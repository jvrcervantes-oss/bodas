<?php
// Política de privacidad del creador y de la compra (el titular como responsable).
// Versión 2026-09-27 (Legal): Lemon Squeezy (LS) es el vendedor y trata los datos de compra como
// responsable por su cuenta; Cloudflare, declarado (el dominio y las bodas pasan por su red).
// El beacon de Web Analytics de Cloudflare NO se declara: se desactiva en la zona (BOD-15).
// Se incluye dentro de un <main> ya maquetado. $E lo define la app.
$L = textos_legales();
$M = h(marca());
$V = h($L['vendedor'] ?? '');
?>
<h1>Política de privacidad</h1>
<p>Versión del 27 de septiembre de 2026. Explica qué datos tratamos cuando usáis el creador de webs de boda de <?= $M ?> y cuando compráis una web.</p>
<p>Los datos de los invitados que responden en cada web de boda no los tratamos por nuestra cuenta: los tratamos por encargo de cada pareja. Cada web tiene su propio aviso de privacidad para invitados.</p>

<h2>Responsable</h2>
<?php if (titular_identificado($E)): ?>
<ul>
  <li><?= h($E['titular']) ?>, que presta el servicio con la marca <?= $M ?>, un producto de AxisWorks, su nombre comercial</li>
<?php if ($E['nif'] !== ''): ?>  <li>NIF: <?= h($E['nif']) ?></li>
<?php endif; if ($E['domicilio'] !== ''): ?>  <li>Domicilio: <?= h($E['domicilio']) ?></li>
<?php endif; ?>  <li>Email: <?= email_enlace($E['email']) ?></li>
</ul>
<?php else: ?>
<p>Quien presta el servicio con la marca <?= $M ?>, un producto de AxisWorks. El servicio está en pruebas y todavía no se vende: su nombre, NIF y domicilio se publicarán aquí antes de abrir la venta.</p>
<ul>
  <li>Email: <?= email_enlace($E['email']) ?></li>
</ul>
<?php endif; ?>

<h2>Mientras montáis la web en el creador</h2>
<p>El borrador (textos, fechas, lugares, foto) se guarda en el almacenamiento local de vuestro navegador, salvo que pulséis «Seguir en otro dispositivo». No nos llega nada más hasta que publicáis. Si borráis los datos del navegador, el borrador que solo esté ahí se pierde.</p>
<p>Si pulsáis «Seguir en otro dispositivo», guardamos en nuestro servidor una copia del borrador (con la foto, si la hay) bajo un enlace secreto, para que lo abráis en otro móvil u ordenador. Quien tenga el enlace puede verlo. La base legal son las medidas precontractuales que nos pedís (art. 6.1.b del RGPD). La guardamos 30 días y después se borra sola, también la foto; podéis borrarla antes desde el mismo aviso. El email que haya en el borrador no lo usamos para recordatorios ni publicidad.</p>

<h2>Qué datos tratamos al comprar y para qué</h2>
<ul>
  <li>Datos del pedido: el email que ponéis en el creador y el de la página de pago, el número de pedido de <?= $V ?>, el pack, el importe y el país. <?= $V ?> nos los pasa cuando el pago se confirma. Los usamos para crear la web, enviaros el enlace para elegir la contraseña del panel y atenderos. Base legal: la ejecución del contrato. Los datos de pago y la dirección de facturación los trata <?= $V ?>; nosotros no vemos los datos de la tarjeta.</li>
  <li>Registro de ventas: número de pedido, fecha, pack, importe y lo que nos liquida <?= $V ?>, para nuestra contabilidad y nuestras obligaciones fiscales. Base legal: obligación legal.</li>
  <li>Prueba de lo que aceptasteis: el texto de las dos casillas que marcasteis antes de pagar, con su fecha y la versión de las condiciones. Base legal: la ejecución del contrato y nuestra obligación de poder demostrar vuestro consentimiento a la ejecución inmediata.</li>
  <li>Contenido de la web: vuestros nombres, la fecha y los lugares de la boda, los textos, la foto y el email de contacto que indiquéis. Los usamos para publicar la web. Base legal: la ejecución del contrato. Tened en cuenta que este contenido es visible para cualquiera que tenga el enlace de la web, y que el email de contacto aparece en el aviso de privacidad para vuestros invitados.</li>
  <li>Mensajes que nos enviéis: para responderos. Base legal: la ejecución del contrato o, si no sois clientes, nuestro interés legítimo en atender a quien nos escribe.</li>
  <li>Gestión interna del servicio y atención al cliente: vuestros nombres, email, pedido y estado de vuestra web los ve solo el titular, en un panel interno, para atenderos y llevar el servicio. Base legal: la ejecución del contrato.</li>
  <li>Códigos de regalo: si la web os la regalamos, guardamos el código usado y una nota interna mínima, sin apellidos ni datos de contacto. Base legal: nuestro interés legítimo en gestionar el regalo; al publicar la web pasáis a ser clientes como cualquier otra pareja.</li>
