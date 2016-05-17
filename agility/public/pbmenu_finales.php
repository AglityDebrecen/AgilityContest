<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once(__DIR__."/../server/tools.php");
require_once(__DIR__."/../server/auth/Config.php");
require_once(__DIR__."/../server/auth/AuthManager.php");
$config =Config::getInstance();
$am = new AuthManager("Public::finales");
if ( ! $am->allowed(ENABLE_PUBLIC)) { include_once("unregistered.php"); return 0;}
?>
<!--
pbmenu_finales.php

Copyright  2013-2016 by Juan Antonio Martinez ( juansgaviota at gmail dot com )

This program is free software; you can redistribute it and/or modify it under the terms 
of the GNU General Public License as published by the Free Software Foundation; 
either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; 
if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 -->


<!-- Presentacion de resultados parciales -->

<div id="pb_finales-panel">
    <div id="pb_finales-layout" style="width:100%">
        <div id="pb_finales-Cabecera" style="height:20%" class="pb_floatingheader" data-options="region:'north',split:false,collapsed:false">

            <a id="pb_header-link" class="easyui-linkbutton" onClick="pb_updateFinales2(workingData.datosRonda);" href="#" style="float:left">
                <img id="pb_header-logo" src="/agility/images/logos/agilitycontest.png" width="40" />
            </a>
            <span style="float:left;padding:5px" id="pb_header-infocabecera"><?php _e('Header'); ?></span>
            <span style="float:right" id="pb_header-texto">
                <?php _e('Final scores'); ?><br/>
		        <span id="pb_enumerateFinales" style="width:200px"></span>
            </span>
            <!-- Datos de TRS y TRM -->
            <table class="pb_trs">
                <tbody>
                <tr>
                    <th id="pb_finales-NombreRonda" colspan="2" style="display:none">(<?php _e('No round selected'); ?>)</th>
                    <th id="pb_finales-Juez1" colspan="2" style="text-align:center"><?php _e('Judge'); ?> 1:</th>
                    <th id="pb_finales-Juez2" colspan="2" style="text-align:center"><?php _e('Judge'); ?> 2:</th>
                </tr>
                <tr style="text-align:right">
                    <td id="pb_finales-Ronda1"><?php _e('Data info for round'); ?> 1:</td>
                    <td id="pb_finales-Distancia1"><?php _e('Distance'); ?>:</td>
                    <td id="pb_finales-Obstaculos1"><?php _e('Obstacles'); ?>:</td>
                    <td id="pb_finales-TRS1"><?php _e('Standard C. Time'); ?>:</td>
                    <td id="pb_finales-TRM1"><?php _e('Maximum C. Time'); ?>:</td>
                    <td id="pb_finales-Velocidad1"><?php _e('Speed'); ?>:</td>
                </tr>
                <tr style="text-align:right">
                    <td id="pb_finales-Ronda2"><?php _e('Data info for round'); ?> 2:</td>
                    <td id="pb_finales-Distancia2"><?php _e('Distance'); ?>:</td>
                    <td id="pb_finales-Obstaculos2"><?php _e('Obstacles'); ?>:</td>
                    <td id="pb_finales-TRS2"><?php _e('Standard C. Time'); ?>:</td>
                    <td id="pb_finales-TRM2"><?php _e('Maximum C. Time'); ?>:</td>
                    <td id="pb_finales-Velocidad2"><?php _e('Speed'); ?>:</td>
                </tr>
                </tbody>
            </table>
        </div>
        <div id="pb_tabla" data-options="region:'center'">
                <table id="pb_resultados-datagrid">
                    <thead>
                    <tr>
                        <th colspan="8"> <span class="main_theader"><?php _e('Competitor data'); ?></span></th>
                        <th colspan="7"> <span class="main_theader" id="pb_resultados_thead_m1"><?php _e('Round'); ?> 1</span></th>
                        <th colspan="7"> <span class="main_theader" id="pb_resultados_thead_m2"><?php _e('Round'); ?> 2</span></th>
                        <th colspan="4"> <span class="main_theader"><?php _e('Final scores'); ?></span></th>
                    </tr>
                    <tr>
                        <!--
                        <th data-options="field:'Perro',		hidden:true " ></th>
                         -->
                        <th data-options="field:'Dorsal',		width:20, align:'left'" > <?php _e('Dors'); ?>.</th>
                        <th data-options="field:'LogoClub',		width:20, align:'left',formatter:formatLogoPublic" > &nbsp;</th>
                        <th data-options="field:'Nombre',		width:35, align:'center',formatter:formatBold"> <?php _e('Name'); ?></th>
                        <th data-options="field:'Licencia',		width:15, align:'center'" > <?php _e('Lic'); ?>.</th>
                        <th data-options="field:'Categoria',	width:15, align:'center',formatter:formatCategoria" > <?php _e('Cat'); ?>.</th>
                        <th data-options="field:'Grado',		width:15, align:'center', formatter:formatGrado" > <?php _e('Grd'); ?>.</th>
                        <th data-options="field:'NombreGuia',	width:50, align:'right'" > <?php _e('Handler'); ?></th>
                        <th data-options="field:'NombreClub',	width:45, align:'right'" > <?php _e('Club'); ?></th>
                        <th data-options="field:'F1',			width:15, align:'center',styler:formatBorder"> <?php _e('F/T'); ?></th>
                        <th data-options="field:'R1',			width:15, align:'center'"> <?php _e('R'); ?>.</th>
                        <th data-options="field:'T1',			width:25, align:'right',formatter:formatT1"> <?php _e('Time'); ?>.</th>
                        <th data-options="field:'V1',			width:16, align:'right',formatter:formatV1"> <?php _e('Vel'); ?>.</th>
                        <th data-options="field:'P1',			width:21, align:'right',formatter:formatP1"> <?php _e('Penal'); ?>.</th>
                        <th data-options="field:'C1',			width:23, align:'center'"> <?php _e('Cal'); ?>.</th>
                        <th data-options="field:'Puesto1',		width:15, align:'center'"> <?php _e('Pos'); ?>.</th>
                        <th data-options="field:'F2',			width:15, align:'center',styler:formatBorder"> <?php _e('F/T'); ?></th>
                        <th data-options="field:'R2',			width:15, align:'center'"> <?php _e('R'); ?>.</th>
                        <th data-options="field:'T2',			width:25, align:'right',formatter:formatT2"> <?php _e('Time'); ?>.</th>
                        <th data-options="field:'V2',			width:16, align:'right',formatter:formatV2"> <?php _e('Vel'); ?>.</th>
                        <th data-options="field:'P2',			width:21, align:'right',formatter:formatP2"> <?php _e('Penal'); ?>.</th>
                        <th data-options="field:'C2',			width:23, align:'center'"> <?php _e('Cal'); ?>.</th>
                        <th data-options="field:'Puesto2',		width:15, align:'center'"> <?php _e('Pos'); ?>.</th>
                        <th data-options="field:'Tiempo',		width:25, align:'right',formatter:formatTF,styler:formatBorder"><?php _e('Time'); ?></th>
                        <th data-options="field:'Penalizacion',	width:25, align:'right',formatter:formatPenalizacionFinal" > <?php _e('Penaliz'); ?>.</th>
                        <th data-options="field:'Calificacion',	width:20, align:'center'" > <?php _e('Calif'); ?>.</th>
                        <th data-options="field:'Puesto',		width:15, align:'center',formatter:formatPuestoFinalBig" ><?php _e('Position'); ?></th>
                    </tr>
                    </thead>
                </table>
        </div>
        <div id="pb_finales-footer" data-options="region:'south',split:false" style="height:10%;" class="pb_floatingfooter">
            <span id="pb_footer-footerData"></span>
        </div>
    </div>
