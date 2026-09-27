<?php
// Condiciones del servicio + Anexo de encargo de tratamiento (art. 28 RGPD).
// Versión 2026-09-27d (Legal; la «c» añadió el enlace personal por grupo y el resumen para el catering, F1a/F1b; la «d»
// añade los extras de pago y el plano de mesas, F1c/F1d, revisión #133: §2, §5 ter, §7, §8, §13, anexo II.2 y II.6).
// §5 ter nombra SOLO los extras que están a la venta (EXTRAS[...]['venta']): uno que aún no se vende no tiene precio
// que garantizar. Al abrir la venta de otro (álbum, idiomas, dominio), Legal lo añade aquí y sube la versión. Lemon Squeezy (LS) es el vendedor (Merchant of Record) y nosotros prestamos
// el servicio. Owner (27-sep): «no se puede pedir reembolso», hasta donde la ley lo permite: el desistimiento
// se pierde en la creación y publicación (103.m), pero el alojamiento es servicio (103.a) y se puede desistir en
// 14 días pagando lo prestado (108.3); fuera de eso, solo las devoluciones legales (consulta fresca de Legal).
// Si el owner fija el reparto del precio creación/alojamiento, se escribe aquí con precio_*_cent(). Se incluye dentro de un <main> ya maquetado y en el cuerpo del correo de
// bienvenida (legal_a_texto): nada de maquetación que no sea h1/h2/p/ul/li/a. $E lo define la app.
// Precio y marca SIEMPRE por sus funciones; el vendedor, por textos.php.
$L = textos_legales();
$M = h(marca());
$V = h($L['vendedor'] ?? '');
?>
<h1>Condiciones del servicio</h1>
<p>Versión 2026-09-27d, del 27 de septiembre de 2026. Se aplica la versión vigente el día de vuestra compra, que es la que os enviamos en el correo de bienvenida.</p>

<h2>1. Quiénes somos y quién os vende</h2>
<p><?= $M ?> es un producto de AxisWorks. <?php if ($E['nif'] !== ''): ?>AxisWorks es el nombre comercial de <?= h($E['titular']) ?>, con NIF <?= h($E['nif']) ?> y domicilio en <?= h($E['domicilio']) ?>.<?php else: ?>El servicio está en pruebas y todavía no se vende: los datos de quien lo presta (nombre, NIF y domicilio) se publicarán aquí antes de abrir la venta.<?php endif; ?> En estas condiciones, «nosotros» es <?= $E['nif'] !== '' ? h($E['titular']) : 'quien presta el servicio con la marca ' . $M ?>. Para cualquier duda, incidencia o reclamación: <?= h($E['email']) ?>.</p>
<p>La compra se la hacéis a <?= $V ?> (<?= h($L['vendedor_entidad'] ?? '') ?>), que actúa como vendedor final (en inglés, <em>Merchant of Record</em>): revende nuestro servicio, os cobra, os envía el recibo y la factura y liquida el IVA. Esa compra se rige por las <a href="<?= h($L['vendedor_terminos'] ?? '') ?>" rel="noopener">condiciones de compra de <?= $V ?></a>.</p>
<p>El servicio, es decir, crear, publicar y alojar vuestra web, lo prestamos nosotros y se rige por estas condiciones. Para cualquier cosa sobre la web podéis dirigiros siempre a nosotros. Nada de lo que digan las condiciones de <?= $V ?> ni estas limita los derechos que la ley os reconoce como consumidores.</p>

