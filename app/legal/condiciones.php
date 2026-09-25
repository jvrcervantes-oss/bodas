<?php
// Condiciones de contratacion + Anexo de encargo de tratamiento (art. 28 RGPD).
// Version 2026-09-25. Se incluye dentro de un <main> ya maquetado. $E lo define la app.
?>
<h1>Condiciones de contratación</h1>
<p>Versión del 25 de septiembre de 2026. Se aplica la versión vigente el día de vuestra compra.</p>

<h2>1. Quiénes somos</h2>
<p>AxisWorks es el nombre comercial de <?= h($E['titular']) ?>, con NIF <?= h($E['nif']) ?> y domicilio en <?= h($E['domicilio']) ?>. Para cualquier duda, incidencia o reclamación: <?= h($E['email']) ?>.</p>

<h2>2. Qué compráis</h2>
<p>Una web para vuestra boda, creada con nuestro creador y publicada en https://vuestro-nombre.axisworks.studio. Incluye:</p>
<ul>
  <li>La web con las secciones, textos, fechas, lugares y foto que elijáis en el creador.</li>
  <li>Un formulario de confirmación de asistencia por grupo. Por cada invitado: nombre, si es adulto o niño, menú y alergias. Por cada grupo: si asiste, si usa el autobús, un dato de contacto y una canción.</li>
  <li>Peticiones y votos de canciones.</li>
  <li>Un panel privado con contraseña para ver las respuestas, exportarlas a Excel, editar la web y descargar un ZIP.</li>
  <li>Si las activáis: una galería de hasta 24 fotos que subís desde el panel, y un libro de invitados donde quien tenga el enlace y el código de la boda puede dejar su nombre, un mensaje y una foto. Ambas van detrás de un código de acceso que elegís vosotros.</li>
  <li>Alojamiento de la web hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la fecha de la boda (apartado 7).</li>
</ul>
<p>El ZIP contiene el HTML estático de vuestra web y las fotos de la galería, sin datos de invitados ni el libro de invitados. Es una copia de lo que se ve, no de lo que funciona: fuera de nuestro alojamiento, el formulario de asistencia, las canciones y el panel no funcionan. Se abre en cualquier navegador actual.</p>
<p>Las webs de boda no aparecen en buscadores: están marcadas para que Google y similares no las indexen. Cualquiera que tenga el enlace puede verlas.</p>

<h2>3. Quién puede comprar</h2>
<p>Personas mayores de 18 años que compran para su propia boda, como consumidores.</p>

<h2>4. Cómo se contrata</h2>
<ul>
  <li>Montáis la web en el creador. El borrador se guarda en vuestro navegador; nosotros no lo recibimos hasta que pagáis, salvo que pulséis «Seguir en otro dispositivo» (os guardamos una copia 30 días, como explica la política de privacidad).</li>
  <li>Revisáis la vista previa. Hasta pulsar el botón de pago podéis cambiar cualquier dato o corregir errores.</li>
  <li>Aceptáis estas condiciones y el encargo de tratamiento (anexo II), y pedís que la web se cree ya (apartado 8).</li>
  <li>Pagáis en la página de Stripe. Al confirmarse el pago, la web se publica al momento.</li>
  <li>Os enviamos un email a la dirección que deis en Stripe con la dirección de la web, un enlace de un solo uso para elegir la contraseña del panel, la factura y una copia de estas condiciones con vuestra petición de ejecución inmediata.</li>
</ul>
<p>El contrato se celebra en castellano. Guardamos una copia de estas condiciones con su fecha de versión; si la necesitáis, pedídnosla por email.</p>

<h2>5. Precio y pago</h2>
<p>Dos packs, IVA incluido: Esencial, <?= h(euros(precio_esencial_cent())) ?>; y Atelier, con un diseño ilustrado de la colección y sus animaciones, <?= h(euros(precio_atelier_cent())) ?>. Pago único. No hay cuotas ni renovaciones. El pago se hace con tarjeta u otro medio que ofrezca Stripe en su página; nosotros no vemos ni guardamos los datos de la tarjeta. Recibiréis una factura simplificada por email.</p>

