<?php
// Privacidad para invitados de una boda (segunda capa). Version 2026-09-25b (libro y galería, rev. previa #87).
// Se incluye dentro de un <main> ya maquetado. $b (datos de la boda) y $E los define la app.
$pareja = $b['nombre1'] . ' y ' . $b['nombre2'];
?>
<h1>Privacidad para invitados</h1>
<p>Este aviso explica qué pasa con los datos que das al confirmar tu asistencia, pedir canciones o escribir en el libro de invitados de esta web, y con las fotos que se publican en ella.</p>

<h2>Quién usa tus datos</h2>
<p>Los datos los recogen y los usan <?= h($pareja) ?>, para organizar su boda. Son los responsables. Contacto: <?= h($b['email']) ?>.</p>
<p>La web la aloja AxisWorks (<?= h($E['titular']) ?>), que guarda los datos por encargo de <?= h($pareja) ?> y no los usa para nada propio.</p>

<h2>Qué datos y para qué</h2>
<ul>
  <li>De cada persona del grupo: nombre, si es adulto o niño, y menú. Sirven para saber cuántos seréis y encargar la comida.</li>
  <li>Alergias o intolerancias: son datos de salud. Solo se recogen si das tu consentimiento explícito en el formulario, y solo se usan para adaptar el menú. Pueden pasarse al servicio de catering para ese fin.</li>
  <li>Del grupo: si asistís, si usáis el autobús y un dato de contacto. Sirven para organizar el transporte y poder avisaros si hay cambios.</li>
  <li>Canciones que pides o votas: para preparar la música de la fiesta.</li>
  <li>Libro de invitados: tu nombre, tu mensaje y, si la subes, una foto. Se publican al momento en la página del libro y los puede ver cualquiera que tenga el enlace y el código de la boda. Quitamos de la foto los datos internos (como el lugar donde se hizo). Junto a cada mensaje guardamos la dirección IP desde la que se envió, solo para poder atender un aviso de abuso; no se muestra a nadie y se borra con el resto.</li>
  <li>Galería: fotos que suben <?= h($pareja) ?>, en las que puedes aparecer. Las ve cualquiera que tenga el enlace y el código de la boda.</li>
  <li>Para evitar abusos, guardamos durante un día como máximo un código derivado de tu dirección IP. No se usa para nada más y se borra solo.</li>
  <li>Lista de invitados: <?= h($pareja) ?> pueden haber anotado tu nombre y un grupo (por ejemplo, «familia de la novia») en una lista privada, solo para ver quién falta por contestar. Nadie más la ve y se borra con el resto de los datos de la boda.</li>
</ul>
<p>Base legal: el consentimiento que das al enviar el formulario, que en el caso de las alergias es explícito. Puedes retirarlo cuando quieras escribiendo a <?= h($b['email']) ?>; lo que ya se hizo antes sigue siendo válido. Si quieres que se retire un mensaje o una foto tuya del libro o de la galería, escribe a <?= h($b['email']) ?> y se quitará.</p>

<h2>Si respondes por otras personas</h2>
<p>Si das datos de otras personas de tu grupo, confirmas que se lo has contado y que están de acuerdo, o que eres su padre, madre o tutor si son menores. Enséñales este aviso.</p>

<h2>Menores</h2>
<p>Si tienes menos de 14 años, pide a tu padre, madre o tutor que escriba en el libro por ti. No subas fotos en las que salgan niños si no eres su padre, madre o tutor o no tienes su permiso.</p>

<h2>Cuánto tiempo se guardan</h2>
<p>Hasta el <?= h($b['borrado']) ?>. Ese día se borran automáticamente todas las respuestas, canciones, mensajes y fotos de esta web.</p>
<p><?= h($pareja) ?> pueden haber descargado antes una copia en Excel para organizar la boda. Esa copia la guardan y la borran ellos.</p>

<h2>Quién más los ve</h2>
<p>Las respuestas de asistencia y las canciones solo las ven <?= h($pareja) ?>, desde su panel privado con contraseña, y quienes les ayuden a organizar la boda (por ejemplo, el catering) en lo que necesiten. Los mensajes y fotos del libro y la galería los ve cualquiera que tenga el enlace y el código de la boda; la web no aparece en buscadores. AxisWorks usa a Hostinger para alojar la web. Tus datos no se venden ni se ceden a nadie más.</p>
<p>Esta web no usa analítica. Solo usa una cookie técnica: si escribes el código de la boda para ver la galería o el libro, lo recuerda durante 60 días para no pedírtelo cada vez.</p>
<p>El mapa de la ceremonia y el convite es una imagen que hace AxisWorks con datos de OpenStreetMap: al verlo, tu navegador no se conecta con nadie más. Para situar los sitios, la dirección del lugar (no la tuya) se consulta una vez en el buscador de OpenStreetMap (Fundación OpenStreetMap, Reino Unido). Los botones «Ver mapa» abren Google Maps y, desde ese momento, se aplica la política de privacidad de Google.</p>

<h2>Tus derechos</h2>
<p>Puedes pedir ver tus datos, corregirlos, borrarlos, limitar su uso, oponerte o recibirlos en un formato que puedas llevar a otro sitio. Escribe a <?= h($b['email']) ?>. Si no obtienes respuesta, puedes escribir a AxisWorks (<?= h($E['email']) ?>), que avisará a <?= h($pareja) ?> y les ayudará a atenderte.</p>
<p>También puedes reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>