<h2>2. Qué compráis</h2>
<p>Una web para vuestra boda, creada con nuestro creador y publicada en https://vuestro-nombre.<?= h(BASE_DOMAIN) ?>. Incluye:</p>
<ul>
  <li>La web con las secciones, textos, fechas, lugares y foto que elijáis en el creador.</li>
  <li>Un formulario de confirmación de asistencia por grupo. Por cada invitado: nombre, si es adulto o niño, menú y alergias. Por cada grupo: si asiste, si usa el autobús, un dato de contacto y una canción.</li>
  <li>Peticiones y votos de canciones.</li>
  <li>Un panel privado con contraseña para ver las respuestas, exportarlas a Excel, editar la web y descargar un ZIP.</li>
  <li>Una lista de invitados privada en el panel, con un enlace personal para cada grupo que podéis copiar o enviar por WhatsApp. Quien lo abre ve el nombre del grupo y los nombres de sus personas tal como los escribisteis, y confirma por todo el grupo. En el panel veis si cada enlace se ha abierto y podéis cambiarlo por uno nuevo cuando queráis.</li>
  <li>Un resumen para el catering, que imprimís o guardáis en PDF desde el panel: cuántos menús hay de cada tipo y, de cada persona con alergias o intolerancias, su nombre, su menú, la alergia y, si tenéis el plano de mesas, su mesa.</li>
  <li>Si las activáis: una galería de hasta 24 fotos que subís desde el panel, y un libro de invitados donde quien tenga el enlace y el código de la boda puede dejar su nombre, un mensaje y una foto. Ambas van detrás de un código de acceso que elegís vosotros.</li>
  <li>Alojamiento de la web hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la fecha de la boda (apartado 7).</li>
</ul>
<p>El enlace personal por grupo y el resumen para el catering van incluidos en los dos packs. Además, desde el panel podéis comprar extras sueltos, que se pagan aparte (apartado 5 ter).</p>
<p>El ZIP contiene el HTML estático de vuestra web y las fotos de la galería, sin datos de invitados ni el libro de invitados. Es una copia de lo que se ve, no de lo que funciona: fuera de nuestro alojamiento, el formulario de asistencia, las canciones y el panel no funcionan. Se abre en cualquier navegador actual.</p>
<p>Las webs de boda no aparecen en buscadores: están marcadas para que Google y similares no las indexen. Cualquiera que tenga el enlace puede verlas.</p>

<h2>3. Quién puede comprar</h2>
<p>Personas mayores de 18 años que compran para su propia boda, como consumidores.</p>

<h2>4. Cómo se contrata</h2>
<ul>
  <li>Montáis la web en el creador. El borrador se guarda en vuestro navegador; nosotros no lo recibimos hasta que pagáis, salvo que pulséis «Seguir en otro dispositivo» (os guardamos una copia 30 días, como explica la política de privacidad).</li>
  <li>Revisáis la vista previa. Hasta pulsar el botón de pago podéis cambiar cualquier dato o corregir errores.</li>
  <li>Marcáis dos casillas: en la primera aceptáis estas condiciones y el encargo de tratamiento (anexo II), sabiendo que quien os vende es <?= $V ?>; en la segunda pedís que la web se cree y se publique ya (apartado 8).</li>
  <li>Pagáis en la página de <?= $V ?>, donde dais vuestro nombre, vuestro email y vuestra dirección de facturación. Al confirmarse el pago, la web se publica al momento.</li>
  <li><?= $V ?> os envía por email el recibo del pago. Nosotros os enviamos otro email con la dirección de la web, un enlace de un solo uso para elegir la contraseña del panel, el texto exacto de las dos casillas que marcasteis, con su fecha, y el texto completo de estas condiciones.</li>
</ul>
<p>El contrato se celebra en castellano. Guardamos cada versión de estas condiciones con su fecha, y la vuestra os llega entera en el correo de bienvenida.</p>

<h2>5. Precio y pago</h2>
<p>Dos packs, IVA incluido: Esencial, <?= h(euros(precio_esencial_cent())) ?>; y Atelier, con un diseño ilustrado de la colección y sus animaciones, <?= h(euros(precio_atelier_cent())) ?>. Pago único. No hay cuotas ni renovaciones.</p>
<p>Pagáis el precio que os enseña el creador justo antes del botón de pago, que es el mismo que os cobra <?= $V ?>. Lo cobra <?= $V ?> con tarjeta u otro medio que ofrezca en su página; nosotros no vemos ni guardamos los datos de pago. El recibo y la factura los emite <?= $V ?>: nosotros no emitimos factura de estas ventas.</p>

