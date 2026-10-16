<?php
// Política de privacidad del creador y de la compra (el titular como responsable).
// Versión 2026-10-16 (Legal, encargo bodaenlace-cierra; owner por agencia-cd: «Cámbiala si quieres»): se publica el borrador del
// 2-oct con D1 y D3 como decisión por defecto (contexto/legal/rat_padrino.md §Decisiones), con evidencia:
//  · D1, buzón 24 meses: el buzón nació el 26-sep-2026 (agencia 59b4b9670, BOD-11), así que hoy ningún correo puede pasar de
//    24 meses; nada lo purga todavía: el borrado automático tiene que existir antes del 26-sep-2028 (encargo del estudio).
//  · D3: las lecciones salen solo de métricas agregadas (padrino negocios/bodas/decisiones.py:110 y clase Analitica);
//    pad_consumo_api no lleva datos personales (migración 20260926120000) y nació el 26-sep-2026, sin purga (6 años = 2032).
// Y la casilla de oposición comercial del paso Publicar (textos.php → check_oposicion_comercial; api_pagar la guarda).
// SIN sección de correo en frío a empresas: si algún día se escribe en frío a empresas, esta política deja de ser verdad.
// Versión 2026-10-02 (Legal, BOD-31/BOD-46, revisiones previas #189 y #196): reescrita para decir lo que el sistema hace de verdad con
// El Padrino (IA de un tercero como encargado, correo con IA, análisis de agregados, decisiones, comunicaciones comerciales,
// reseñas, pruebas de precio), con base legal por finalidad, transferencias (EE. UU. con las cláusulas tipo del acuerdo de
// Anthropic, Indonesia con el anexo II.7 de las condiciones), plazos y derechos (RGPD/LOPDGDD y, por estar el responsable en
// Indonesia, UU PDP). SIN datos personales de ninguna persona física: el responsable es la sociedad (empresa_publica()) y el
// contacto, el buzón de la marca.
// Representante en la UE (art. 27 RGPD; decisión del owner, 2-oct-2026): el titular es PT Mahkota (Indonesia) y su representante
// en la UE es una persona física establecida en España. Sus datos NO están en el repo: salen de secrets.php (servidor) ->
// 'representante_ue' => ['nombre' => ..., 'email' => ..., 'direccion' => ... (opcional)]. Se publican solo nombre y contacto
// (EDPB 3/2018 v2.1, §4 ej. 24: «name and contact details»). NIF y domicilio personales no salen nunca (aceptado por el owner).
// Se rellena en secrets.php SOLO cuando la designación escrita (art. 27.1, Recital 80) está firmada: el texto afirma que existe.
// Mientras falte, el párrafo del representante no sale (16-oct: antes enseñaba «PENDIENTE OWNER BOD-32», un relleno en una
// página pública); la falta queda en contexto/pendientes.md (BOD-32) con el owner, y no se inventa un nombre.
// Verificado el 2-oct-2026 (fuentes en contexto/legal/rat_padrino.md §Fuentes): Anthropic no se ampara en el Marco de Privacidad
// de Datos UE-EE. UU. según su propia política (10-sep-2026); lo hace en las cláusulas tipo (módulo 2) de su acuerdo de
// tratamiento, incorporado a sus Condiciones Comerciales. Si cambia, se cambia AQUÍ y en el RAT.
// Versión 2026-09-27 (Legal): Lemon Squeezy (LS) es el vendedor y trata los datos de compra como
// responsable por su cuenta; Cloudflare, declarado (el dominio y las bodas pasan por su red).
// El beacon de Web Analytics de Cloudflare NO se declara: se desactiva en la zona (BOD-15).
// Se incluye dentro de un <main> ya maquetado. $E lo define la app.
$L = textos_legales();
$M = h(marca());
$V = h($L['vendedor'] ?? '');
$R = (array) secreto('representante_ue', []);
$R_email = trim((string) ($R['email'] ?? '')) !== '' ? trim((string) $R['email']) : (string) $E['email'];
$R_dir = trim((string) ($R['direccion'] ?? ''));
// Hay representante si hay nombre y algún contacto real (el email del propio representante, o una dirección postal).
$tieneR = trim((string) ($R['nombre'] ?? '')) !== '' && (trim((string) ($R['email'] ?? '')) !== '' || $R_dir !== '');
?>
<h1>Política de privacidad</h1>
<p>Versión del 16 de octubre de 2026. Explica qué datos tratamos cuando usáis el creador de webs de boda de <?= $M ?> y cuando compráis una web.</p>
<p>Los datos de los invitados que responden en cada web de boda no los tratamos por nuestra cuenta: los tratamos por encargo de cada pareja. Cada web tiene su propio aviso de privacidad para invitados.</p>

