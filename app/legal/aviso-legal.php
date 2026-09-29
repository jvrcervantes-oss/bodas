<?php
// Aviso legal (art. 10 LSSI). Versión 2026-09-27 (Legal): marca «<marca>, un producto de AxisWorks»,
// dominio del producto y Lemon Squeezy como vendedor. Se incluye dentro de un <main> ya maquetado. $E lo define la app.
$L = textos_legales();
$M = h(marca());
$V = h($L['vendedor'] ?? '');
?>
<h1>Aviso legal</h1>

<h2>Quién está detrás de esta web</h2>
<?php if ($E['nif'] !== ''): ?>
<p><?= $M ?> es un producto de AxisWorks, el nombre comercial con el que <?= h($E['titular']) ?> ofrece el creador de webs de boda de <?= h(BASE_DOMAIN) ?> y las webs que se publican en sus subdominios.</p>
<ul>
  <li>Titular: <?= h($E['titular']) ?></li>
  <li>NIF: <?= h($E['nif']) ?></li>
  <li>Domicilio: <?= h($E['domicilio']) ?></li>
  <li>Email: <?= email_enlace($E['email']) ?></li>
</ul>
<?php else: ?>
<p><?= $M ?> es un producto de AxisWorks que ofrece el creador de webs de boda de <?= h(BASE_DOMAIN) ?> y las webs que se publican en sus subdominios. El servicio está en pruebas y todavía no se vende: los datos de quien lo presta (nombre, NIF y domicilio) se publicarán aquí antes de abrir la venta.</p>
<ul>
  <li>Email: <?= email_enlace($E['email']) ?></li>
</ul>
<?php endif; ?>

<h2>Para qué sirve esta web</h2>
<p>Desde aquí se crea y se aloja una web de boda. La venta y el cobro los hace <?= $V ?> (<?= h($L['vendedor_entidad'] ?? '') ?>) como vendedor final; el servicio lo prestamos nosotros. Las condiciones están en las <a href="<?= BASE_PATH ?>/condiciones">condiciones del servicio</a> y el uso de los datos, en la <a href="<?= BASE_PATH ?>/privacidad">política de privacidad</a>.</p>

<h2>Contenido de las webs de boda</h2>
<p>Los textos y la foto de cada web de boda los escribe y sube la pareja que la contrata, y es ella quien responde de ellos. <?= $M ?> solo los aloja. Si alguien nos avisa de que una web tiene contenido ilegal o que vulnera derechos de otra persona, lo revisaremos y, si es así, lo retiraremos o bloquearemos el acceso sin demora.</p>
<p>Para avisarnos, escribe a <?= email_enlace($E['email']) ?> indicando la dirección de la web y qué contenido es.</p>

<h2>Propiedad intelectual</h2>
<p>El diseño, el código y los textos propios del creador y de la plantilla de boda son de <?= $E['nif'] !== '' ? h($E['titular']) : 'quien presta el servicio con la marca ' . $M ?>. No se pueden copiar, vender ni distribuir sin permiso, salvo lo que permita la licencia del ZIP descrita en las condiciones del servicio.</p>

<h2>Enlaces a otras webs</h2>
<p>El pago se hace en la página de <?= $V ?>, que es suya y se rige por sus propias <a href="<?= h($L['vendedor_terminos'] ?? '') ?>" rel="noopener">condiciones de compra</a> y su <a href="<?= h($L['vendedor_privacidad'] ?? '') ?>" rel="noopener">política de privacidad</a>.</p>
