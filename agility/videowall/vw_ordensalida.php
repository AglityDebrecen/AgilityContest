<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once(__DIR__."/../server/tools.php");
require_once(__DIR__."/../server/auth/Config.php");
require_once(__DIR__."/../server/auth/AuthManager.php");
$config =Config::getInstance();
$am = new AuthManager("Videowall::ordensalida");
if ( ! $am->allowed(ENABLE_VIDEOWALL)) { include_once("unregistered.php"); return 0;}
?>
<!--
vw_ordensalida.inc

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

<!-- Presentacion del orden de salida de la jornada -->
<div id="vw_ordensalida-window">
	<div id="vw_ordensalida-layout" style="width:100%">
		<div id="vw_ordensalida-Cabecera" data-options="region:'north',split:false" style="height:100px" class="vw_floatingheader">
            <img id="vw_header-logo" src="/agility/images/logos/rsce.png" style="float:left;width:75px" />
		    <span style="float:left;padding:10px;" id="vw_header-infoprueba"><?php _e('Header'); ?></span>
			<div style="float:right;padding:10px;text-align:right;">
                <span id="vw_header-texto"><?php _e('Starting order'); ?></span>&nbsp;-&nbsp;
                <span id="vw_header-ring"><?php _e('Ring'); ?></span>
                <br />
                <span id="vw_header-infomanga" style="width:200px">(<?php _e('No round selected'); ?>)</span>
            </div>
		</div>
		<div id="vw_tabla" data-options="region:'center'">
            <?php include_once(__DIR__."/../lib/templates/orden_salida.inc.php");?>
		</div>
        <div id="vw_ordensalida-footer" data-options="region:'south',split:false" class="vw_floatingfooter">
            <span id="vw_footer-footerData"></span>
        </div>
	</div>
</div> <!-- vw_ordensalida-window -->

<script type="text/javascript">

$('#vw_ordensalida-layout').layout({fit:true});

$('#vw_ordensalida-window').window({
    fit:true,
    noheader:true,
    border:false,
    closable:false,
    collapsible:false,
    collapsed:false,
    resizable:true,
    callback: null,
    onOpen: function() {
        startEventMgr();
    }
});

$('#ordensalida-datagrid').datagrid({
    queryParams: {
        Operation: 'getDataByTanda',
        Prueba: workingData.prueba,
        Jornada: workingData.jornada,
        Sesion: workingData.sesion // used only at startup. then use TandaID
    },
    onBeforeLoad:function(params) {
        // do not update until 'open' received
        if( $('#vw_header-infoprueba').html()==='<?php _e('Header'); ?>') return false;
        return true;
    }
});

    var eventHandler= {
        'null': null,// null event: no action taken
        'init': function(event) { // operator starts tablet application
            vw_updateWorkingData(event,function(evt,data){
                vw_updateHeaderAndFooter(evt,data);
                $('#vw_header-infomanga').html("(<?php _e('No round selected');?>)");
                // clear datagrid
                $('#ordensalida-datagrid').datagrid('loadData', {"total":0,"rows":[]});
            });
        },
        'open': function(event){ // operator select tanda
            vw_updateWorkingData(event,function(evt,data){
                vw_updateHeaderAndFooter(evt,data);
                vw_updateOrdenSalida(evt,data);
            });
        },
        'close': null,    // no more dogs in tanda
        'datos': null,      // actualizar datos (si algun valor es -1 o nulo se debe ignorar)
        'llamada': null,    // llamada a pista
        'salida': null,     // orden de salida
        'start': null,      // start crono manual
        'stop': null,       // stop crono manual
        // nada que hacer aqui: el crono automatico se procesa en el tablet
        'crono_start':  null, // arranque crono automatico
		'crono_restart': null,// paso de tiempo intermedio a manual
        'crono_int':  	null, // tiempo intermedio crono electronico
		'crono_stop':  null, // parada crono electronico
		'crono_reset':  null, // puesta a cero del crono electronico
		'crono_error':  null, // fallo en los sensores de paso
        'crono_dat':    null, // datos desde crono electronico
        'aceptar':	null, // operador pulsa aceptar
        'cancelar': null, // operador pulsa cancelar
        'camera':	null, // change video source
        'reconfig':	function(event) { loadConfiguration(); }, // reload configuration from server
        'info':	null // click on user defined tandas
    };

</script>