<h2>5 bis. Pasar del Pack Esencial al Atelier</h2>
<p>Desde el panel podéis pasar vuestra web del Pack Esencial al Atelier. La mejora cuesta la diferencia entre los precios vigentes de los dos packs el día que la pagáis, y el panel os enseña el importe, IVA incluido, antes de pagar. Es una compra aparte que también os vende y os cobra <?= $V ?>, con estas mismas condiciones.</p>
<p>Antes de pagarla marcáis una casilla en la que pedís que el Pack Atelier se active ya y reconocéis que, en cuanto se active, perdéis el derecho de desistimiento de la mejora (artículo 103.m de la misma ley): es un diseño que se entrega al momento y no alarga el alojamiento. Os lo confirmamos por email. Por eso, una vez activada, no se puede pedir su reembolso por cambio de opinión, salvo en los casos que exige la ley (apartado 8).</p>
<p>Con el Pack Atelier podéis cambiar de diseño de la colección cuantas veces queráis mientras la web esté alojada. Si volvéis a un estilo del Pack Esencial, no se devuelve nada de la mejora.</p>

<h2>5 ter. Extras</h2>
<p>Con la web ya publicada, podéis añadirle desde el panel servicios sueltos que no van en los packs. Hoy son estos, con su precio, IVA incluido y en pago único:</p>
<ul>
  <li>Plano de mesas, <?= h(euros(precio_extra_cent('mesas'))) ?>: en el panel creáis las mesas del banquete, con su nombre y sus plazas, y sentáis en ellas a quienes han confirmado que van al banquete. El panel os avisa si alguien que habíais sentado deja de venir o vuelve a responder, pero no lo mueve por su cuenta: lo decidís vosotros. Incluye una hoja para el restaurante, que imprimís o guardáis en PDF, con cada mesa y, de cada persona, su nombre, su menú y sus alergias o intolerancias. Con el plano de mesas, el resumen para el catering muestra también la mesa de cada persona con alergias.</li>
</ul>
<p>Si más adelante ofrecemos otros extras, los añadiremos a este apartado antes de ponerlos a la venta.</p>
<p>El panel os enseña qué hace cada extra y su precio antes de pagar, y ese es el importe que os cobra <?= $V ?>. Cada extra es una compra aparte que también os vende y os cobra <?= $V ?>, que os envía el recibo y la factura, igual que en el apartado 5. También se puede comprar para una web de regalo. Antes de pagar marcáis una casilla en la que pedís que el extra se active ya, dentro de los 14 días para desistir, y aceptáis para él estas condiciones; se activa en cuanto <?= $V ?> confirma el pago y os lo confirmamos por email con el texto de la casilla, su fecha y estas condiciones completas.</p>
<p>Un extra es un servicio que os prestamos mientras la web está alojada, así que podéis desistir de él en los 14 días siguientes a su compra, sin dar motivos, escribiéndonos a <?= h($E['email']) ?> (podéis usar el modelo del anexo I). Como pedís que empiece ya, si desistís pagaréis la parte proporcional a lo ya prestado: el precio del extra repartido por días entre el día de la compra y la fecha de borrado de la web (apartado 7), multiplicado por los días transcurridos hasta que nos lo comuniquéis. El resto os lo devuelve <?= $V ?>, por el mismo medio de pago, en un máximo de 14 días. Al desistir, el extra se desactiva y la web sigue publicada y alojada como hasta entonces. Pasados esos 14 días, no se devuelve el extra por cambio de opinión, salvo en los casos del apartado 8.</p>
<p>Cada extra dura lo que dure la web: se puede usar hasta la fecha de borrado del apartado 7 y se apaga con ella, o antes si la web se da de baja. Comprar un extra no alarga el alojamiento. Lo que guardáis con él (por ejemplo, el plano de mesas) se borra con el resto de los datos de la boda.</p>