<h2>Responsable</h2>
<?php if (titular_identificado($E)): ?>
<ul>
  <li><?= h($E['titular']) ?>, sociedad que presta el servicio con la marca <?= $M ?>, un producto de AxisWorks, su nombre comercial</li>
<?php if ($E['nif'] !== ''): ?>  <li>Identificación fiscal: <?= h($E['nif']) ?></li>
<?php endif; if ($E['domicilio'] !== ''): ?>  <li>Domicilio social: <?= h($E['domicilio']) ?></li>
<?php endif; ?>  <li>Contacto para cualquier asunto de privacidad: <?= email_enlace($E['email']) ?></li>
</ul>
<?php else: ?>
<p>Quien presta el servicio con la marca <?= $M ?>, un producto de AxisWorks. El servicio está en pruebas y todavía no se vende: su razón social, identificación fiscal y domicilio se publicarán aquí antes de abrir la venta.</p>
<ul>
  <li>Contacto para cualquier asunto de privacidad: <?= email_enlace($E['email']) ?></li>
</ul>
<?php endif; ?>
<p>No hay delegado de protección de datos: no tratamos datos a gran escala ni hacemos un seguimiento sistemático de las personas.</p>
<?php if ($tieneR): ?>
<p><strong>Representante en la Unión Europea.</strong> Como el responsable está fuera de la Unión Europea, ha designado por escrito, según el artículo 27 del RGPD, a <?= h($R['nombre']) ?>, establecido en España, como su representante en la Unión Europea. Contacto: <?= email_enlace($R_email) ?><?= $R_dir !== '' ? ', ' . h($R_dir) : '' ?>. Podéis dirigiros al representante, además de al responsable o en lugar de él, para cualquier asunto sobre vuestros datos o para ejercer vuestros derechos, y también pueden hacerlo las autoridades de protección de datos.</p>
<?php endif; ?>
<p>El responsable está establecido en Indonesia, fuera de la Unión Europea. Además del RGPD y la LOPDGDD, que se nos aplican porque ofrecemos el servicio en España, nos aplica la ley indonesia de protección de datos personales (UU PDP, Ley 27/2022).</p>

<h2>Mientras montáis la web en el creador</h2>
<p>El borrador (textos, fechas, lugares, foto) se guarda en el almacenamiento local de vuestro navegador, salvo que pulséis «Seguir en otro dispositivo». No nos llega nada más hasta que publicáis. Si borráis los datos del navegador, el borrador que solo esté ahí se pierde.</p>
<p>Si pulsáis «Seguir en otro dispositivo», guardamos en nuestro servidor una copia del borrador (con la foto, si la hay) bajo un enlace secreto, para que lo abráis en otro móvil u ordenador. Quien tenga el enlace puede verlo. La base legal son las medidas precontractuales que nos pedís (art. 6.1.b del RGPD). La guardamos 30 días y después se borra sola, también la foto; podéis borrarla antes desde el mismo aviso. El email que haya en el borrador no lo usamos para recordatorios ni publicidad.</p>

