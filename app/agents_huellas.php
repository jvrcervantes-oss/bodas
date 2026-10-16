<?php
// /agents (app/agents.php): las dos HUELLAS de acceso. Las escribe `erp/aaas_local/sube_web.py huellas` desde el PC; nunca a mano.
// Van en el repo PÚBLICO porque son huellas de secretos ALEATORIOS generados por máquina (Seguridad, 17-oct-2026): la contraseña es
// secrets.token_urlsafe(18) (144 bits) y la llave token_urlsafe(32) (256 bits). NUNCA la huella de una contraseña elegida por una
// persona: contra ella el ataque sin conexión no tiene freno (sube_web.py se niega a escribirla). Por eso las dos son sha256: contra
// un secreto de 144 bits un hash lento no añade nada. Rotar = `sube_web.py genera --rota` + `huellas` + commit y push.
return [
    'AGENTS_CLAVE_SHA256' => '9c82fb65143fac202f9ee742ce4dc8b3553ca08019aafb344bd01f6f27167889',
    'AGENTS_SUBIDA_SHA256' => 'f18729701d01d57cdc472e3910856390bdeb6c526fbed219ea95e4a208affd9d',
];
