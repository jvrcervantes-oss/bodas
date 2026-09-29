<?php
// Textos legales cortos de los formularios. Texto plano, sin HTML.
// Marcadores que sustituye textos_legales() en TODOS los textos (un solo sitio, para que lo que se
// muestra en la casilla sea literalmente lo que se guarda en el pedido): {titular} {marca} {vendedor}.
// rsvp_capa1 y libro_capa1 llevan además los suyos, que pone quien los pinta: {pareja} {email} {borrado}.
// El enlace a /privacidad lo pone la app sobre la frase final «Más información en el aviso de privacidad.».
//
// VENDEDOR (Merchant of Record): dato único. Lo leen condiciones, privacidad, aviso legal y el correo de
// bienvenida. Lemon Squeezy cambió de entidad (Sold through Link, LLC) y anunció el 28-ene-2026 su
// migración a Stripe Managed Payments sin fechas: si cambia, se cambia AQUÍ y se sube 'version'.
// Si algún día se reactiva `pasarela = stripe`, estos textos NO sirven: Stripe no es vendedor (Legal, 27-sep-2026).
return [
    // 2026-09-27e: cambia el texto de las condiciones que acepta check_condiciones (§2 y §5 ter: el plano de mesas va
    // incluido en los packs). La versión que se guarda con cada aceptación es esta, así que sube con las condiciones.
    // 2026-09-29: §1 (servicio por invitación con códigos de regalo en pruebas), licencia del ZIP (§11) y anexo II también al canjear (Legal).
    'version' => '2026-09-29',

    'vendedor' => 'Lemon Squeezy',
    'vendedor_entidad' => 'Sold through Link, LLC (antes Lemon Squeezy LLC), Estados Unidos',
    'vendedor_terminos' => 'https://www.lemonsqueezy.com/buyer-terms',
    'vendedor_privacidad' => 'https://www.lemonsqueezy.com/privacy',

    'rsvp_capa1' => 'Tus respuestas las reciben {pareja} para organizar su boda. {marca} aloja esta web y guarda los datos por encargo suyo. Las alergias solo se usan para el menú y se pasan al catering. Todo se borra el {borrado}. Para ver, corregir o borrar tus datos, escribe a {email}. Más información en el aviso de privacidad.',

    // F1b (resumen para el catering) y F1d (plano de mesas), owner opción A del 27-sep: la casilla se mantiene (art. 9.2.a;
    // la excepción doméstica no cubre al prestador, considerando 18) y cubre también pasarlas al catering con nombre y mesa.
    // Solo se guarda 'version' en la respuesta (boda.php): cambiar esta frase exige subir 'version'.
    'check_alergias' => 'Doy mi consentimiento explícito para que la pareja use las alergias o intolerancias que indico solo para preparar el menú, y para que se las pase al catering con mi nombre y, si hay plano de mesas, mi mesa. Si indico las de otras personas de mi grupo, ellas lo saben y están de acuerdo en lo mismo.',

    'check_acompanantes' => 'Si doy datos de otras personas de mi grupo, les he avisado y están de acuerdo, o soy su padre, madre o tutor. Les enseñaré este aviso.',

    'check_condiciones' => 'He leído y acepto las condiciones del servicio de {marca}, incluido el anexo de encargo de tratamiento de los datos de nuestros invitados. Sé que quien nos vende y nos cobra es {vendedor}, con sus condiciones de compra.',

    // Web regalada con código de cortesía: no hay venta, así que no nombra al vendedor. El creador cambia la
    // casilla a este texto al escribir un código y el servidor guarda este mismo en el pedido del regalo.
    'check_condiciones_regalo' => 'He leído y acepto las condiciones del servicio de {marca}, incluido el anexo de encargo de tratamiento de los datos de nuestros invitados.',

    // Libro de invitados y galería (revisión previa #87; el owner decidió publicación inmediata y código de acceso)
    'libro_capa1' => 'Tu nombre, tu mensaje y tu foto se publican al momento en esta web y los verá cualquiera que tenga el enlace y el código de la boda. Los reciben {pareja}, que pueden ocultarlos o borrarlos. Todo se borra el {borrado}. Para que se retire algo, escribe a {email}. Más información en el aviso de privacidad.',

    'check_libro_publicar' => 'Entiendo que mi nombre y mi mensaje se publican en la web de la boda.',

    'check_foto_libro' => 'La foto es mía o tengo permiso para publicarla, y las personas que salen están de acuerdo. Si salen menores, soy su padre, madre o tutor o tengo su permiso.',

    'check_galeria_pareja' => 'Tenemos derecho a publicar estas fotos y las personas que salen, o sus padres si son menores, están de acuerdo.',

    // Art. 103.m TRLGDCU: consentimiento expreso + reconocimiento de la pérdida, ANTES de pagar, solo para la
    // creación y publicación. El alojamiento es servicio (103.a): desistible con pago proporcional (108.3), y hay
    // que avisarlo aquí o el 108.4 obliga a devolverlo todo (consulta fresca de Legal, 27-sep). Sin renuncias
    // previas («no se puede pedir reembolso» sería nula, art. 10), y se guarda literal en el pedido. Nombra solo la
    // marca (owner + Legal, 27-sep tarde): la identidad del titular (97.1.b-c) va en condiciones y aviso legal,
    // enlazados junto a la casilla, y el vendedor ya lo nombra la casilla de condiciones del mismo paso.
    'check_desistimiento' => 'Pedimos a {marca} que cree y publique nuestra web ya, antes de que acaben los 14 días para desistir. Sabemos que, en cuanto se publique, perdemos el derecho de desistimiento sobre la creación y publicación de la web, y que si desistimos del alojamiento dentro de esos 14 días pagaremos la parte proporcional a lo ya prestado.',

    // Mejora Esencial → Atelier desde el panel (condiciones, apartado 5 bis). La web ya está publicada, así que
    // la casilla del alta («que cree y publique») sería falsa. Misma estructura del 103.m: petición expresa +
    // reconocimiento de la pérdida. Nombra solo la marca (Legal, 27-sep tarde) PORQUE junto a la casilla van los
    // enlaces a las condiciones y a las de compra del vendedor (crear.js, pintaAtelier): sin ellos, vuelve a hacer
    // falta «Sabemos que la compra la hacemos a {vendedor}». Se guarda literal en el pedido de la mejora y el
    // correo de confirmación de la mejora tiene que repetirla (art. 98.7).
    'check_mejora' => 'Pedimos a {marca} que active ya el Pack Atelier en nuestra web, con las condiciones del servicio que ya aceptamos. Sabemos que, en cuanto se active, perdemos el derecho de desistimiento de esta mejora.',

    // Extras de pago desde el panel (condiciones, apartado 5 ter; Legal #133). Un extra es un SERVICIO (103.a TRLGDCU),
    // no la excepción 103.m de la mejora: se puede desistir en 14 días, y como la pareja pide que empiece ya, paga la
    // parte proporcional (108.3). Sin la petición expresa de empezar DENTRO de los 14 días, el 108.4 obliga a devolverlo
    // todo: por eso «antes de que acaben los 14 días». Es la ÚNICA casilla del formulario del extra, así que también
    // acepta las condiciones vigentes (la pareja aceptó al comprar la web una versión que quizá no tenía el 5 ter).
    // Nombra solo la marca PORQUE junto a la casilla van el nombre y el precio del extra y los enlaces a las condiciones
    // y a las de compra del vendedor (app/extras.php, extra_presentacion). Se guarda literal en extras/<token>.json
    // (aceptacion.casilla) y la repite el correo del extra (art. 98.7). Cambiarla exige subir 'version'.
    'check_extra' => 'Pedimos a {marca} que active ya este extra en nuestra web, antes de que acaben los 14 días para desistir, y aceptamos para él las condiciones del servicio vigentes. Sabemos que, si desistimos dentro de esos 14 días, pagaremos la parte proporcional a lo ya prestado y el extra se desactivará.',
];
