<?php
// sha256 de los tokens del Padrino (lectura / decisión). El token en claro vive SOLO en Railway
// (PADRINO_LECTURA_TOKEN / PADRINO_DECISION_TOKEN). Vacío = nadie entra (401). Rotar: generar
// otro con tools/padrino_tokens.py en el repo de la agencia, que actualiza esto y Railway a la vez.
return ['lectura' => '', 'decision' => ''];
