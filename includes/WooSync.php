<?php
/**
 * includes/WooSync.php
 * Sincronización con WooCommerce REST API v3
 */
class WooSync {

    private PDO $db;
    private string $url;
    private string $ck;
    private string $cs;
    private string $campoColegio;

    // Mapeo estados WooCommerce → estado interno
    const ESTADO_MAP = [
        'pending'    => 'pendiente_pago',
        'processing' => 'procesando',
        'on-hold'    => 'procesando',
        'wc-en-produccion' => 'en_produccion',
        'wc-listo-envio'   => 'listo_envio',
        'completed'  => 'entregado',
        'cancelled'  => 'cancelado',
        'refunded'   => 'cancelado',
        'failed'     => 'cancelado',
    ];

    const ESTADO_LABEL = [
        'pendiente_pago'  => 'Pendiente de pago',
        'procesando'      => 'Procesando',
        'en_produccion'   => 'En producción',
        'listo_envio'     => 'Listo para envío',
        'despachado'      => 'Despachado',
        'entregado'       => 'Entregado',
        'cancelado'       => 'Cancelado',
    ];

    const ESTADO_COLOR = [
        'pendiente_pago'  => 'warning',
        'procesando'      => 'primary',
        'en_produccion'   => 'info',
        'listo_envio'     => 'success',
        'despachado'      => 'success',
        'entregado'       => 'secondary',
        'cancelado'       => 'danger',
    ];

    public function __construct(PDO $db) {
        $this->db = $db;
        $cfg = $db->query("SELECT clave, valor FROM configuracion WHERE grupo='woocommerce'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->url          = rtrim($cfg['woo_url']            ?? '', '/');
        $this->ck           = $cfg['woo_consumer_key']         ?? '';
        $this->cs           = $cfg['woo_consumer_secret']      ?? '';
        $this->campoColegio = $cfg['woo_campo_colegio']        ?? 'billing_colegio';
    }

    public function isConfigured(): bool {
        return !empty($this->url) && !empty($this->ck) && !empty($this->cs);
    }

    /**
     * Llama a la API WooCommerce REST v3
     */
    private function apiGet(string $endpoint, array $params = []): array {
        $params['consumer_key']    = $this->ck;
        $params['consumer_secret'] = $this->cs;
        $params['per_page']        = $params['per_page'] ?? 100;

        $url = $this->url . '/wp-json/wc/v3/' . ltrim($endpoint, '/') . '?' . http_build_query($params);

        $ctx = stream_context_create(['http' => [
            'timeout' => 15,
            'header'  => "Accept: application/json\r\n",
        ]]);

        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            throw new RuntimeException("No se pudo conectar a WooCommerce: $url");
        }
        $data = json_decode($resp, true);
        if (!is_array($data)) {
            throw new RuntimeException("Respuesta inválida de WooCommerce");
        }
        return $data;
    }