<h2>6. Plazo de entrega</h2>
<p>La web se publica en cuanto <?= $V ?> confirma el pago, normalmente en segundos. Si pasado un rato no la veis o no os llega el email, escribid a <?= h($E['email']) ?> y lo resolvemos.</p>

<h2>7. Duración y borrado</h2>
<p>La web y las respuestas de los invitados se mantienen hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la fecha de la boda que figure en la web. Ese día, de forma automática:</p>
<ul>
  <li>se borran todas las respuestas de asistencia (nombres, menús, alergias, contactos) y las canciones;</li>
  <li>se borran las fotos de la galería y los mensajes y fotos del libro de invitados;</li>
  <li>se borra la lista de invitados, y los enlaces personales de cada grupo dejan de funcionar;</li>
  <li>se borra el plano de mesas, si lo teníais, y se apagan los extras;</li>
  <li>la web deja de mostrar su contenido y pasa a una página de agradecimiento.</li>
</ul>
<p>Lo borrado no se puede recuperar. Si queréis conservar las respuestas, exportad el Excel y guardad las fotos antes de esa fecha. Podéis pedirnos que borremos antes la web entera escribiendo a <?= h($E['email']) ?>.</p>

<h2>8. Desistimiento y reembolsos</h2>
<p>Como consumidores, la ley os da en general 14 días naturales desde la compra para desistir sin dar motivos. Lo que compráis tiene dos partes, y el desistimiento funciona distinto en cada una.</p>
<p>La primera parte es crear y publicar vuestra web y entregaros el ZIP. Es contenido digital hecho con vuestros datos que se entrega al momento de pagar, a petición vuestra. Por eso, antes de pagar marcáis una casilla en la que nos pedís que la creemos y la publiquemos ya y reconocéis que, en cuanto se publique, perdéis el derecho de desistimiento sobre esta parte (artículo 103.m de la Ley General para la Defensa de los Consumidores y Usuarios).</p>
<p>La segunda parte es el alojamiento de la web, el panel y el formulario de asistencia hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la boda. Sobre esta parte podéis desistir en los 14 días siguientes a la compra. Como pedís que empiece ya, si desistís tendréis que pagar la parte proporcional a lo ya prestado: la creación y publicación de la web, que ya está hecha, y el tiempo de alojamiento transcurrido hasta que nos lo comuniquéis; se os devuelve el resto, por el mismo medio de pago, en un máximo de 14 días. Al desistir del alojamiento, la web se da de baja con el aviso del final de este apartado.</p>
<p>El correo de bienvenida os confirma vuestra petición y vuestro reconocimiento, con su fecha. Pasados esos 14 días, no se devuelve dinero por cambio de opinión.</p>
<p>Los extras del apartado 5 ter son servicios y funcionan como el alojamiento: podéis desistir de cada uno en los 14 días siguientes a su compra, pagando la parte proporcional a lo ya prestado como explica ese apartado.</p>
<p>Además del desistimiento del alojamiento y de los extras, se devuelve dinero en estos casos:</p>
<ul>
  <li>si habéis pagado y la web no llega a publicarse, y no lo resolvemos cuando nos aviséis: se devuelve todo lo pagado;</li>
  <li>si se os cobra dos veces o un importe distinto del que os enseñamos antes de pagar: se devuelve lo cobrado de más;</li>
  <li>si la web no funciona como se describe aquí: según la garantía del apartado 9.</li>
