<!-- 
pb_inscripciones.inc

Copyright 2013-2015 by Juan Antonio Martinez ( juansgaviota at gmail dot com )

This program is free software; you can redistribute it and/or modify it under the terms 
of the GNU General Public License as published by the Free Software Foundation; 
either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; 
if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 -->

<!-- Presentacion de las inscripciones de la jornada -->
<div id="pb_inscripciones-window">
	<div id="pb_inscripciones-layout" style="width:100%">
		<div id="pb_inscripciones-Cabecera" data-options="region:'north',split:false" style="height:10%" class="vw_floatingheader">
		    <span style="float:left;" id="pb_inscripciones-infocabecera">Cabecera</span>
			<span style="float:right;" id="pb_inscripciones-texto">Listado de inscritos</span>
		</div>
		<div id="pb_tabla" data-options="region:'center'">
			<span id="pb_inscripcionesJornada"></span>
		</div>
	</div>
</div> <!-- pb_inscripciones-window -->

<script type="text/javascript">
$('#pb_inscripciones-layout').layout({fit:true});

$('#pb_inscripciones-window').window({
	fit:true,
	noheader:true,
	border:false,
	closable:false,
	collapsible:false,
	collapsed:false,
	resizable:true,
	callback: null, 
	// 1 minute poll is enouth for this, as no expected changes during a session
	onOpen: function() {
		// call once and then fire as timed task
		pb_updateInscripciones();
		var a=setInterval(pb_updateInscripciones,10000);
		$(this).window.defaults.callback=a; 
	},
	onClose: function() { 
		clearInterval($(this).window.defaults.callback);
	}
});

</script>