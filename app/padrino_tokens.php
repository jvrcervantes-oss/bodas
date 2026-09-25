<?php
// sha256 de los tokens del Padrino (lectura / decisión). El token en claro vive SOLO en Railway
// (PADRINO_LECTURA_TOKEN / PADRINO_DECISION_TOKEN). Vacío = nadie entra (401). Rotar: generar
// otro con tools/padrino_tokens.py en el repo de la agencia, que actualiza esto y Railway a la vez.
// `contenido` publica guías: token propio, se rota y revoca aparte del de decisión (Seguridad #100).
return ['lectura' => 'e714b6846dba587181bda92801ab6684b5638e1533598c4bd19c55cb16b25750', 'decision' => '8acd3d7a0e5dcc32b44d7338c5c602477f7f4263f79b786d94111f5320f4360c', 'contenido' => '82f27166da0a7019c88984ff01bd23e9ff9fd25771ef838aeb66ec01056c5d2c'];