</ul>
<p>Esas devoluciones se hacen a través de <?= $V ?>, por el mismo medio de pago. Nada de este apartado limita los derechos que la ley os reconoce como consumidores. Para desistir basta con decírnoslo por email a <?= h($E['email']) ?>; podéis usar el modelo del anexo I, aunque no es obligatorio.</p>
<p>Si <?= $V ?> devuelve todo lo pagado por la web, o vuestro banco anula el pago y <?= $V ?> confirma la devolución, el servicio queda sin pagar y daremos de baja la web: os avisaremos por email y tendréis 7 días para exportar el Excel y descargar el ZIP; después se borran los datos como en el apartado 7. Mientras un contracargo esté en disputa, podremos dejar la web en pausa, sin borrar nada, hasta que se resuelva. Si lo que se devuelve es solo la mejora al Pack Atelier, la web vuelve al Pack Esencial y sigue alojada. Si desistís del alojamiento, la web se da de baja con el mismo aviso de 7 días.</p>
<p>Si lo que se devuelve es un extra, entero o la parte que corresponda al desistir de él, o si vuestro banco anula el pago de un extra y <?= $V ?> confirma la devolución, ese extra se desactiva y la web sigue publicada y alojada, con todo lo demás igual. Lo que hubierais guardado con el extra (por ejemplo, el plano de mesas) no se borra al desactivarlo: se conserva hasta la fecha de borrado del apartado 7 por si lo volvéis a activar, y se borra entonces con el resto. Si la devolución de un extra es una rebaja por un fallo que no hemos arreglado (apartado 9), el extra sigue activo.</p>

<h2>9. Garantía</h2>
<p>La web tiene que funcionar como se describe en estas condiciones durante todo el tiempo de alojamiento. Si algo no funciona, avisadnos a nosotros, que somos quienes prestamos el servicio, y lo arreglaremos sin coste. El orden es el que marca la ley: primero lo arreglamos; si no lo arreglamos en un plazo razonable, podéis pedir una rebaja proporcional del precio o, si el fallo no es menor, resolver el contrato y recuperar lo pagado en la medida que fija la ley. Si hay que devolver dinero, lo hacemos a través de <?= $V ?>. Es la garantía legal que os da la ley y no la limitamos.</p>

<h2>10. Vuestros textos y vuestras fotos</h2>
<p>Los textos y la foto que ponéis en la web son vuestros y seguís siendo sus titulares. Al subirlos nos dais permiso para alojarlos y mostrarlos en vuestra web mientras dure el servicio, y para nada más.</p>
<p>Al subir las fotos (la de portada y las de la galería) garantizáis que tenéis derecho a usarlas: que la hicisteis vosotros o que el fotógrafo os permite publicarla, y que las personas que aparecen están de acuerdo. Si un tercero nos reclama por la foto o por vuestros textos, responderéis vosotros de esa reclamación.</p>
<p>No se puede publicar contenido ilegal, ofensivo o que vulnere derechos de otras personas. Si recibimos un aviso fundado sobre ello, podremos retirar ese contenido y os avisaremos.</p>

<h2>10 bis. Libro de invitados</h2>
<p>Los mensajes y fotos del libro los publican vuestros invitados y aparecen al momento. Vosotros decidís qué se queda: podéis ocultar o borrar cualquiera desde el panel. La página del libro incluye un enlace para pedir la retirada de un contenido. Si nos llega un aviso fundado de que un mensaje o una foto es ilegal o vulnera derechos de alguien (por ejemplo, la imagen de un menor sin permiso), lo retiraremos sin esperar y os avisaremos.</p>

<h2>11. Licencia del ZIP</h2>
<p>El diseño, el código y la plantilla son de <?= $E['nif'] !== '' ? h($E['titular']) : 'quien presta el servicio con la marca ' . $M ?>. Con la compra recibís una licencia para usar el ZIP de vuestra web:</p>
<ul>
  <li>solo para vuestra boda y sin fines comerciales;</li>
  <li>podéis guardarlo, abrirlo y alojarlo donde queráis como recuerdo;</li>
  <li>no podéis venderlo, cederlo ni usarlo como plantilla para otras bodas o para terceros.</li>
</ul>
<p>La licencia no caduca.</p>

