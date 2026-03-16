<?php
// includes/helpers.php

// ── Código automático por categoría RS-ARD-001 ──
function generarCodigo(int $categoriaId): string {
    $db = Database::get();
    $db->beginTransaction();
    try {
        $st = $db->prepare("SELECT c.prefijo, cs.ultimo_numero FROM categorias c JOIN codigos_secuencia cs ON cs.categoria_id=c.id WHERE c.id=? FOR UPDATE");
        $st->execute([$categoriaId]);
        $row = $st->fetch();
        if (!$row) throw new Exception("Categoría no encontrada");
        $nuevo = $row['ultimo_numero'] + 1;
        $db->prepare("UPDATE codigos_secuencia SET ultimo_numero=? WHERE categoria_id=?")->execute([$nuevo, $categoriaId]);
        $db->commit();
        return 'RS-' . $row['prefijo'] . '-' . str_pad($nuevo, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// ── Semáforo de stock ──
function semaforo(int $actual, int $minimo, int $maximo): array {
    if ($actual <= 0)       return ['color'=>'danger',  'label'=>'Sin stock',  'icon'=>'🔴'];
    if ($actual <= $minimo) return ['color'=>'warning', 'label'=>'Stock bajo', 'icon'=>'🟡'];
    if ($actual >= $maximo) return ['color'=>'info',    'label'=>'Lleno',      'icon'=>'🔵'];
    return                         ['color'=>'success', 'label'=>'Normal',     'icon'=>'🟢'];
}

// ── Liquidación de importación ──
// Distribuye flete + aranceles + IVA proporcional por peso
function liquidarPedido(int $pedidoId): array {
    $db = Database::get();

    // Cargar cabecera del pedido
    $ped = $db->query("SELECT * FROM pedidos_importacion WHERE id=$pedidoId")->fetch();
    if (!$ped) throw new Exception("Pedido no encontrado");

    $trm         = (float)$ped['tasa_cambio_usd_cop'];
    $dhlCOP      = (float)$ped['costo_dhl_usd'] * $trm;
    $arancelPct  = (float)$ped['arancel_pct'] / 100;
    $ivaPct      = (float)$ped['iva_pct'] / 100;
    $otrosCOP    = (float)$ped['otros_impuestos_cop'];

    // Base imponible CIF en COP
    $fobUSD      = (float)$ped['valor_fob_usd'];
    $seguroUSD   = (float)$ped['valor_seguro_usd'];
    $cifUSD      = $fobUSD + (float)$ped['costo_dhl_usd'] + $seguroUSD;
    $cifCOP      = $cifUSD * $trm;

    $arancelTotalCOP = $cifCOP * $arancelPct;
    $ivaTotalCOP     = ($cifCOP + $arancelTotalCOP) * $ivaPct;
    $costoTotalCOP   = $cifCOP + $arancelTotalCOP + $ivaTotalCOP + $otrosCOP;

    // Items del pedido
    $items = $db->query("SELECT * FROM pedido_items WHERE pedido_id=$pedidoId")->fetchAll();
    if (!$items) throw new Exception("El pedido no tiene ítems");

    $pesoTotalGramos = array_sum(array_column($items, 'peso_total_gramos'));
    if ($pesoTotalGramos <= 0) throw new Exception("El peso total es 0. Verifica los ítems.");

    $db->beginTransaction();
    try {
        foreach ($items as $item) {
            $pct      = $item['peso_total_gramos'] / $pesoTotalGramos;
            $fleteIt  = $dhlCOP * $pct;
            $arancIt  = $arancelTotalCOP * $pct;
            $ivaIt    = $ivaTotalCOP * $pct;
            $otrosIt  = $otrosCOP * $pct;

            $costoMercCOP = $item['precio_unit_usd'] * $trm;
            $costoUnitFinal = $costoMercCOP + (($fleteIt + $arancIt + $ivaIt + $otrosIt) / $item['cantidad']);

            $db->prepare("UPDATE pedido_items SET pct_peso=?, flete_asignado_cop=?, arancel_asignado_cop=?, iva_asignado_cop=?, costo_unit_final_cop=? WHERE id=?")
               ->execute([round($pct,6), round($fleteIt,2), round($arancIt,2), round($ivaIt,2), round($costoUnitFinal,4), $item['id']]);

            // Actualizar costo_real_cop en el elemento
            $db->prepare("UPDATE elementos SET costo_real_cop=? WHERE id=?")->execute([round($costoUnitFinal,2), $item['elemento_id']]);

            // Registrar movimiento de entrada
            $elem = $db->query("SELECT stock_actual FROM elementos WHERE id={$item['elemento_id']}")->fetch();
            $antes = (int)$elem['stock_actual'];
            $despues = $antes + $item['cantidad'];
            $db->prepare("UPDATE elementos SET stock_actual=? WHERE id=?")->execute([$despues, $item['elemento_id']]);
            $db->prepare("INSERT INTO movimientos (elemento_id,tipo,cantidad,stock_antes,stock_despues,referencia,motivo,costo_unit_cop,pedido_id,usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$item['elemento_id'],'entrada',$item['cantidad'],$antes,$despues,$ped['codigo_pedido'],'Importación liquidada',round($costoUnitFinal,4),$pedidoId,$_SESSION['user_id']??null]);
        }

        // Actualizar totales en cabecera del pedido
        $db->prepare("UPDATE pedidos_importacion SET total_cif_usd=?, total_arancel_cop=?, total_iva_cop=?, total_dhl_cop=?, costo_total_cop=?, estado='liquidado', liquidado_por=?, liquidado_at=NOW() WHERE id=?")
           ->execute([round($cifUSD,2), round($arancelTotalCOP,2), round($ivaTotalCOP,2), round($dhlCOP,2), round($costoTotalCOP,2), $_SESSION['user_id']??null, $pedidoId]);

        $db->commit();
        return ['ok'=>true, 'costo_total_cop'=>$costoTotalCOP, 'items'=>count($items)];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// ── Formateo de pesos ──
function cop(float $valor): string {
    return '$ ' . number_format($valor, 0, ',', '.');
}
function usd(float $valor): string {
    return 'USD ' . number_format($valor, 2, '.', ',');
}

// ── Auditoría ──
function auditoria(string $accion, string $tabla='', int $regId=0, array $antes=[], array $despues=[]): void {
    try {
        $db = Database::get();
        $db->prepare("INSERT INTO auditoria (usuario_id,accion,tabla,registro_id,datos_antes,datos_desp,ip) VALUES (?,?,?,?,?,?,?)")
           ->execute([$_SESSION['user_id']??null, $accion, $tabla, $regId ?: null, $antes ? json_encode($antes):null, $despues ? json_encode($despues):null, $_SERVER['REMOTE_ADDR']??null]);
    } catch (Exception $e) { /* silent */ }
}

// ── Upload de imagen ──
function subirFoto(array $file, string $subdir='elementos'): ?string {
    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) throw new Exception("Formato no permitido");
    if ($file['size'] > 5 * 1024 * 1024) throw new Exception("Imagen mayor a 5MB");
    $nombre = uniqid('img_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) throw new Exception("Error al guardar imagen");
    return $subdir . '/' . $nombre;
}

// ── Paginación ──
function paginar(int $total, int $pagina, int $porPagina=ITEMS_PER_PAGE): array {
    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = max(1, min($pagina, $totalPaginas));
    return [
        'total'        => $total,
        'pagina'       => $pagina,
        'por_pagina'   => $porPagina,
        'total_paginas'=> $totalPaginas,
        'offset'       => ($pagina - 1) * $porPagina,
    ];
}


// ── AUTO-ENVÍO A PRODUCCIÓN ──────────────────────────────────────────────────
// Crea solicitud en produccion automaticamente desde cualquier fuente
function crearSolicitudProduccion(PDO $db, array $datos): int {
    if (!$db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()) return 0;

    // Verificar columnas opcionales (dependen de migration v3.5)
    $colFuente = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'fuente'")->fetchColumn();
    $colTitulo = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'titulo'")->fetchColumn();
    $colConvId = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'convenio_id'")->fetchColumn();
    $colColId  = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'colegio_id'")->fetchColumn();

    $campos = ['tipo','cantidad','prioridad','estado','kit_nombre','notas','solicitado_por','pedido_id','fecha_limite'];
    $vals   = [
        $datos['tipo']         ?? 'armar_kit',
        (int)($datos['cantidad']  ?? 1),
        (int)($datos['prioridad'] ?? 2),
        'pendiente',
        $datos['kit_nombre']   ?? null,
        $datos['notas']        ?? null,
        $datos['usuario_id']   ?? null,
        $datos['pedido_id']    ?? null,
        $datos['fecha_limite'] ?? null,
    ];

    if ($colFuente) { $campos[] = 'fuente';     $vals[] = $datos['fuente']      ?? 'tienda'; }
    if ($colTitulo) { $campos[] = 'titulo';      $vals[] = $datos['titulo']      ?? null; }
    if ($colConvId) { $campos[] = 'convenio_id'; $vals[] = $datos['convenio_id'] ?? null; }
    if ($colColId)  { $campos[] = 'colegio_id';  $vals[] = $datos['colegio_id']  ?? null; }

    $ph = implode(',', array_fill(0, count($campos), '?'));
    $db->prepare("INSERT INTO solicitudes_produccion (".implode(',',$campos).") VALUES ($ph)")
       ->execute($vals);
    $sid = (int)$db->lastInsertId();

    if ($db->query("SHOW TABLES LIKE 'solicitud_historial'")->fetchColumn()) {
        $db->prepare("INSERT INTO solicitud_historial (solicitud_id,estado,usuario_id,comentario) VALUES (?,?,?,?)")
           ->execute([$sid,'pendiente',$datos['usuario_id']??null,$datos['historial_nota']??'Creado automáticamente']);
    }
    return $sid;
}

