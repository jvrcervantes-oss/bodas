<?php
// Códigos de cortesía: el owner regala la web a amigos (100 %, sin Stripe). 25-sep-2026.
// Revisión previa #95 (Seguridad + Administración):
//  - Cada código son 15 caracteres Crockford base32 (75 bits, random_bytes), con caducidad
//    obligatoria. En este repo PÚBLICO solo va su sha256 con id opaco, usos, pack y caducidad:
//    nunca el código ni a quién se dio. Con 75 bits el hash no se puede invertir por fuerza bruta.
//  - Los usos se cuentan en DATA_DIR/cortesia (fuera de las bodas: borrar una boda no
//    devuelve el uso) y se consumen dentro del cerrojo de las altas, atados al pedido:
//    un reintento del mismo pedido no gasta otro uso.
//  - Límites: 10 intentos/hora por IP (en cerrado si el disco falla) y 200 FALLOS al día en
//    total; los canjes buenos no cuentan para el tope global.
//  - Regalar el servicio es autoconsumo (art. 12.3 LIVA): lleva IVA sobre el coste y no se
//    hace factura BODA-. Por eso cada canje queda en un registro para el gestor (BOD-4).
// Los códigos se crean con tools/bodas_cortesia.py (repo del estudio), que añade la línea aquí.
declare(strict_types=1);

const CORTESIA_ALFABETO = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/** Mayúsculas, sin guiones ni espacios, y las confusiones típicas de Crockford resueltas. */
function cortesia_normaliza(string $c): string {
    $c = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $c));
    return strtr($c, ['O' => '0', 'I' => '1', 'L' => '1', 'U' => 'V']);
}

/**
 * Todos los códigos: los del repo (tools/bodas_cortesia.py) y los creados desde el panel del
 * estudio (DATA_DIR/cortesia/codigos.json, con su nota), menos los anulados.
 * [sha256 => ['id', 'usos', 'atelier', 'caduca', 'nota'?]]
 */
function cortesia_codigos(bool $conAnulados = false): array {
    $f = APP_DIR . '/cortesia_codigos.php';
    $d = is_file($f) ? require $f : [];
    $d = (is_array($d) ? $d : []) + (lee_json(dir_datos('cortesia', 'codigos.json')) ?? []);
    if ($conAnulados) return $d;
    $anulados = lee_json(dir_datos('cortesia', 'anulados.json')) ?? [];
    return array_diff_key($d, array_flip($anulados));
}

/** Crea un código desde el panel del estudio. Devuelve [id, código en claro] (se enseña una vez). */
function cortesia_crea(int $usos, bool $atelier, string $caduca, string $nota): array {
    $codigo = '';
    for ($i = 0; $i < 15; $i++) $codigo .= CORTESIA_ALFABETO[random_int(0, 31)];
    $h = hash('sha256', $codigo);
    asegura_dir(dir_datos('cortesia'));
    $id = muta_json(dir_datos('cortesia', 'codigos.json'), function (array &$d) use ($h, $usos, $atelier, $caduca, $nota) {
        $n = 1;
        foreach ($d as $x) $n = max($n, (int) substr((string) ($x['id'] ?? 'E-0'), 2) + 1);
        $id = sprintf('E-%03d', $n);
        $d[$h] = ['id' => $id, 'usos' => $usos, 'atelier' => $atelier, 'caduca' => $caduca, 'nota' => $nota, 'creado' => date('c')];
        return $id;
    });
    return [(string) $id, implode('-', str_split($codigo, 5))];
}

function cortesia_anula(string $id): bool {
    foreach (cortesia_codigos(true) as $h => $d) {
        if (($d['id'] ?? '') === $id) {
            muta_json(dir_datos('cortesia', 'anulados.json'), function (array &$a) use ($h) { if (!in_array($h, $a, true)) $a[] = $h; });
            return true;
        }
    }
    return false;
}

/** Para el panel del estudio: cada código con sus usos gastados y su estado. */
function cortesia_listado(): array {
    $anulados = lee_json(dir_datos('cortesia', 'anulados.json')) ?? [];
    $o = [];
    foreach (cortesia_codigos(true) as $h => $d) {
        $usados = count((lee_json(dir_datos('cortesia', 'usos', $h . '.json')) ?? [])['pedidos'] ?? []);
        $estado = in_array($h, $anulados, true) ? 'Anulado' : ($usados >= (int) $d['usos'] ? 'Gastado' : ((string) $d['caduca'] < date('Y-m-d') ? 'Caducado' : 'Activo'));
        $o[] = ['id' => (string) $d['id'], 'atelier' => !empty($d['atelier']), 'usos' => (int) $d['usos'], 'usados' => $usados, 'caduca' => (string) $d['caduca'], 'nota' => (string) ($d['nota'] ?? ''), 'estado' => $estado];
    }
    usort($o, fn($a, $b) => strcmp($b['id'], $a['id']));
    return $o;
}

/** Código válido y vigente → [hash, datos]; si no, null. */
function cortesia_busca(string $codigo): ?array {
    $n = cortesia_normaliza($codigo);
    if (strlen($n) !== 15 || strspn($n, CORTESIA_ALFABETO) !== 15) return null;
    $h = hash('sha256', $n);
    foreach (cortesia_codigos() as $hash => $d) {
        if (hash_equals((string) $hash, $h)) {
            return (($d['caduca'] ?? '') !== '' && $d['caduca'] >= date('Y-m-d')) ? [$h, $d] : null;
        }
    }
    return null;
}

function cortesia_bloqueada(): bool {
    $b = lee_json(dir_datos('cortesia', 'bloqueo.json'));
    return $b && ($b['hasta'] ?? 0) > time();
}

/** Un intento fallido: cuenta para el tope global y, si se pasa, cierra los canjes un día. */
function cortesia_fallo(): void {
    asegura_dir(dir_datos('cortesia'));
    if (!limite('cortesia-fallos', 200, 86400, true)) {
        escribe_json(dir_datos('cortesia', 'bloqueo.json'), ['hasta' => time() + 86400]);
        registra('ALERTA códigos de cortesía cerrados 24 h: demasiados intentos fallidos');
    }
}

/**
 * Consume un uso para este pedido. Llamar SOLO dentro de con_cerrojo(). Idempotente por
 * pedido: si ya lo consumió, devuelve true sin gastar otro.
 */
function cortesia_consume(string $hash, array $d, string $pedido): bool {
    asegura_dir(dir_datos('cortesia', 'usos'));
    $f = dir_datos('cortesia', 'usos', $hash . '.json');
    $u = lee_json($f) ?? ['pedidos' => []];
    if (in_array($pedido, $u['pedidos'], true)) return true;
    if (count($u['pedidos']) >= (int) ($d['usos'] ?? 1)) return false;
    $u['pedidos'][] = $pedido;
    escribe_json($f, $u);
    return true;
}

/** Registro de solo añadir: para el gestor (autoconsumo) y para auditar. Nunca el código. */
function cortesia_registra(array $fila): void {
    asegura_dir(dir_datos('cortesia'));
    file_put_contents(dir_datos('cortesia', 'canjes.jsonl'), json_encode($fila + ['fecha' => date('c')], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