<h2>12. Uso en nuestro portfolio</h2>
<p>No mostraremos vuestra web ni ninguna parte de ella como ejemplo de nuestro trabajo salvo que nos deis permiso por separado. Esa petición, si la hacemos, os llegará aparte y podéis decir que no sin que cambie nada del servicio.</p>

<h2>13. Datos de vuestros invitados</h2>
<p>Las respuestas de los invitados son datos personales, y las alergias son datos de salud. Vosotros sois los responsables de esos datos y nosotros los tratamos por encargo vuestro, en los términos del anexo II. La web muestra a cada invitado un aviso de privacidad con vuestros nombres, el email de contacto que nos deis y la fecha de borrado.</p>
<p>Si exportáis las respuestas a Excel, o imprimís o guardáis en PDF el resumen para el catering o la hoja del plano de mesas, esas copias quedan fuera de nuestro alojamiento y de nuestro control. Es responsabilidad vuestra guardarlas con cuidado, no compartirlas más allá de quien las necesite para organizar la boda y borrarlas o destruirlas cuando ya no hagan falta. El resumen para el catering y la hoja del plano de mesas llevan nombres y alergias: dádselos solo a quien sirve la comida, para ese fin, y pedidle que los destruya después de la boda. Las hojas impresas no se actualizan solas: si cambian las respuestas o el plano, volved a imprimirlas y destruid las anteriores.</p>
<p>El nombre de cada grupo de la lista de invitados lo ve quien abre su enlace. Poned nombres neutros (por ejemplo, «Familia García» o «Amigos de la universidad»), sin etiquetas ofensivas ni notas sobre las personas. Cada enlace permite responder por su grupo: enviadlo solo a ese grupo y, si llega a quien no debe, cambiadlo desde el panel; el anterior deja de funcionar.</p>

<h2>14. Contraseña del panel</h2>
<p>La contraseña la elegís vosotros con el enlace de un solo uso que os enviamos. No la compartáis con quien no deba ver los datos de los invitados. Si creéis que alguien la conoce, escribidnos y os enviaremos un enlace nuevo.</p>

<h2>15. Responsabilidad</h2>
<p>Respondemos de los daños que os cause un incumplimiento nuestro, según la ley. No respondemos de lo que no depende de nosotros, como cortes de internet ajenos o fallos de <?= $V ?> al cobrar, ni del uso que hagáis del Excel o del ZIP una vez descargados. Nada de estas condiciones limita los derechos que os da la ley como consumidores.</p>

<h2>16. Reclamaciones y ley aplicable</h2>
<p>Para cualquier reclamación sobre la web o el servicio, escribid a <?= h($E['email']) ?>. Os responderemos lo antes posible y como máximo en un mes. Las dudas sobre el cobro, el recibo o la factura podéis planteárnoslas a nosotros o directamente a <?= $V ?>.</p>
<p>No estamos adheridos a ninguna entidad de resolución alternativa de conflictos de consumo. Si no llegamos a un acuerdo, podéis acudir a los servicios de consumo de vuestra comunidad autónoma o a los tribunales.</p>
<p>Estas condiciones se rigen por la ley española. Si residís en otro país de la Unión Europea, conserváis la protección que os dan las normas de consumo de ese país que no se pueden excluir por contrato. Podéis reclamar ante los tribunales de vuestro domicilio.</p>

<h2>Anexo I. Modelo de formulario de desistimiento</h2>
<p>Sirve para desistir del alojamiento en los 14 días siguientes a la compra, o en cualquier otro caso en que la ley os reconozca el derecho de desistimiento (apartado 8).</p>
<p>A la atención de <?= h($E['titular']) ?><?= $E['titular'] !== marca() ? ' (' . $M . ')' : '' ?>, <?= $E['domicilio'] !== '' ? h($E['domicilio']) . ', ' : '' ?><?= h($E['email']) ?>, o de <?= $V ?>:</p>
<ul>
  <li>Por la presente os comunico que desisto del contrato (del alojamiento) de la web de boda publicada en: ____________</li>
  <li>Fecha de compra y número de pedido de <?= $V ?>: ____________</li>
  <li>Nombre de quien compró: ____________</li>
  <li>Dirección de quien compró: ____________</li>
  <li>Firma (solo si se envía en papel): ____________</li>
  <li>Fecha: ____________</li>