<h2>Qué datos tratamos y para qué</h2>
<p>Cada uso lleva su base legal. Cuando la base es el consentimiento, podéis retirarlo cuando queráis, y lo hecho antes sigue siendo válido.</p>
<ul>
  <li>Datos del pedido: el email que ponéis en el creador y el de la página de pago, el número de pedido de <?= $V ?>, el pack, el importe y el país. <?= $V ?> nos los pasa cuando el pago se confirma. Los usamos para crear la web, enviaros el enlace para elegir la contraseña del panel y atenderos. Base legal: la ejecución del contrato (art. 6.1.b del RGPD). Los datos de pago y la dirección de facturación los trata <?= $V ?>; nosotros no vemos los datos de la tarjeta.</li>
  <li>Registro de ventas: número de pedido, fecha, pack, importe y lo que nos liquida <?= $V ?>, para nuestra contabilidad y nuestras obligaciones fiscales. Base legal: obligación legal (art. 6.1.c).</li>
  <li>Prueba de lo que aceptasteis: el texto de las casillas que marcasteis antes de pagar (también si marcasteis que no queréis recibir ofertas), con su fecha y la versión de las condiciones. Base legal: la ejecución del contrato y nuestra obligación de poder demostrar vuestro consentimiento a la ejecución inmediata.</li>
  <li>Contenido de la web: vuestros nombres, la fecha y los lugares de la boda, los textos, la foto y el email de contacto que indiquéis. Los usamos para publicar la web. Base legal: la ejecución del contrato. Tened en cuenta que este contenido es visible para cualquiera que tenga el enlace de la web, y que el email de contacto aparece en el aviso de privacidad para vuestros invitados.</li>
  <li>Gestión interna del servicio y atención al cliente: vuestros nombres, email, pedido y estado de vuestra web los ve solo el equipo del titular, en un panel interno, para atenderos y llevar el servicio. Base legal: la ejecución del contrato.</li>
  <li>Mensajes que nos enviéis por correo: para responderos. Base legal: la ejecución del contrato o, si no sois clientes, nuestro interés legítimo en atender a quien nos escribe (art. 6.1.f). Una parte de esas respuestas las redacta un sistema de IA; lo explicamos en el apartado «Inteligencia artificial y decisiones automatizadas».</li>
  <li>Seguridad y prevención de abusos: registros técnicos de las peticiones (IP y hora), límites de uso por IP y, en el libro de invitados, la IP de cada mensaje. Base legal: nuestro interés legítimo en proteger el servicio (art. 6.1.f).</li>
  <li>Códigos de regalo: si la web os la regalamos, guardamos el código usado y una nota interna mínima, sin apellidos ni datos de contacto. Base legal: nuestro interés legítimo en gestionar el regalo; al publicar la web pasáis a ser clientes como cualquier otra pareja.</li>
  <li>Cifras agregadas del negocio: a partir de los pedidos, las visitas y las respuestas, calculamos totales (por ejemplo, cuántas webs se han vendido de cada pack o cuántas personas han confirmado en total) para decidir precios, textos y campañas. Se explica en el apartado «Cifras agregadas y pruebas de precio». Base legal: nuestro interés legítimo en saber qué funciona (art. 6.1.f).</li>
</ul>

