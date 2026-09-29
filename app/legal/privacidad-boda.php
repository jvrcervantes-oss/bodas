<?php
// Privacidad para invitados de una boda (segunda capa). Versión 2026-09-27d (Legal: enlace personal por grupo, fecha de
// primer acceso sin IP y resumen impreso para el catering — F1a/F1b; la «d», plano de mesas con la copia del nombre y la
// hoja para el restaurante — F1d, revisión #133).
// Se incluye dentro de un <main> ya maquetado. $b (datos de la boda) y $E los define la app.
$pareja = $b['nombre1'] . ' y ' . $b['nombre2'];
$M = h(marca());
?>
<h1>Privacidad para invitados</h1>
<p>Este aviso explica qué pasa con los datos que das al confirmar tu asistencia, pedir canciones o escribir en el libro de invitados de esta web, y con las fotos que se publican en ella.</p>

<h2>Quién usa tus datos</h2>
<p>Los datos los recogen y los usan <?= h($pareja) ?>, para organizar su boda. Son los responsables. Contacto: <?= email_enlace($b['email']) ?>.</p>
<p>La web la aloja <?= $M ?>, un producto de AxisWorks<?= $E['titular'] !== marca() ? ' (' . h($E['titular']) . ')' : '' ?>, que guarda los datos por encargo de <?= h($pareja) ?> y no los usa para nada propio.</p>

<h2>Qué datos y para qué</h2>
<ul>
  <li>De cada persona del grupo: nombre, si es adulto o niño, y menú. Sirven para saber cuántos seréis y encargar la comida.</li>
  <li>Alergias o intolerancias: son datos de salud. Solo se recogen si das tu consentimiento explícito en el formulario, y solo se usan para adaptar el menú. Para eso se pasan al catering, con tu nombre, tu menú y, si hay plano de mesas, tu mesa.</li>
  <li>Del grupo: si asistís, si usáis el autobús y un dato de contacto. Sirven para organizar el transporte y poder avisaros si hay cambios.</li>
  <li>Canciones que pides o votas: para preparar la música de la fiesta.</li>
  <li>Libro de invitados: tu nombre, tu mensaje y, si la subes, una foto. Se publican al momento en la página del libro y los puede ver cualquiera que tenga el enlace y el código de la boda. Quitamos de la foto los datos internos (como el lugar donde se hizo). Junto a cada mensaje guardamos la dirección IP desde la que se envió, solo para poder atender un aviso de abuso; no se muestra a nadie y se borra con el resto.</li>
  <li>Galería: fotos que suben <?= h($pareja) ?>, en las que puedes aparecer. Las ve cualquiera que tenga el enlace y el código de la boda.</li>
  <li>Para evitar abusos, guardamos durante un día como máximo un código derivado de tu dirección IP. No se usa para nada más y se borra solo.</li>
  <li>Lista de invitados y enlace personal: <?= h($pareja) ?> pueden haber anotado tu nombre y el de tu grupo (por ejemplo, «Familia García») en una lista privada, para ver quién falta por contestar y enviar a cada grupo su propio enlace. Quien abre ese enlace ve el nombre del grupo y los nombres de sus personas tal como los escribieron <?= h($pareja) ?>, para no tener que escribirlos; nunca ve las respuestas ya enviadas, las alergias ni los datos de contacto. La primera vez que se abre el enlace guardamos solo la fecha y la hora, para que <?= h($pareja) ?> sepan que ha llegado; no guardamos tu dirección IP ni tu navegador, y es un dato orientativo. Cualquiera que tenga el enlace puede responder por el grupo: no lo reenvíes fuera de él. Si respondéis otra vez por el mismo enlace, la respuesta nueva sustituye a la anterior; la anterior no se muestra a nadie y se borra con el resto de los datos de la boda.</li>
  <li>Mesa: si <?= h($pareja) ?> hacen un plano de mesas, la mesa que te asignen. Sirve para colocaros y para que el catering sepa dónde servir cada menú. Al sentarte se guarda también una copia de tu nombre, solo para avisar a <?= h($pareja) ?> si dejas de venir o cambias tu respuesta; se quita cuando te quitan del plano y, si no, se borra con el resto.</li>
