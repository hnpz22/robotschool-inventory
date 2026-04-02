<?php
/**
 * WooSync — Integración bidireccional con WooCommerce REST API v3
 *
 * ENTRADA:  API REST para importación histórica de pedidos pagados
 *           + receptor de webhooks (woo_webhook.php usa procesarDesdeWebhook)
 * SALIDA:   Actualización de estado en WooCommerce cuando cambia en el sistema
 */
class WooSync {

    private PDO    $db;
    private string $url          = '';
    private string $ck           = '';
    private string $cs           = '';
    private string $campoColegio = 'billing_colegio';
    private bool   $configured    = false;
    private bool   $colEstadoPago = false;
    private bool   $tblItemsOk   = false;

    public function __construct(PDO $db) {
        $this->db = $db;
        try {
            $cfg = $db->query(
                "SELECT clave, valor FROM configuracion WHERE grupo='woocommerce'"
            )->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $cfg = [];
        }

        // Prioridad: tabla configuracion → constantes de .env (via config.php)
        $this->url          = rtrim(
            ($cfg['woo_url']            ?: (defined('WOO_URL')             ? WOO_URL             : '')),
            '/'
        );
        $this->ck           = $cfg['woo_consumer_key']    ?: (defined('WOO_CONSUMER_KEY')    ? WOO_CONSUMER_KEY    : '');
        $this->cs           = $cfg['woo_consumer_secret'] ?: (defined('WOO_CONSUMER_SECRET') ? WOO_CONSUMER_SECRET : '');
        $this->campoColegio = $cfg['woo_campo_colegio']   ?: (defined('WOO_CAMPO_COLEGIO')   ? WOO_CAMPO_COLEGIO   : 'billing_colegio');

        $this->configured   = !empty($this->url) && !empty($this->ck) && !empty($this->cs);
        $this->colEstadoPago = (bool) $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
            AND COLUMN_NAME='estado_pago'")->fetchColumn();
        $this->tblItemsOk = (bool) $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedido_items'")->fetchColumn();
    }

    public function isConfigured(): bool { return $this->configured; }

    // ── Importación histórica ─────────────────────────────────────────────────

    /**
     * Importa todos los pedidos desde la API REST (cualquier status).
     * Pagina automáticamente hasta agotar resultados o $maxPaginas.
     * Retorna ['importados', 'duplicados', 'errores', 'detalle'].
     */
    // Statuses de WooCommerce que se importan
    private const STATUSES_IMPORTAR = ['processing', 'entregado', 'recibido', 'enviado'];

    public function importarHistorico(int $maxPaginas = 10): array {
        $r = ['importados' => 0, 'duplicados' => 0, 'errores' => 0, 'detalle' => [], 'statuses_vistos' => []];

        for ($pag = 1; $pag <= $maxPaginas; $pag++) {
            $orders = $this->apiRequest('orders', 'GET', [
                'status'   => 'any',
                'page'     => $pag,
                'per_page' => 100,
                'orderby'  => 'date',
                'order'    => 'desc',
            ]);

            if ($orders === null) {
                $r['errores']++;
                $r['detalle'][] = "Página $pag: no se pudo conectar a WooCommerce.";
                break;
            }

            if (empty($orders)) break;

            foreach ($orders as $order) {
                if (!is_array($order)) continue;
                $st = $order['status'] ?? '?';
                $r['statuses_vistos'][$st] = ($r['statuses_vistos'][$st] ?? 0) + 1;
                if (!in_array($st, self::STATUSES_IMPORTAR, true)) continue;
                $res = $this->procesarDesdeWebhook($order);
                if ($res === 'ok')            $r['importados']++;
                elseif ($res === 'duplicado') $r['duplicados']++;
                else { $r['errores']++; $r['detalle'][] = "Pedido #{$order['id']}: $res"; }
            }
        }

        return $r;
    }

    // ── Webhook / inserción individual ────────────────────────────────────────