<h2>Comunicaciones comerciales</h2>
<p>Hoy no os enviamos publicidad ni boletines. Esto es lo que haremos cuando lo hagamos, y no cambia sin que lo avisemos aquí:</p>
<ul>
  <li>Si habéis comprado, podremos escribiros sobre servicios parecidos al que contratasteis, por ejemplo una mejora o un extra de vuestra web. Base legal: el interés legítimo y el artículo 21.2 de la LSSI. Solo lo haremos si, al recoger vuestro email en el creador o en el pago, os hemos dado la opción de oponeros, y cada mensaje llevará un enlace para daros de baja con un clic.</li>
  <li>En el paso «Publicar» del creador podéis marcar la casilla «<?= h($L['check_oposicion_comercial'] ?? '') ?>». Si la marcáis, guardamos esa elección con vuestro pedido y no os escribimos nada comercial.</li>
  <li>A quien no ha comprado, a quien nos hace una consulta y a los invitados de una boda no les enviamos nada comercial salvo que lo pidan y den su consentimiento. Contestar a lo que nos preguntan no es publicidad. Aplicamos la misma regla a WhatsApp y a los mensajes directos.</li>
  <li>Quien se da de baja no recibe nada comercial, lo decida quien lo decida. La lista de bajas la escribe el sistema y ninguna IA puede modificarla.</li>
  <li>Después de la boda podemos pediros una reseña. Se la pedimos a todos los compradores, no solo a quienes creemos contentos, y solo aceptamos las de compradores verificados con su número de pedido. No las filtramos por ser negativas. Si ofrecemos algo a cambio de una reseña, lo decimos junto a la petición. Publicamos una reseña con vuestro permiso y podéis pedirnos que la retiremos.</li>
</ul>
<p>Podéis oponeros a cualquier comunicación comercial, en cualquier momento y sin dar motivos, desde el enlace de baja de cada mensaje o escribiendo a <?= email_enlace($E['email']) ?>.</p>

<h2>Inteligencia artificial y decisiones automatizadas</h2>
<p>Una parte del negocio la lleva un sistema de inteligencia artificial, «El Padrino». Usa modelos de Anthropic, PBC (Estados Unidos), que trata los datos por encargo nuestro y solo para lo que sigue. Esto es lo que hace y lo que no:</p>
<ul>
  <li><strong>Redactar respuestas a los correos que llegan a nuestro buzón.</strong> Para eso, el texto del correo se envía a Anthropic, que genera un borrador. Nosotros no guardamos el texto en el sistema de IA: solo una huella cifrada del remitente y del mensaje y el estado de la respuesta, como mucho 12 meses. El correo en sí queda en nuestro buzón, en Hostinger. Según la documentación de Anthropic para empresas, consultada el 2 de octubre de 2026, elimina esas entradas y salidas de su sistema en un máximo de 30 días, no las usa para entrenar sus modelos y solo las conserva más tiempo si lo exige la ley o si sus sistemas de seguridad marcan la conversación por un uso indebido (hasta 2 años). Cada respuesta lleva en la firma que la ha escrito un asistente de IA y explica cómo pedir que os atienda una persona.</li>
  <li><strong>Apartar lo delicado antes de la IA.</strong> Un filtro de código aparta y pasa a una persona los correos sobre reclamaciones, devoluciones, protección de datos, salud o alergias, listas de invitados, afiliados y facturas. Esos correos no se envían a la IA. El filtro puede fallar con un mensaje atípico; si un correo así llega a la IA, lo que contiene es lo que escribió quien nos contactó, y se trata como se explica en este apartado.</li>
  <li><strong>Proponer cambios generales del negocio</strong> (el precio de los packs, el nombre de la marca, los textos de las guías de ayuda y las campañas). Trabaja solo con cifras agregadas, sin datos de personas: ni nombres, ni emails, ni enlaces de webs, ni fechas de boda concretas. Los límites (precio mínimo y máximo, cuánto puede cambiar cada vez, cuánto puede gastar) están fijados en código que la IA no puede modificar. Las guías de ayuda de esta web las escribe la IA y se publican sin revisión humana previa; lo dicen al pie.</li>
  <li><strong>Os enseña el mismo precio que a cualquier otra persona.</strong> Las pruebas de precio se hacen por periodos, iguales para todos en cada momento, y nunca ajustan el precio a quién sois o a lo que habéis hecho en la web.</li>
</ul>
<p>El Padrino solo contesta consultas de atención. No tomamos decisiones basadas únicamente en un tratamiento automatizado que os produzcan efectos jurídicos o os afecten de forma significativa (art. 22 del RGPD). Cualquier decisión sobre un cliente, como conceder o negar un reembolso, bloquear un acceso o archivar una web, la toma una persona, y la IA no tiene forma de ejecutarla. Si pensáis que una respuesta automática es errónea, podéis pedir que la revise una persona y dar vuestro punto de vista escribiendo a <?= email_enlace($E['email']) ?>.</p>

