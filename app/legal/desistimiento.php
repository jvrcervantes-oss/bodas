<?php
// Anexo I de las condiciones: modelo de formulario de desistimiento. Una sola fuente para la página /condiciones (lo
// incluye condiciones.php) y para el cuerpo del correo de bienvenida (documento_legal('desistimiento')): desde que las
// condiciones van por enlace (owner, 17-oct-2026), el modelo tiene que seguir DENTRO de la confirmación del art. 98.7,
// que debe llevar la información del 97.1 (Legal, 17-oct: sin él el plazo de desistimiento se alarga 12 meses, art. 71.3).
// $E lo define quien lo incluye; $V y $M, condiciones.php (si no, se calculan aquí).
$V = $V ?? h(textos_legales()['vendedor'] ?? '');
$M = $M ?? h(marca());
?>
<h2>Anexo I. Modelo de formulario de desistimiento</h2>
<p>Sirve para desistir del alojamiento o de un extra (apartado 5 ter) en los 14 días siguientes a su compra, o en cualquier otro caso en que la ley os reconozca el derecho de desistimiento (apartado 8).</p>
<p>A la atención de <?= h($E['titular']) ?><?= $E['titular'] !== marca() ? ' (' . $M . ')' : '' ?>, <?= $E['domicilio'] !== '' ? h($E['domicilio']) . ', ' : '' ?><?= email_enlace($E['email']) ?>, o de <?= $V ?>:</p>
<ul>
  <li>Por la presente os comunico que desisto del contrato (del alojamiento, o del extra ____________) de la web de boda publicada en: ____________</li>
  <li>Fecha de compra y número de pedido de <?= $V ?>: ____________</li>
  <li>Nombre de quien compró: ____________</li>
  <li>Dirección de quien compró: ____________</li>
  <li>Firma (solo si se envía en papel): ____________</li>
  <li>Fecha: ____________</li>
</ul>