</ul>

<h2>Anexo II. Encargo de tratamiento de datos (artículo 28 del RGPD)</h2>

<h2>II.1. Partes y papel de cada una</h2>
<p>La pareja que contrata la web es la responsable de los datos de sus invitados: decide para qué se recogen y los usa para organizar su boda. <?php if ($E['nif'] !== ''): ?><?= h($E['titular']) ?>, que presta el servicio con la marca <?= $M ?> (en este anexo, «<?= $M ?>»),<?php else: ?>Quien presta el servicio con la marca <?= $M ?> (en este anexo, «<?= $M ?>»; sus datos se publicarán antes de abrir la venta)<?php endif; ?> es el encargado: los guarda y los muestra a la pareja en el panel, por encargo suyo. Este anexo forma parte del contrato y se acepta al comprar. <?= $V ?> no es parte de este anexo: no recibe datos de invitados.</p>
<p>Aunque la pareja trate esos datos para una actividad personal, <?= $M ?> cumple igualmente todo lo que dice este anexo.</p>
<p>Para dar soporte y llevar el servicio, <?= $M ?> solo ve cifras agregadas y anónimas de cada web, como el número total de personas que han confirmado. Nunca ve los datos de un invitado concreto ni cifras por menú o por alergia.</p>

<h2>II.2. Qué se trata</h2>
<ul>
  <li>Objeto: alojar el formulario de asistencia y las canciones, guardar las respuestas y ponerlas a disposición de la pareja en el panel y en la exportación a Excel; generar un enlace personal para cada grupo de la lista de invitados; preparar para la pareja resúmenes imprimibles del catering y, si lo usa, del plano de mesas; publicar los mensajes y fotos del libro de invitados y las fotos de la galería.</li>
  <li>Personas afectadas: los invitados que responden y las personas de su grupo (adultos y niños); quienes escriben en el libro y las personas que aparecen en las fotos, incluidos menores; y las personas que la pareja incluye en su lista de invitados aunque no respondan.</li>
  <li>Datos: nombre, si es adulto o niño, menú, alergias o intolerancias, si asiste, si usa el autobús, dato de contacto y canciones pedidas o votadas; mensajes y fotos del libro, la dirección IP de cada mensaje (para atender avisos de abuso) y un código derivado de la IP guardado un día como máximo para evitar abusos; de la lista de invitados, el nombre, el grupo, el enlace del grupo, la fecha y hora del primer acceso a ese enlace (sin dirección IP ni navegador); y, si la pareja usa el plano de mesas, los nombres y plazas de las mesas y la mesa asignada a cada persona que ha confirmado su asistencia al banquete, junto con una copia de su nombre tomada al sentarla, que sirve solo para avisar a la pareja si esa persona deja de venir y se conserva hasta que la pareja la quita del plano o hasta el borrado. Tampoco ve <?= $M ?> estos datos del plano de mesas.</li>
  <li>Categoría especial: las alergias e intolerancias son datos de salud (artículo 9 del RGPD). La web solo las recoge si quien responde da su consentimiento explícito en el formulario.</li>
  <li>Duración: desde la publicación de la web hasta el borrado automático, <?= (int) MESES_ALOJAMIENTO ?> meses después de la fecha de la boda, o hasta la baja de la web si es antes.</li>
</ul>