<h2>6. Plazo de entrega</h2>
<p>La web se publica en cuanto Stripe confirma el pago, normalmente en segundos. Si pasado un rato no la veis o no os llega el email, escribid a <?= h($E['email']) ?> y lo resolvemos.</p>

<h2>7. Duración y borrado</h2>
<p>La web y las respuestas de los invitados se mantienen hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la fecha de la boda que figure en la web. Ese día, de forma automática:</p>
<ul>
  <li>se borran todas las respuestas de asistencia (nombres, menús, alergias, contactos) y las canciones;</li>
  <li>se borran las fotos de la galería y los mensajes y fotos del libro de invitados;</li>
  <li>la web deja de mostrar su contenido y pasa a una página de agradecimiento.</li>
</ul>
<p>Lo borrado no se puede recuperar. Si queréis conservar las respuestas, exportad el Excel y guardad las fotos antes de esa fecha. Podéis pedirnos que borremos antes la web entera escribiendo a <?= h($E['email']) ?>.</p>

<h2>8. Derecho de desistimiento</h2>
<p>Como consumidores, tenéis 14 días naturales desde la compra para desistir sin dar motivos.</p>
<p>Pero la web se crea y se publica al momento de pagar, a petición vuestra. Por eso, antes de pagar, marcáis una casilla en la que pedís que empecemos ya y reconocéis que, una vez publicada la web, perdéis el derecho de desistimiento (artículo 103.m de la Ley General para la Defensa de los Consumidores y Usuarios). El email de confirmación recoge esa petición y ese reconocimiento.</p>
<p>Si en algún caso se entendiera que el derecho no se ha perdido y desistís dentro del plazo, os devolveremos lo pagado menos la parte proporcional del servicio ya prestado hasta que nos lo comuniquéis (artículo 108.3 de la misma ley), por el mismo medio de pago y en un máximo de 14 días.</p>
<p>Para desistir basta con decírnoslo por email a <?= h($E['email']) ?>. Podéis usar el modelo del anexo I, aunque no es obligatorio.</p>

<h2>9. Garantía</h2>
<p>La web tiene que funcionar como se describe en estas condiciones durante todo el tiempo de alojamiento. Si algo no funciona, avisadnos y lo arreglaremos sin coste. Si no lo arreglamos en un plazo razonable, podéis pedir una rebaja del precio o, si el fallo es importante, resolver el contrato y recuperar lo pagado. Es la garantía legal que os da la ley y no la limitamos.</p>

<h2>10. Vuestros textos y vuestras fotos</h2>
<p>Los textos y la foto que ponéis en la web son vuestros y seguís siendo sus titulares. Al subirlos nos dais permiso para alojarlos y mostrarlos en vuestra web mientras dure el servicio, y para nada más.</p>
<p>Al subir las fotos (la de portada y las de la galería) garantizáis que tenéis derecho a usarlas: que la hicisteis vosotros o que el fotógrafo os permite publicarla, y que las personas que aparecen están de acuerdo. Si un tercero nos reclama por la foto o por vuestros textos, responderéis vosotros de esa reclamación.</p>
<p>No se puede publicar contenido ilegal, ofensivo o que vulnere derechos de otras personas. Si recibimos un aviso fundado sobre ello, podremos retirar ese contenido y os avisaremos.</p>

<h2>10 bis. Libro de invitados</h2>
<p>Los mensajes y fotos del libro los publican vuestros invitados y aparecen al momento. Vosotros decidís qué se queda: podéis ocultar o borrar cualquiera desde el panel. La página del libro incluye un enlace para pedir la retirada de un contenido. Si nos llega un aviso fundado de que un mensaje o una foto es ilegal o vulnera derechos de alguien (por ejemplo, la imagen de un menor sin permiso), lo retiraremos sin esperar y os avisaremos.</p>

