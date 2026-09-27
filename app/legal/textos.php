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
    'version' => '2026-09-27',

    'vendedor' => 'Lemon Squeezy',
    'vendedor_entidad' => 'Sold through Link, LLC (antes Lemon Squeezy LLC), Estados Unidos',
    'vendedor_terminos' => 'https://www.lemonsqueezy.com/buyer-terms',
    'vendedor_privacidad' => 'https://www.lemonsqueezy.com/privacy',

    'rsvp_capa1' => 'Tus respuestas las reciben {pareja} para organizar su boda. {marca} aloja esta web y guarda los datos por encargo suyo. Las alergias solo se usan para el menú. Todo se borra el {borrado}. Para ver, corregir o borrar tus datos, escribe a {email}. Más información en el aviso de privacidad.',

    'check_alergias' => 'Doy mi consentimiento explícito para que la pareja use las alergias o intolerancias que indico solo para preparar el menú. Si indico las de otras personas de mi grupo, ellas lo saben y están de acuerdo.',

    'check_acompanantes' => 'Si doy datos de otras personas de mi grupo, les he avisado y están de acuerdo, o soy su padre, madre o tutor. Les enseñaré este aviso.',

    'check_condiciones' => 'He leído y acepto las condiciones del servicio de {marca}, que presta {titular}, incluido el anexo de encargo de tratamiento de los datos de nuestros invitados. Sé que quien nos vende y nos cobra es {vendedor}, con sus condiciones de compra.',

    // Web regalada con código de cortesía: no hay venta, así que no nombra al vendedor. El creador cambia la
    // casilla a este texto al escribir un código y el servidor guarda este mismo en el pedido del regalo.
    'check_condiciones_regalo' => 'He leído y acepto las condiciones del servicio de {marca}, que presta {titular}, incluido el anexo de encargo de tratamiento de los datos de nuestros invitados.',

    // Libro de invitados y galería (revisión previa #87; el owner decidió publicación inmediata y código de acceso)
    'libro_capa1' => 'Tu nombre, tu mensaje y tu foto se publican al momento en esta web y los verá cualquiera que tenga el enlace y el código de la boda. Los reciben {pareja}, que pueden ocultarlos o borrarlos. Todo se borra el {borrado}. Para que se retire algo, escribe a {email}. Más información en el aviso de privacidad.',

    'check_libro_publicar' => 'Entiendo que mi nombre y mi mensaje se publican en la web de la boda.',

    'check_foto_libro' => 'La foto es mía o tengo permiso para publicarla, y las personas que salen están de acuerdo. Si salen menores, soy su padre, madre o tutor o tengo su permiso.',

    'check_galeria_pareja' => 'Tenemos derecho a publicar estas fotos y las personas que salen, o sus padres si son menores, están de acuerdo.',

    // Art. 103.m TRLGDCU: consentimiento expreso + reconocimiento de la pérdida, ANTES de pagar. Nombra a
    // quien presta (el titular) y a quien vende (LS), y se guarda literal en el pedido (Legal #109).
    'check_desistimiento' => 'Pido a {titular} ({marca}) que cree y publique nuestra web ya, antes de que acaben los 14 días para desistir. Sé que, en cuanto se publique, perdemos el derecho de desistimiento, también frente a {vendedor}, que es quien nos la vende, y que por eso no se puede pedir el reembolso por cambio de opinión.',

    // Mejora Esencial → Atelier desde el panel (condiciones, apartado 5 bis). La web ya está publicada, así que
    // la casilla del alta («que cree y publique») sería falsa. Misma estructura del 103.m: petición expresa +
    // reconocimiento de la pérdida, nombrando a quien presta y a quien vende. Se guarda literal en el pedido
    // de la mejora y el correo de confirmación de la mejora tiene que repetirla (art. 98.7).
    'check_mejora' => 'Pido a {titular} ({marca}) que active ya el Pack Atelier en nuestra web, con las condiciones del servicio que ya aceptamos. Sé que, en cuanto se active, perdemos el derecho de desistimiento de esta mejora, también frente a {vendedor}, que es quien nos la vende, y que por eso no se puede pedir su reembolso por cambio de opinión.',
];