<h2>II.3. Obligaciones de <?= $M ?></h2>
<ul>
  <li>Tratar los datos solo para prestar este servicio y siguiendo las instrucciones de la pareja, que son las de este contrato y las que dé por escrito después. Si una instrucción nos parece contraria a la ley, lo diremos.</li>
  <li>No usar los datos para nada propio: ni publicidad, ni estadísticas, ni cederlos a nadie, salvo obligación legal.</li>
  <li>Garantizar que quien pueda acceder a ellos está obligado a guardar confidencialidad.</li>
  <li>Aplicar medidas de seguridad adecuadas: los datos se guardan fuera de la parte pública del servidor, el panel está protegido con una contraseña que guardamos de forma que nadie, ni nosotros, puede leerla, y la conexión con la web va cifrada. Los mensajes y fotos del libro y de la galería no están detrás de la contraseña: los ve quien tenga el enlace y el código de la boda, porque ese es su fin. Cada enlace de grupo lleva un código aleatorio que no se puede adivinar y no muestra las respuestas ya enviadas, las alergias ni los datos de contacto.</li>
  <li>Ayudar a la pareja a atender las peticiones de los invitados (ver, corregir o borrar sus datos) y, si procede, en las evaluaciones de impacto o consultas a la autoridad de control.</li>
  <li>Al terminar el encargo, borrar los datos de invitados. Antes de esa fecha, la pareja puede llevarse una copia exportando el Excel. No guardamos copias después.</li>
  <li>Poner a disposición de la pareja la información necesaria para demostrar que cumplimos este anexo y permitir, con aviso razonable, las comprobaciones que pida.</li>
</ul>

<h2>II.4. Subencargados</h2>
<p>La pareja autoriza a <?= $M ?> a usar estos subencargados:</p>
<ul>
  <li>Hostinger, para el alojamiento de la web y el envío de emails.</li>
  <li>Cloudflare, Inc. (Estados Unidos), cuya red recibe las visitas a la web, incluidas las respuestas que envían los invitados, y las entrega cifradas a nuestro alojamiento. Puede tratar datos fuera de la Unión Europea con las cláusulas contractuales tipo de la Comisión Europea que recoge su acuerdo de tratamiento.</li>
</ul>
<p>Si cambiamos o añadimos un subencargado que trate datos de invitados, lo avisaremos por email con antelación y la pareja podrá oponerse; si se opone y no podemos seguir sin ese cambio, podrá resolver el contrato. Cada subencargado queda sujeto a obligaciones de protección de datos equivalentes a las de este anexo.</p>

<h2>II.5. Brechas de seguridad</h2>
<p>Si sufrimos una brecha que afecte a datos de invitados, avisaremos a la pareja sin dilación indebida desde que lo sepamos, con lo que sepamos en ese momento: qué ha pasado, qué datos y cuántas personas pueden estar afectadas, qué consecuencias puede tener y qué medidas hemos tomado. Completaremos la información según la vayamos teniendo.</p>
<p>Todas las webs de boda comparten el mismo alojamiento. Si una brecha afecta a varias bodas, avisaremos a todas las parejas afectadas, no solo a una.</p>
<p>Como responsable, a la pareja le corresponde decidir si notifica la brecha a la Agencia Española de Protección de Datos (en 72 horas) y a sus invitados. Le daremos la información y la ayuda necesarias para hacerlo.</p>

<h2>II.6. Obligaciones de la pareja</h2>
<ul>
  <li>Usar los datos de los invitados solo para organizar la boda.</li>
  <li>Dar un email de contacto que funcione para que los invitados puedan ejercer sus derechos, y atender esas peticiones.</li>
  <li>Guardar con cuidado la contraseña del panel, el Excel exportado y el resumen para el catering y la hoja del plano de mesas impresos o en PDF; pasar esas hojas solo a quien sirve la comida y destruirlas después de la boda (apartado 13 de las condiciones).</li>
  <li>En la lista de invitados, poner solo nombres y grupos: ni datos de contacto, ni de salud, ni notas sobre las personas. Los nombres de los grupos los ve quien abre su enlace, así que no pueden llevar etiquetas ofensivas.</li>
  <li>Enviar cada enlace de grupo solo a ese grupo, y cambiarlo desde el panel si llega a quien no debe.</li>
</ul>
