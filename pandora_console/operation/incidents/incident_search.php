<?php

// Pandora FMS - the Free monitoring system
// ========================================
// Copyright (c) 2004-2007 Sancho Lerena, slerena@openideas.info
// Copyright (c) 2005-2007 Artica Soluciones Tecnologicas
// Copyright (c) 2004-2007 Raul Mateos Martin, raulofpandora@gmail.com
// Copyright (c) 2006-2007 Jose Navarro jose@jnavarro.net
// Copyright (c) 2006-2007 Jonathan Barajas, jonathan.barajas[AT]gmail[DOT]com

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation version 2
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
// Load global vars
require("include/config.php");

if (comprueba_login() == 0) {

echo "<h2>".$lang_label["incident_manag"]."</h2>";
echo "<h3>".$lang_label["find_crit"]." <a href='help/".$help_code."/chap4.php#43' target='_help' class='help'>&nbsp;<span>".$lang_label["help"]."</span></a></h3>";
echo "<div style='width:645'>";
echo "<div style='float:right;'><img src='images/pulpo_lupa.gif' class='bot' align='left'></div>";	
?>
<div style='float:left;'>
<table width="500" cellpadding="3" cellspacing="3">
<form name="busqueda" method="post" action="index.php?sec=incidencias&sec2=operation/incidents/incident">
<td class='lb' rowspan="4" width="5">
<tr>
<td class="datos"><?php echo $lang_label["user"] ?>
<td class="datos">
<select name="usuario" class="w120">
	<option value=""><?php echo $lang_label["all"] ?>
	<?php 
	$sql1='SELECT * FROM tusuario ORDER BY id_usuario';
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		echo "<option>".$row["id_usuario"];
	}
	?>
</select>
<tr><td class="datos2"><?php echo $lang_label["free_text_search"] ?>
<td class="datos2"><input type="text" size="45" name="texto"></tr>
<tr><td class="datos" colspan="2"><i><?php echo $lang_label["free_text_search_msg"] ?></i></td></tr>
<tr><td colspan='3'><div class='raya'></div></td></tr>
<tr><td align="right" colspan="3">
<?php echo "<input name='uptbutton' type='submit' class='sub' value='".$lang_label["search"]."'>"; ?>

</form>
</table>
</div>
</div>
<?php 

} // end page
?>