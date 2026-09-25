<?php
// Factura simplificada (B2C, < 400 €) con serie propia BODA-AAAA-NNNN.
// El recibo de Stripe no vale como factura española (Administración, #81): aquí se
// numera en correlativo, con los datos del emisor, y queda ligada al session_id
// para que un reintento del webhook nunca cree otro número.
//
// Pendiente (Administración): pasar a un software adaptado a Verifactu antes de la
// fecha que toque a la entidad, y OSS al superar 10.000 €/año de ventas UE no ES.

declare(strict_types=1);

function emite_factura(array $ped): string {
    $anio = date('Y');
    $n = muta_json(dir_datos('facturas', 'contador.json'), function (array &$d) use ($anio) {
        $d[$anio] = ($d[$anio] ?? 0) + 1;
        return $d[$anio];
    });
    if (!is_int($n)) throw new RuntimeException('No se pudo numerar la factura');
    $num = sprintf('BODA-%s-%04d', $anio, $n);
    escribe_json(dir_datos('facturas', $num . '.json'), [
        'numero' => $num,
        'fecha' => date('Y-m-d'),
        'emisor' => empresa(),
        'cliente' => ['nombre' => $ped['nombre'], 'email' => $ped['email'], 'pais' => $ped['pais_facturacion']],
        'concepto' => 'Web de boda ' . $ped['slug'] . '.' . BASE_DOMAIN . ': creación y alojamiento'
            . (($ped['atelier'] ?? '') !== '' ? ', con diseño Atelier «' . ATELIER[$ped['atelier']]['nombre'] . '»' : ''),
        'base' => $ped['importe']['base'],
        'iva_pct' => IVA_PCT,
        'iva' => $ped['importe']['iva'],
        'total' => $ped['importe']['total'],
        'session_id' => $ped['session_id'],
        'pruebas_residencia' => ['facturacion' => $ped['pais_facturacion'], 'tarjeta' => $ped['pais_tarjeta']],
    ]);
    return $num;
}

function render_factura(string $num): ?string {
    if (!preg_match('/^BODA-\d{4}-\d{4,}$/', $num)) return null;
    $f = lee_json(dir_datos('facturas', $num . '.json'));
    if (!$f) return null;
    $e = $f['emisor'];
    ob_start(); ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Factura <?= h($f['numero']) ?></title><meta name="robots" content="noindex">
<style>body{font:15px/1.6 system-ui,sans-serif;color:#191C1D;max-width:640px;margin:40px auto;padding:0 16px;background:#fff}h1{font-size:22px;margin:0 0 4px}table{width:100%;border-collapse:collapse;margin:24px 0}td,th{padding:8px 0;border-bottom:1px solid #E3E7E1;text-align:left}td.n,th.n{text-align:right}.muted{color:#56695D;font-size:13px}@media print{body{margin:0}}</style>
</head><body>
<h1>Factura simplificada <?= h($f['numero']) ?></h1>
<p class="muted">Fecha de expedición y de operación: <?= h(date('d/m/Y', strtotime($f['fecha']))) ?></p>
<p><b><?= h($e['titular'] ?: '—') ?></b><br>NIF: <?= h($e['nif'] ?: '—') ?><br><?= h($e['domicilio'] ?: '—') ?><br><?= h($e['email']) ?></p>
<p class="muted">Cliente: <?= h(trim($f['cliente']['nombre'] . ' · ' . $f['cliente']['email'], ' ·')) ?></p>
<table>
<tr><th>Concepto</th><th class="n">Importe</th></tr>
<tr><td><?= h($f['concepto']) ?></td><td class="n"><?= h(euros($f['base'])) ?></td></tr>
<tr><td>IVA (<?= h($f['iva_pct']) ?> %)</td><td class="n"><?= h(euros($f['iva'])) ?></td></tr>
<tr><td><b>Total</b></td><td class="n"><b><?= h(euros($f['total'])) ?></b></td></tr>
</table>
<p class="muted">Pagado con tarjeta a través de Stripe.</p>
</body></html>
<?php
    return (string) ob_get_clean();
}