    /**
     * Sincroniza pedidos desde WooCommerce
     * @param int $paginas Cuántas páginas traer (100 pedidos por página)
     */
    public function sincronizar(int $paginas = 3): array {
        $resultado = ['nuevos' => 0, 'actualizados' => 0, 'errores' => []];

        for ($pag = 1; $pag <= $paginas; $pag++) {
            try {
                $orders = $this->apiGet('orders', [
                    'page'     => $pag,
                    'per_page' => 100,
                    'orderby'  => 'date',
                    'order'    => 'desc',
                    'status'   => 'any',
                ]);

                if (empty($orders)) break;

                foreach ($orders as $order) {
                    try {
                        $this->procesarPedido($order, $resultado);
                    } catch (Exception $e) {
                        $resultado['errores'][] = "Pedido #{$order['id']}: " . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $resultado['errores'][] = "Página $pag: " . $e->getMessage();
                break;
            }
        }

        return $resultado;
    }

    private function procesarPedido(array $order, array &$resultado): void {
        $wooId    = (int)$order['id'];
        $status   = $order['status'] ?? 'processing';
        $estadoInterno = self::ESTADO_MAP[$status] ?? 'procesando';

        // Extraer colegio del campo personalizado del checkout
        $colegioNombre = null;
        foreach (($order['meta_data'] ?? []) as $meta) {
            if ($meta['key'] === $this->campoColegio) {
                $colegioNombre = trim($meta['value']);
                break;
            }
        }
        // Si no está en meta_data, intentar en billing
        if (!$colegioNombre && isset($order['billing'][$this->campoColegio])) {
            $colegioNombre = trim($order['billing'][$this->campoColegio]);
        }

        // Buscar colegio en BD por nombre (cruce aproximado)
        $colegioId = null;
        if ($colegioNombre) {
            $st = $this->db->prepare("SELECT id FROM colegios WHERE activo=1 AND nombre LIKE ? LIMIT 1");
            $st->execute(['%' . $colegioNombre . '%']);
            $colegioId = $st->fetchColumn() ?: null;
        }

        $data = [
            'woo_order_id'    => $wooId,
            'woo_status'      => $status,
            'estado_interno'  => $estadoInterno,
            'cliente_nombre'  => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
            'cliente_email'   => $order['billing']['email']   ?? null,
            'cliente_telefono'=> $order['billing']['phone']   ?? null,
            'colegio_nombre'  => $colegioNombre,
            'colegio_id'      => $colegioId,
            'fecha_compra'    => date('Y-m-d H:i:s', strtotime($order['date_created'])),
            'total_cop'       => (float)$order['total'],
            'metodo_pago'     => $order['payment_method_title'] ?? null,
            'notas_cliente'   => $order['customer_note'] ?? null,
        ];

        // Verificar si ya existe
        $existe = $this->db->query("SELECT id, estado_interno FROM woo_pedidos WHERE woo_order_id=$wooId")->fetch();

        if ($existe) {
            // Actualizar solo si cambió el estado
            $sets = implode(',', array_map(fn($k) => "$k=:$k", array_keys($data)));
            $data['id'] = $existe['id'];
            $this->db->prepare("UPDATE woo_pedidos SET $sets, sincronizado_at=NOW() WHERE id=:id")->execute($data);

            // Registrar cambio de estado en historial
            if ($existe['estado_interno'] !== $estadoInterno) {
                $this->registrarHistorial($existe['id'], $existe['estado_interno'], $estadoInterno, 'Actualizado por sincronización WooCommerce');
            }
            $resultado['actualizados']++;
        } else {
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $this->db->prepare("INSERT INTO woo_pedidos ($cols) VALUES ($vals)")->execute($data);
            $nuevoPedidoId = (int)$this->db->lastInsertId();
            $this->registrarHistorial($nuevoPedidoId, null, $estadoInterno, 'Importado desde WooCommerce');

            // Insertar items
            foreach (($order['line_items'] ?? []) as $item) {
                $sku   = $item['sku'] ?? null;
                $kitId = null;
                if ($sku) {
                    $stKit = $this->db->prepare("SELECT id FROM kits WHERE codigo=? AND activo=1 LIMIT 1");
                    $stKit->execute([$sku]);
                    $kitId = $stKit->fetchColumn() ?: null;
                }
                // Intentar cruzar por nombre si no hay SKU
                if (!$kitId) {
                    $st2 = $this->db->prepare("SELECT id FROM kits WHERE activo=1 AND nombre LIKE ? LIMIT 1");
                    $st2->execute(['%' . ($item['name'] ?? '') . '%']);
                    $kitId = $st2->fetchColumn() ?: null;
                }
                $this->db->prepare("INSERT INTO woo_pedido_items (pedido_id,woo_product_id,kit_id,nombre,sku,cantidad,precio_unit,subtotal) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$nuevoPedidoId, $item['product_id'] ?? null, $kitId, $item['name'], $sku, $item['quantity'], $item['price'] ?? 0, $item['subtotal'] ?? 0]);
            }
            $resultado['nuevos']++;
        }
    }

    public function registrarHistorial(int $pedidoId, ?string $ant, string $nuevo, ?string $nota = null, ?int $userId = null): void {
        $this->db->prepare("INSERT INTO woo_pedido_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
            ->execute([$pedidoId, $ant, $nuevo, $nota, $userId]);
    }

    /**
     * Cambia estado interno de un pedido
     */
    public function cambiarEstado(int $pedidoId, string $nuevoEstado, ?string $nota = null, int $userId = 0): void {
        $actual = $this->db->query("SELECT estado_interno FROM woo_pedidos WHERE id=$pedidoId")->fetchColumn();
        $this->db->prepare("UPDATE woo_pedidos SET estado_interno=? WHERE id=?")->execute([$nuevoEstado, $pedidoId]);
        $this->registrarHistorial($pedidoId, $actual, $nuevoEstado, $nota, $userId ?: null);
    }

    public static function getSemaforoClass(string $semaforo): string {
        return match($semaforo) {
            'verde'     => 'success',
            'amarillo'  => 'warning',
            'rojo'      => 'danger',
            'completado'=> 'secondary',
            default     => 'light',
        };
    }
}