<h2>11. Licencia del ZIP</h2>
<p>El diseño, el código y la plantilla son de <?= h($E['titular']) ?>. Con la compra recibís una licencia para usar el ZIP de vuestra web:</p>
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
<p>Si exportáis las respuestas a Excel, esa copia queda fuera de nuestro alojamiento y de nuestro control. Es responsabilidad vuestra guardarla con cuidado, no compartirla más allá de quien la necesite para organizar la boda (por ejemplo, el catering) y borrarla cuando ya no haga falta.</p>

<h2>14. Contraseña del panel</h2>
<p>La contraseña la elegís vosotros con el enlace de un solo uso que os enviamos. No la compartáis con quien no deba ver los datos de los invitados. Si creéis que alguien la conoce, escribidnos y os enviaremos un enlace nuevo.</p>

<h2>15. Responsabilidad</h2>
<p>Respondemos de los daños que os cause un incumplimiento nuestro, según la ley. No respondemos de lo que no depende de nosotros, como cortes de internet ajenos o fallos de Stripe, ni del uso que hagáis del Excel o del ZIP una vez descargados. Nada de estas condiciones limita los derechos que os da la ley como consumidores.</p>

<h2>16. Reclamaciones y ley aplicable</h2>
<p>Para cualquier reclamación, escribid a <?= h($E['email']) ?>. Os responderemos lo antes posible y como máximo en un mes.</p>
<p>No estamos adheridos a ninguna entidad de resolución alternativa de conflictos de consumo. Si no llegamos a un acuerdo, podéis acudir a los servicios de consumo de vuestra comunidad autónoma o a los tribunales.</p>
<p>Este contrato se rige por la ley española. Si residís en otro país de la Unión Europea, conserváis la protección que os dan las normas de consumo de ese país que no se pueden excluir por contrato. Podéis reclamar ante los tribunales de vuestro domicilio.</p>

<h2>Anexo I. Modelo de formulario de desistimiento</h2>
<p>Solo tenéis que rellenarlo y enviarlo si queréis desistir del contrato.</p>
<p>A la atención de <?= h($E['titular']) ?> (AxisWorks), <?= h($E['domicilio']) ?>, <?= h($E['email']) ?>:</p>
<ul>
  <li>Por la presente os comunico que desisto del contrato de la web de boda publicada en: ____________</li>
  <li>Fecha de compra: ____________</li>
  <li>Nombre de quien compró: ____________</li>
  <li>Dirección de quien compró: ____________</li>
  <li>Firma (solo si se envía en papel): ____________</li>
  <li>Fecha: ____________</li>
</ul>

<h2>Anexo II. Encargo de tratamiento de datos (artículo 28 del RGPD)</h2>

<h2>II.1. Partes y papel de cada una</h2>
<p>La pareja que contrata la web es la responsable de los datos de sus invitados: decide para qué se recogen y los usa para organizar su boda. <?= h($E['titular']) ?> (AxisWorks) es el encargado: los guarda y los muestra a la pareja en el panel, por encargo suyo. Este anexo forma parte del contrato y se acepta al comprar.</p>
<p>Aunque la pareja trate esos datos para una actividad personal, AxisWorks cumple igualmente todo lo que dice este anexo.</p>
<p>Para dar soporte y llevar el servicio, AxisWorks solo ve cifras agregadas y anónimas de cada web, como el número total de personas que han confirmado. Nunca ve los datos de un invitado concreto ni cifras por menú o por alergia.</p>

<h2>II.2. Qué se trata</h2>
<ul>
  <li>Objeto: alojar el formulario de asistencia y las canciones, guardar las respuestas y ponerlas a disposición de la pareja en el panel y en la exportación a Excel; publicar los mensajes y fotos del libro de invitados y las fotos de la galería.</li>
  <li>Personas afectadas: los invitados que responden y las personas de su grupo (adultos y niños); quienes escriben en el libro y las personas que aparecen en las fotos, incluidos menores; y las personas que la pareja incluye en su lista de invitados aunque no respondan.</li>
  <li>Datos: nombre, si es adulto o niño, menú, alergias o intolerancias, si asiste, si usa el autobús, dato de contacto y canciones pedidas o votadas; mensajes y fotos del libro, la dirección IP de cada mensaje (para atender avisos de abuso) y un código derivado de la IP guardado un día como máximo para evitar abusos; de la lista de invitados, solo nombre y grupo, que tampoco ve AxisWorks.</li>
  <li>Categoría especial: las alergias e intolerancias son datos de salud (artículo 9 del RGPD). La web solo las recoge si quien responde da su consentimiento explícito en el formulario.</li>
  <li>Duración: desde la publicación de la web hasta el borrado automático, <?= (int) MESES_ALOJAMIENTO ?> meses después de la fecha de la boda.</li>
