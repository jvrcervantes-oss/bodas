<?php
// Politica de privacidad del creador y de la compra (AxisWorks como responsable).
// Version 2026-09-25. Se incluye dentro de un <main> ya maquetado. $E lo define la app.
?>
<h1>Política de privacidad</h1>
<p>Versión del 25 de septiembre de 2026. Explica qué datos tratamos cuando usáis el creador de webs de boda y cuando compráis una web.</p>
<p>Los datos de los invitados que responden en cada web de boda no los trata AxisWorks por su cuenta: los trata por encargo de cada pareja. Cada web tiene su propio aviso de privacidad para invitados.</p>

<h2>Responsable</h2>
<ul>
  <li><?= h($E['titular']) ?> (nombre comercial AxisWorks)</li>
  <li>NIF: <?= h($E['nif']) ?></li>
  <li>Domicilio: <?= h($E['domicilio']) ?></li>
  <li>Email: <?= h($E['email']) ?></li>
</ul>

<h2>Mientras montáis la web en el creador</h2>
<p>El borrador (textos, fechas, lugares, foto) se guarda solo en el almacenamiento local de vuestro navegador. No nos llega nada hasta que pagáis. Si borráis los datos del navegador o usáis otro dispositivo, el borrador se pierde.</p>

<h2>Qué datos tratamos al comprar y para qué</h2>
<ul>
  <li>Datos de compra: nombre, email y dirección de facturación, que recoge Stripe en su página de pago y nos pasa. Los usamos para cobrar, enviaros la factura, crear la web y enviaros el enlace para elegir la contraseña del panel. Base legal: la ejecución del contrato. Los datos de la tarjeta los trata solo Stripe; nosotros no los vemos.</li>
  <li>Contenido de la web: vuestros nombres, la fecha y los lugares de la boda, los textos, la foto y el email de contacto que indiquéis. Los usamos para publicar la web. Base legal: la ejecución del contrato. Tened en cuenta que este contenido es visible para cualquiera que tenga el enlace de la web, y que el email de contacto aparece en el aviso de privacidad para vuestros invitados.</li>
  <li>Factura: los datos que exige la ley para emitirla y guardarla. Base legal: obligación legal.</li>
  <li>Mensajes que nos enviéis: para responderos. Base legal: la ejecución del contrato o, si no sois clientes, nuestro interés legítimo en atender a quien nos escribe.</li>
</ul>
<p>No usamos vuestros datos para enviaros publicidad, no hacemos perfiles y no tomamos decisiones automatizadas sobre vosotros.</p>

<h2>Cuánto tiempo los guardamos</h2>
<ul>
  <li>Contenido de la web: mientras la web esté publicada. Cuando la web pasa a la página de agradecimiento (<?= (int) MESES_ALOJAMIENTO ?> meses después de la boda), podéis pedirnos que la borremos entera.</li>
  <li>Datos de compra y factura: el tiempo que nos obliga la ley fiscal y mercantil, hasta 6 años.</li>
  <li>Mensajes: el tiempo necesario para resolver lo que nos planteéis y, después, mientras puedan surgir reclamaciones.</li>
</ul>

<h2>Quién más los recibe</h2>
<ul>
  <li>Hostinger, que aloja las webs y envía nuestros emails, como encargado del tratamiento.</li>
  <li>Stripe, que procesa el pago. Stripe trata además algunos datos por su cuenta (por ejemplo, para prevenir el fraude y cumplir sus obligaciones legales), según su propia política de privacidad. Stripe puede tratar datos fuera de la Unión Europea con las garantías que exige el RGPD.</li>
  <li>La Administración tributaria, cuando la ley nos obliga.</li>
</ul>
<p>No vendemos ni cedemos vuestros datos a nadie más.</p>

<h2>Cookies y almacenamiento en el navegador</h2>
<p>No usamos analítica, publicidad ni cookies de terceros en nuestras páginas.</p>
<ul>
  <li>Cookie de sesión del panel: se crea al entrar en el panel privado para mantener la sesión abierta. Es técnica y necesaria para el servicio que pedís, por eso no requiere consentimiento.</li>
  <li>Almacenamiento local del creador: guarda el borrador en vuestro navegador mientras lo montáis. También es necesario para el servicio que pedís. Podéis borrarlo desde la configuración del navegador.</li>
</ul>
<p>La página de pago es de Stripe y usa sus propias cookies, que explica su política.</p>

<h2>Vuestros derechos</h2>
<p>Podéis pedirnos ver vuestros datos, corregirlos, borrarlos, limitar su uso, oponeros a su tratamiento o recibirlos en un formato que podáis llevar a otro sitio. Escribid a <?= h($E['email']) ?>. Os responderemos en un mes como máximo.</p>
<p>Si creéis que no hemos tratado bien vuestros datos, podéis reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>