</ul>
<p>Base legal: el consentimiento que das al enviar el formulario, que en el caso de las alergias es explícito. Puedes retirarlo cuando quieras escribiendo a <?= email_enlace($b['email']) ?>; lo que ya se hizo antes sigue siendo válido. Si quieres que se retire un mensaje o una foto tuya del libro o de la galería, escribe a <?= email_enlace($b['email']) ?> y se quitará.</p>

<h2>Si respondes por otras personas</h2>
<p>Si das datos de otras personas de tu grupo, confirmas que se lo has contado y que están de acuerdo, o que eres su padre, madre o tutor si son menores. Enséñales este aviso.</p>

<h2>Menores</h2>
<p>Si tienes menos de 14 años, pide a tu padre, madre o tutor que escriba en el libro por ti. No subas fotos en las que salgan niños si no eres su padre, madre o tutor o no tienes su permiso.</p>

<h2>Cuánto tiempo se guardan</h2>
<p>Hasta el <?= h($b['borrado']) ?>. Ese día se borran automáticamente todas las respuestas, canciones, mensajes y fotos de esta web.</p>
<p><?= h($pareja) ?> pueden haber descargado antes una copia en Excel, o impreso o guardado en PDF el resumen para el catering o la hoja del plano de mesas. Esas copias las guardan ellos y el catering, y las tienen que borrar o destruir cuando ya no hagan falta.</p>

<h2>Quién más los ve</h2>
<p>Las respuestas de asistencia y las canciones solo las ven <?= h($pareja) ?>, desde su panel privado con contraseña, y quienes les ayuden a organizar la boda en lo que necesiten. Para el catering, <?= h($pareja) ?> pueden imprimir o guardar en PDF un resumen con cuántos menús hay de cada tipo y, de cada persona con alergias o intolerancias, su nombre, su menú, la alergia y, si hay plano de mesas, su mesa. Si hacen un plano de mesas, pueden imprimir también una hoja para el restaurante con cada mesa y, de cada persona sentada en ella, su nombre, su menú y sus alergias o intolerancias. Esas hojas las reciben solo <?= h($pareja) ?> y el catering, que las usa para servir la comida. Los mensajes y fotos del libro y la galería los ve cualquiera que tenga el enlace y el código de la boda; la web no aparece en buscadores. <?= $M ?> usa a Hostinger para alojar la web y a Cloudflare, Inc. (Estados Unidos) para entregarla: su red recibe tu conexión, con tu dirección IP y lo que envías, y la pasa cifrada al alojamiento, con las cláusulas contractuales tipo de la Comisión Europea como garantía. Tus datos no se venden ni se ceden a nadie más.</p>
<p>Esta web no usa analítica. El enlace personal de tu grupo no guarda ninguna cookie. Solo usa una cookie técnica: si escribes el código de la boda para ver la galería o el libro, lo recuerda durante 60 días para no pedírtelo cada vez.</p>
<p>El mapa de la ceremonia y el convite es una imagen que hace <?= $M ?> con datos de OpenStreetMap: al verlo, tu navegador no se conecta con nadie más. Para situar los sitios, la dirección del lugar (no la tuya) se consulta una vez en el buscador de OpenStreetMap (Fundación OpenStreetMap, Reino Unido). Los botones «Ver mapa» abren Google Maps y, desde ese momento, se aplica la política de privacidad de Google.</p>

<h2>Tus derechos</h2>
<p>Puedes pedir ver tus datos, corregirlos, borrarlos, limitar su uso, oponerte o recibirlos en un formato que puedas llevar a otro sitio. Escribe a <?= email_enlace($b['email']) ?>. Si no obtienes respuesta, puedes escribir a <?= $M ?> (<?= email_enlace($E['email']) ?>), que avisará a <?= h($pareja) ?> y les ayudará a atenderte.</p>
<p>También puedes reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>