<h2>Cifras agregadas y pruebas de precio</h2>
<p>Para decidir qué precio, qué textos y qué campañas funcionan, calculamos totales a partir de los pedidos, las visitas y las respuestas de las webs, por ejemplo, cuántas webs se han vendido de cada pack, cuántas visitas ha tenido cada página o cuántas personas han confirmado en total. Estos totales no identifican a nadie y son lo único que ve la IA al proponer cambios del negocio. No elaboramos perfiles individuales de las personas y no cruzamos estas cifras con vuestro nombre, email ni correos. Podéis oponeros a que vuestros datos entren en estos totales escribiendo a <?= email_enlace($E['email']) ?>.</p>

<h2>Cuánto tiempo los guardamos</h2>
<ul>
  <li>Contenido de la web: mientras la web esté publicada. Cuando la web pasa a la página de agradecimiento (<?= (int) MESES_ALOJAMIENTO ?> meses después de la boda), podéis pedirnos que la borremos entera.</li>
  <li>Datos del pedido, registro de ventas y prueba de lo aceptado: el tiempo que nos obliga la ley fiscal y mercantil, hasta 6 años.</li>
  <li>Correo: el texto de los mensajes que guardamos en nuestro buzón, como mucho 24 meses desde el último mensaje de esa conversación, salvo que haya una reclamación abierta o el mensaje sea un documento de la venta (entonces, el plazo fiscal de 6 años). Podéis pedirnos que lo borremos antes. De los que gestiona el sistema de IA, solo huellas y estados, como mucho 12 meses. En Anthropic, hasta 30 días, como se explica arriba.</li>
  <li>Borrador compartido entre dispositivos: 30 días.</li>
  <li>Medición de visitas y cifras agregadas: contadores agregados, 90 días.</li>
  <li>Reseñas publicadas: hasta que pidáis retirarlas o dejemos de ofrecer el servicio.</li>
  <li>Comunicaciones comerciales: hasta que os deis de baja. Guardamos la baja para respetarla.</li>
  <li>Lo que aprende y planifica el sistema de IA (lecciones y plan de la semana): no contiene datos personales y lo guardamos mientras sea útil. El registro de lo que gasta la IA, 6 años, porque forma parte de la contabilidad.</li>
  <li>Registros técnicos de seguridad: lo estrictamente necesario, normalmente unas semanas.</li>
</ul>

<h2><?= $V ?>, el vendedor</h2>
<p>La compra se la hacéis a <?= $V ?> (<?= h($L['vendedor_entidad'] ?? '') ?>). En su página de pago recoge vuestro nombre, email, dirección de facturación y los datos de pago, y los trata <strong>como responsable por su cuenta</strong>: para cobraros, emitir el recibo y la factura, liquidar el IVA, prevenir el fraude y cumplir sus propias obligaciones legales, según <a href="<?= h($L['vendedor_privacidad'] ?? '') ?>" rel="noopener">su política de privacidad</a>. Para ejercer vuestros derechos sobre esos datos, dirigíos a <?= $V ?>.</p>
<p>Nosotros le pasamos el email que ponéis en el creador, para rellenar su formulario, y un código interno del pedido. Lo poco que <?= $V ?> hace por encargo nuestro, como guardarnos la lista de pedidos en su panel, lo hace como encargado del tratamiento bajo su acuerdo de tratamiento. Está en Estados Unidos: la transferencia se ampara en las cláusulas contractuales tipo de la Comisión Europea que recoge ese acuerdo.</p>