</ul>

<h2>II.3. Obligaciones de AxisWorks</h2>
<ul>
  <li>Tratar los datos solo para prestar este servicio y siguiendo las instrucciones de la pareja, que son las de este contrato y las que dé por escrito después. Si una instrucción nos parece contraria a la ley, lo diremos.</li>
  <li>No usar los datos para nada propio: ni publicidad, ni estadísticas, ni cederlos a nadie, salvo obligación legal.</li>
  <li>Garantizar que quien pueda acceder a ellos está obligado a guardar confidencialidad.</li>
  <li>Aplicar medidas de seguridad adecuadas: los datos se guardan fuera de la parte pública del servidor, el panel está protegido con una contraseña que guardamos de forma que nadie, ni nosotros, puede leerla, y la conexión con la web va cifrada. Los mensajes y fotos del libro y de la galería no están detrás de la contraseña: los ve quien tenga el enlace y el código de la boda, porque ese es su fin.</li>
  <li>Ayudar a la pareja a atender las peticiones de los invitados (ver, corregir o borrar sus datos) y, si procede, en las evaluaciones de impacto o consultas a la autoridad de control.</li>
  <li>Al terminar el encargo, borrar los datos de invitados. Antes de esa fecha, la pareja puede llevarse una copia exportando el Excel. No guardamos copias después.</li>
  <li>Poner a disposición de la pareja la información necesaria para demostrar que cumplimos este anexo y permitir, con aviso razonable, las comprobaciones que pida.</li>
</ul>

<h2>II.4. Subencargados</h2>
<p>La pareja autoriza a AxisWorks a usar a Hostinger para el alojamiento de la web y el envío de emails. Stripe no recibe datos de invitados. Si cambiamos o añadimos un subencargado que trate datos de invitados, lo avisaremos por email con antelación y la pareja podrá oponerse; si se opone y no podemos seguir sin ese cambio, podrá resolver el contrato. Cada subencargado queda sujeto a obligaciones de protección de datos equivalentes a las de este anexo.</p>

<h2>II.5. Brechas de seguridad</h2>
<p>Si sufrimos una brecha que afecte a datos de invitados, avisaremos a la pareja sin dilación indebida desde que lo sepamos, con lo que sepamos en ese momento: qué ha pasado, qué datos y cuántas personas pueden estar afectadas, qué consecuencias puede tener y qué medidas hemos tomado. Completaremos la información según la vayamos teniendo.</p>
<p>Todas las webs de boda comparten el mismo alojamiento. Si una brecha afecta a varias bodas, avisaremos a todas las parejas afectadas, no solo a una.</p>
<p>Como responsable, a la pareja le corresponde decidir si notifica la brecha a la Agencia Española de Protección de Datos (en 72 horas) y a sus invitados. Le daremos la información y la ayuda necesarias para hacerlo.</p>

<h2>II.6. Obligaciones de la pareja</h2>
<ul>
  <li>Usar los datos de los invitados solo para organizar la boda.</li>
  <li>Dar un email de contacto que funcione para que los invitados puedan ejercer sus derechos, y atender esas peticiones.</li>
  <li>Guardar con cuidado la contraseña del panel y el Excel exportado (apartado 13 de las condiciones).</li>
  <li>En la lista de invitados, poner solo nombres y grupos: ni datos de contacto, ni de salud, ni notas sobre las personas.</li>
</ul>