    /**
     * Procesa un pedido WooCommerce (recibido por webhook o importación histórica).
     * Retorna 'ok', 'duplicado', o 'error: <mensaje>'.
     */
    public function procesarDesdeWebhook(array $order): string {
        $wooId = (string)($order['id'] ?? '');
        if ($wooId === '') return 'error: id de pedido vacío';

        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_pedidos WHERE woo_order_id = ?");
            $st->execute([$wooId]);
            if ((int)$st->fetchColumn() > 0) return 'duplicado';

            $data = $this->mapearPedido($order);
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $this->db->prepare("INSERT INTO tienda_pedidos ($cols) VALUES ($vals)")->execute($data);
            $newId = (int)$this->db->lastInsertId();

            // ── Insertar ítems individuales en tienda_pedido_items ────
            if ($this->tblItemsOk && $newId) {
                $stItem = $this->db->prepare(
                    "INSERT INTO tienda_pedido_items
                     (pedido_id, kit_id, kit_nombre, cantidad, precio_unit, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stKit = $this->db->prepare(
                    "SELECT id FROM kits WHERE activo=1 AND nombre = ? LIMIT 1"
                );
                $stKitLike = $this->db->prepare(
                    "SELECT id FROM kits WHERE activo=1 AND nombre LIKE ? LIMIT 1"
                );
                foreach (($order['line_items'] ?? []) as $li) {
                    $nombre   = trim($li['name'] ?? '');
                    if ($nombre === '') continue;
                    $cantidad = max(1, (int)($li['quantity'] ?? 1));
                    $total    = (float)($li['total'] ?? 0);
                    $precioU  = $cantidad > 0 ? round($total / $cantidad, 2) : 0;

                    // Intentar cruzar con kit: exacto primero, luego LIKE
                    $stKit->execute([$nombre]);
                    $kitId = $stKit->fetchColumn() ?: null;
                    if (!$kitId) {
                        $stKitLike->execute(['%' . $nombre . '%']);
                        $kitId = $stKitLike->fetchColumn() ?: null;
                    }

                    $stItem->execute([$newId, $kitId, $nombre, $cantidad, $precioU, $total]);
                }
            }

            return 'ok';
        } catch (Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    // ── Salida: actualizar estado en WooCommerce ──────────────────────────────

    /**
     * Actualiza el estado de un pedido en WooCommerce.
     * $nuevoEstadoWoo: 'on-hold' (al aprobar) o 'completed' (al despachar).
     * Retorna true si la actualización fue exitosa.
     */
    public function actualizarEstado(string $wooOrderId, string $nuevoEstadoWoo): bool {
        $resp = $this->apiRequest("orders/$wooOrderId", 'PUT', ['status' => $nuevoEstadoWoo]);
        return $resp !== null && !empty($resp['id']);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Mapea un pedido WooCommerce (array de la API) al array de columnas de tienda_pedidos.
     */
    private function mapearPedido(array $order): array {
        // ── Buscar colegio ─────────────────────────────────────────
        // 1. Meta field configurado (billing_colegio u otro)
        $colegioNombre = null;
        $sessionEntry  = null;
        foreach (($order['meta_data'] ?? []) as $meta) {
            $key = $meta['key'] ?? '';
            if ($key === $this->campoColegio) {
                $colegioNombre = trim((string)($meta['value'] ?? '')) ?: null;
            }
            if ($key === '_wc_order_attribution_session_entry') {
                $sessionEntry = (string)($meta['value'] ?? '');
            }
        }

        // 2. Fallback: extraer slug del colegio de la URL de sesión
        //    Ej: .../colegios-kits/colegio-sagrado-corazon-de-jesus-medellin/
        if (!$colegioNombre && $sessionEntry) {
            if (preg_match('~colegios?-kits?/([^/?]+)~i', $sessionEntry, $m)) {
                $slug = preg_replace('#^colegios?-#i', '', $m[1]);
                $colegioNombre = ucwords(str_replace('-', ' ', $slug));
            }
        }

        // 3. Intentar cruzar con colegio registrado en el sistema
        $colegioId = null;
        if ($colegioNombre) {
            $palabras = explode(' ', $colegioNombre);
            $busqueda = implode(' ', array_slice($palabras, 0, 3));
            $st = $this->db->prepare("SELECT id, nombre FROM colegios WHERE activo=1 AND nombre LIKE ? LIMIT 1");
            $st->execute(['%' . $busqueda . '%']);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $colegioId     = $row['id'];
                $colegioNombre = $row['nombre']; // reemplazar con nombre oficial
            }
            // Si no hay match, colegio_nombre queda con el texto extraído (visible pero sin vincular)
        }

        // Extraer grado del alumno (billing_grado en meta_data)
        $grado = null;
        foreach (($order['meta_data'] ?? []) as $meta) {
            if (($meta['key'] ?? '') === 'billing_grado') {
                $grado = trim((string)($meta['value'] ?? '')) ?: null;
                break;
            }
        }

        // kit_nombre: nombre(s) de líneas + grado si aplica
        $items     = $order['line_items'] ?? [];
        $kitNombres = array_filter(array_map(fn($i) => $i['name'] ?? '', $items));
        $kitNombre  = implode(' + ', $kitNombres);
        if ($grado && count($kitNombres) === 1) {
            $kitNombre .= ' — Grado ' . $grado;
        }

        // Dirección de envío
        $dir = trim(
            ($order['shipping']['address_1'] ?? '') .
            (!empty($order['shipping']['address_2']) ? ', ' . $order['shipping']['address_2'] : '')
        );

        // Fecha: preferir date_paid, fallback date_created
        $fechaRaw    = $order['date_paid'] ?? $order['date_created'] ?? null;
        $fechaCompra = $fechaRaw ? date('Y-m-d', strtotime($fechaRaw)) : date('Y-m-d');

        return [
            'woo_order_id'       => (string)$order['id'],
            'numero_pedido'      => '#' . ($order['number'] ?? $order['id']),
            'woo_status'         => $order['status']               ?? 'processing',
            'woo_payment_method' => $order['payment_method_title'] ?? null,
            'woo_total'          => (float)($order['total']        ?? 0),
            'woo_payload'        => json_encode($order),
            'woo_items_payload'  => json_encode($items),
            'estado'             => match($order['status'] ?? '') {
                'entregado', 'recibido' => 'entregado',
                'enviado'               => 'despachado',
                default                 => 'pendiente',
            },
            'cliente_nombre'     => trim(
                ($order['billing']['first_name'] ?? '') . ' ' .
                ($order['billing']['last_name']  ?? '')
            ),
            'cliente_email'      => $order['billing']['email'] ?? null,
            'cliente_telefono'   => $order['billing']['phone'] ?? null,
            'direccion'          => $dir  ?: null,
            'ciudad'             => $order['shipping']['city'] ?? null,
            'colegio_nombre'     => $colegioNombre,
            'colegio_id'         => $colegioId,
            'kit_nombre'         => $kitNombre ?: null,
            'notas_internas'     => $order['customer_note'] ?? null,
            'fecha_compra'       => $fechaCompra,
            'creado_desde_csv'   => 0,
        ] + ($this->colEstadoPago ? ['estado_pago' => 'aprobado'] : []);
    }

    /**
     * Ejecuta un request HTTP a la API REST WooCommerce con autenticación Basic.
     */
    private function apiRequest(string $endpoint, string $method = 'GET', array $body = []): ?array {
        $url = $this->url . '/wp-json/wc/v3/' . ltrim($endpoint, '/');

        if ($method === 'GET' && !empty($body)) {
            $url .= '?' . http_build_query($body);
        }

        $headers = "Accept: application/json\r\n" .
                   "Authorization: Basic " . base64_encode($this->ck . ':' . $this->cs) . "\r\n";

        $opts = ['http' => [
            'method'        => $method,
            'timeout'       => 15,
            'header'        => $headers,
            'ignore_errors' => true,
        ]];

        if ($method !== 'GET' && !empty($body)) {
            $opts['http']['header']  .= "Content-Type: application/json\r\n";
            $opts['http']['content']  = json_encode($body);
        }

        $resp = @file_get_contents($url, false, stream_context_create($opts));
        if ($resp === false) return null;

        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }
}
