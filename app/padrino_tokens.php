<?php
// sha256 de los tokens del Padrino (lectura / decisión). El token en claro vive SOLO en Railway
// (PADRINO_LECTURA_TOKEN / PADRINO_DECISION_TOKEN). Vacío = nadie entra (401). Rotar: generar
// otro con tools/padrino_tokens.py en el repo de la agencia, que actualiza esto y Railway a la vez.
return ['lectura' => 'e714b6846dba587181bda92801ab6684b5638e1533598c4bd19c55cb16b25750', 'decision' => '8acd3d7a0e5dcc32b44d7338c5c602477f7f4263f79b786d94111f5320f4360c'];