</ul>
<p>No usamos vuestros datos para enviaros publicidad, no hacemos perfiles y no tomamos decisiones automatizadas sobre vosotros.</p>

<h2>Cuánto tiempo los guardamos</h2>
<ul>
  <li>Contenido de la web: mientras la web esté publicada. Cuando la web pasa a la página de agradecimiento (<?= (int) MESES_ALOJAMIENTO ?> meses después de la boda), podéis pedirnos que la borremos entera.</li>
  <li>Datos del pedido, registro de ventas y prueba de lo aceptado: el tiempo que nos obliga la ley fiscal y mercantil, hasta 6 años.</li>
  <li>Mensajes: el tiempo necesario para resolver lo que nos planteéis y, después, mientras puedan surgir reclamaciones.</li>
</ul>

<h2><?= $V ?>, el vendedor</h2>
<p>La compra se la hacéis a <?= $V ?> (<?= h($L['vendedor_entidad'] ?? '') ?>). En su página de pago recoge vuestro nombre, email, dirección de facturación y los datos de pago, y los trata <strong>como responsable por su cuenta</strong>: para cobraros, emitir el recibo y la factura, liquidar el IVA, prevenir el fraude y cumplir sus propias obligaciones legales, según <a href="<?= h($L['vendedor_privacidad'] ?? '') ?>" rel="noopener">su política de privacidad</a>. Para ejercer vuestros derechos sobre esos datos, dirigíos a <?= $V ?>.</p>
<p>Nosotros le pasamos el email que ponéis en el creador, para rellenar su formulario, y un código interno del pedido. Lo poco que <?= $V ?> hace por encargo nuestro, como guardarnos la lista de pedidos en su panel, lo hace como encargado del tratamiento bajo su acuerdo de tratamiento. Está en Estados Unidos: la transferencia se ampara en las cláusulas contractuales tipo de la Comisión Europea que recoge ese acuerdo.</p>

<h2>Quién más los recibe</h2>
<ul>
  <li>Hostinger, que aloja las webs y el buzón desde el que os escribimos, como encargado del tratamiento.</li>
  <li>Cloudflare, Inc., como encargado del tratamiento: su red recibe las visitas a nuestras páginas y a las webs de boda (con vuestra dirección IP) y las entrega cifradas a nuestro alojamiento. Está en Estados Unidos; la transferencia se ampara en las cláusulas contractuales tipo de la Comisión Europea que recoge su acuerdo de tratamiento. No usamos sus funciones de analítica.</li>
  <li><?= $V ?>, como se explica en el apartado anterior.</li>
  <li>La Administración tributaria, cuando la ley nos obliga.</li>
</ul>
<p>No vendemos ni cedemos vuestros datos a nadie más.</p>

<h2>Cookies y almacenamiento en el navegador</h2>
<p>No usamos cookies de analítica ni de publicidad. Medimos las visitas sin cookies, como se explica abajo.</p>
<ul>
  <li>Cookie de sesión del panel: se crea al entrar en el panel privado para mantener la sesión abierta. Es técnica y necesaria para el servicio que pedís, por eso no requiere consentimiento.</li>
  <li>Almacenamiento local del creador: guarda el borrador en vuestro navegador mientras lo montáis (salvo la copia que decidáis pasar a otro dispositivo, explicada arriba). También es necesario para el servicio que pedís. Podéis borrarlo desde la configuración del navegador.</li>
</ul>
<p>La página de pago es de <?= $V ?> y usa sus propias cookies, que explica su política.</p>

<h2>Medición de visitas sin cookies</h2>
<p>En las páginas de presentación del servicio (no en las webs de boda) contamos las visitas para saber qué páginas funcionan. Lo hacemos en nuestro servidor, sin cookies ni nada que se guarde o se lea en vuestro dispositivo:</p>
<ul>
  <li>Usamos vuestra dirección IP y el tipo de navegador solo un instante, para calcular una huella cifrada con una clave que cambia cada día. La clave y las huellas se destruyen al terminar ese día. No guardamos la IP.</li>
  <li>Lo único que queda son contadores agregados por página, origen de la visita, campaña y tipo de dispositivo. Los guardamos 90 días.</li>
  <li>No se cede a nadie y no se cruza con compras, correos ni ningún otro dato.</li>
  <li>La base legal es nuestro interés legítimo en saber qué funciona de nuestra web. Podéis oponeros: si vuestro navegador envía la señal «Global Privacy Control» o «Do Not Track», no os contamos.</li>
</ul>
<p>Además, como cualquier web, los servidores de nuestro alojamiento y de Cloudflare registran las peticiones (IP y hora) por seguridad y para el funcionamiento técnico.</p>

<h2>Vuestros derechos</h2>
<p>Podéis pedirnos ver vuestros datos, corregirlos, borrarlos, limitar su uso, oponeros a su tratamiento o recibirlos en un formato que podáis llevar a otro sitio. Escribid a <?= email_enlace($E['email']) ?>. Os responderemos en un mes como máximo.</p>
<p>Si creéis que no hemos tratado bien vuestros datos, podéis reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>