<h2>Quién más los recibe y dónde están</h2>
<p>Estos son todos los destinatarios. No hay otros. Los que tratan datos por encargo nuestro lo hacen bajo contrato y solo con nuestras instrucciones.</p>
<ul>
  <li><?= $V ?>, como se explica en el apartado anterior.</li>
  <li>Hostinger, que aloja las webs y presta el servicio de correo (el buzón desde el que os escribimos y donde llegan vuestros mensajes), como encargado del tratamiento.</li>
  <li>Cloudflare, Inc. (Estados Unidos), como encargado del tratamiento: su red recibe las visitas a nuestras páginas y a las webs de boda (con vuestra dirección IP) y las entrega cifradas a nuestro alojamiento. No usamos sus funciones de analítica. La transferencia se ampara en las cláusulas contractuales tipo de la Comisión Europea que recoge su acuerdo de tratamiento.</li>
  <li>Anthropic, PBC (Estados Unidos), como encargado del tratamiento: recibe el texto de los correos a los que responde la IA y las cifras agregadas del negocio, como se explica arriba. La transferencia se ampara en las cláusulas contractuales tipo de la Comisión Europea (módulo dos, de responsable a encargado) que forman parte de su acuerdo de tratamiento de datos, incorporado a sus condiciones comerciales. No nos consta que Anthropic esté adherida al Marco de Privacidad de Datos UE-EE. UU., y no nos amparamos en él.</li>
  <li>Railway y Supabase, como encargados del tratamiento: en ellos funciona El Padrino y se guardan las huellas y los estados de los correos, las cifras agregadas del negocio y el registro de sus acciones. Sus acuerdos de tratamiento incluyen cláusulas contractuales tipo para las transferencias fuera de la Unión Europea.</li>
  <li>Telegram: recibimos en él avisos internos del sistema, con un título y un número de pedido, sin vuestro nombre ni email.</li>
  <li>OpenStreetMap (Fundación OpenStreetMap, Reino Unido): recibe la dirección del lugar de la ceremonia o el convite, que no es la vuestra, para dibujar el mapa de vuestra web.</li>
  <li>La Administración tributaria y las autoridades, cuando la ley nos obliga.</li>
</ul>
<p>No vendemos ni cedemos vuestros datos a nadie más.</p>

<h2>Transferencias fuera de la Unión Europea</h2>
<p>El responsable está en Indonesia, que no tiene una decisión de adecuación de la Comisión Europea, y nuestro equipo accede a los datos desde allí. Para los datos que tratamos por encargo de las parejas, la garantía son las cláusulas contractuales tipo de la Comisión Europea que forman parte del anexo de encargo de las condiciones del servicio. Los proveedores de Estados Unidos citados arriba se amparan en las cláusulas tipo de sus acuerdos de tratamiento, salvo que se indique otra cosa en su apartado. Podéis pedirnos una copia de esas garantías escribiendo a <?= email_enlace($E['email']) ?>.</p>

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

<h2>Vuestros derechos y cómo ejerceros</h2>
<p>Podéis pedirnos ver vuestros datos, corregirlos, borrarlos, limitar su uso, oponeros a su tratamiento, recibirlos en un formato que podáis llevar a otro sitio, retirar un consentimiento y pedir que una persona revise una decisión automática. Escribid a <?= email_enlace($E['email']) ?> diciéndonos qué queréis. Para identificaros os pediremos lo mínimo, por ejemplo, que escribáis desde el email de vuestro pedido. Os responderemos en un mes como máximo.</p>
<p>Los derechos que os da la ley indonesia (UU PDP) son equivalentes y se ejercen por el mismo canal. Si hay una brecha de seguridad que os afecte, os lo diremos a vosotros y a la autoridad competente en los plazos que marque la ley aplicable.</p>
<p>Si creéis que no hemos tratado bien vuestros datos, podéis reclamar ante la autoridad de protección de datos de vuestro país de residencia o de trabajo, por ejemplo la Agencia Española de Protección de Datos (aepd.es). Os agradeceremos que nos escribáis antes para intentar resolverlo.</p>
<p>No estáis obligados a darnos ningún dato, pero sin el email y el contenido de la web no podemos prestar el servicio.</p>
