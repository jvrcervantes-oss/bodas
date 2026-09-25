<?php
// Enlace de alta de la contraseña del panel del estudio (lo crea tools/bodas_estudio_alta.py).
// Solo el sha256 del token (256 bits, irreversible). Se gasta al usarlo; para rehacer la
// contraseña se genera otro con una versión mayor. Revisión previa #96.
return ['version' => 1, 'sha256' => 'aac0f9a183ed8270ed8f2e25746b9287e32ca22bc1ee53dec2eeef9e8e858562', 'caduca' => '2026-10-02'];