</div> <!-- finales-window -->

<script type="text/javascript">

// in a mobile device, increase north window height
if (isMobileDevice()) {
    $('#pb_finales-Cabecera').css('height','90%');
}

addTooltip($('#pb_header-link').linkbutton(),'<?php _e("Update scores"); ?>');

$('#pb_finales-layout').layout({fit:true});
$('#pb_finales-panel').panel({
	fit:true,
	noheader:true,
	border:false,
	closable:false,
	collapsible:false,
	collapsed:false,
	resizable:true,
	// 1 minute poll is enouth for this, as no expected changes during a session
	onOpen: function() {
        // update header
        pb_getHeaderInfo();
        // update footer info
        pb_setFooterInfo();
		// call once and then fire as timed task
		pb_updateFinales2(workingData.datosRonda);

	}
});

$('#pb_resultados-datagrid').datagrid({
	// propiedades del panel asociado
	fit: true,
	border: false,
	closable: false,
	collapsible: false,
	collapsed: false,
	// propiedades del datagrid
	// no tenemos metodo get ni parametros: directamente cargamos desde el datagrid
	loadMsg: "<?php _e('Updating round scores');?>...",
	pagination: false,
	rownumbers: false,
	fitColumns: true,
	singleSelect: true,
    autoRowHeight:true, // let the formatters decide the size
	rowStyler:myRowStyler
});

// fire autorefresh if configured
setTimeout(function(){ $('#pb_enumerateFinales').text(workingData.datosRonda.Nombre)},0);
var rtime=parseInt(ac_config.web_refreshtime);
if (rtime!=0) {

    function update() {
        pb_updateFinales2(workingData.datosRonda);
        workingData.timeout=setTimeout(update,1000*rtime);
    }

    if (workingData.timeout!=null) clearTimeout(workingData.timeout);
    update();
}

</script>