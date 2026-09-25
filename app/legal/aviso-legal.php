<?php
// Aviso legal (art. 10 LSSI). Version 2026-09-25.
// Se incluye dentro de un <main> ya maquetado. $E lo define la app.
?>
<h1>Aviso legal</h1>

<h2>Quién está detrás de esta web</h2>
<p>AxisWorks es el nombre comercial con el que <?= h($E['titular']) ?> ofrece el creador de webs de boda de axisworks.studio y las webs que se publican en sus subdominios.</p>
<ul>
  <li>Titular: <?= h($E['titular']) ?></li>
  <li>NIF: <?= h($E['nif']) ?></li>
  <li>Domicilio: <?= h($E['domicilio']) ?></li>
  <li>Email: <?= h($E['email']) ?></li>
</ul>

<h2>Para qué sirve esta web</h2>
<p>Desde aquí se crea, se paga y se aloja una web de boda. Las condiciones de la compra están en las <a href="/condiciones">condiciones de contratación</a> y el uso de los datos, en la <a href="/privacidad">política de privacidad</a>.</p>

<h2>Contenido de las webs de boda</h2>
<p>Los textos y la foto de cada web de boda los escribe y sube la pareja que la contrata, y es ella quien responde de ellos. AxisWorks solo los aloja. Si alguien nos avisa de que una web tiene contenido ilegal o que vulnera derechos de otra persona, lo revisaremos y, si es así, lo retiraremos o bloquearemos el acceso sin demora.</p>
<p>Para avisarnos, escribe a <?= h($E['email']) ?> indicando la dirección de la web y qué contenido es.</p>

<h2>Propiedad intelectual</h2>
<p>El diseño, el código y los textos propios del creador y de la plantilla de boda son de <?= h($E['titular']) ?>. No se pueden copiar, vender ni distribuir sin permiso, salvo lo que permita la licencia del ZIP descrita en las condiciones de contratación.</p>

<h2>Enlaces a otras webs</h2>
<p>El pago se hace en la página de Stripe, que es de Stripe y se rige por sus propias condiciones y su política de privacidad.</p>
