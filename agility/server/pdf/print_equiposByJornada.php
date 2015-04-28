<?php
/*
print_equiposByJornada.php

Copyright 2013-2015 by Juan Antonio Martinez ( juansgaviota at gmail dot com )

This program is free software; you can redistribute it and/or modify it under the terms 
of the GNU General Public License as published by the Free Software Foundation; 
either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; 
if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/


header('Set-Cookie: fileDownload=true; path=/');
// mandatory 'header' to be the first element to be echoed to stdout

/**
 * genera un pdf ordenado con los participantes en jornada de prueba por equipos
*/

require_once(__DIR__."/fpdf.php");
require_once(__DIR__."/../tools.php");
require_once(__DIR__."/../logging.php");
require_once(__DIR__.'/../database/classes/DBObject.php');
require_once(__DIR__.'/../database/classes/Pruebas.php');
require_once(__DIR__.'/../database/classes/Jornadas.php');
require_once(__DIR__.'/../database/classes/Equipos.php');
require_once(__DIR__."/print_common.php");

class PDF extends PrintCommon {

	protected $prueba; // datos de la prueba
	protected $jornada; // datos de la jornada
	protected $equipos; // lista de equipos de esta jornada
    protected $perros; // lista de participantes en esta jornada
	
	// geometria de las celdas
	protected $cellHeader;
    //                      Dorsal  nombre raza licencia Categoria guia club  celo  observaciones
	protected $pos	=array( 10,     25,     27,    10,    15,      40,   25,  10,    26);
	protected $align=array( 'R',    'C',    'R',    'C',  'L',     'R',  'R', 'C',   'R');
	protected $cat  =array("-" => "","L"=>"Large","M"=>"Medium","S"=>"Small","T"=>"Tiny");
	
	/**
	 * Constructor
     * @param {integer} $prueba Prueba ID
     * @param {integer} $jornada Jormada ID
     * @param {integer} $manga Manga ID
	 * @throws Exception
	 */
	function __construct($prueba,$jornada) {
		parent::__construct('Portrait',$prueba,$jornada);
		if ( ($prueba<=0) || ($jornada<=0) ) {
			$this->errormsg="print_teamsByJornada: either prueba or jornada data are invalid";
			throw new Exception($this->errormsg);
		}
		// Datos de prueba y jornada
        $m = new Pruebas("print_teamsByJornada");
        $this->prueba= $m->selectByID($prueba);
        $m = new Jornadas("print_teamsByJornada",$prueba);
        $this->jornada= $m->selectByID($jornada);
        // comprobamos que estamos en una jornada por equipos
        $flag=intval($this->jornada['Equipos3'])+intval($this->jornada['Equipos4']);
        if ($flag==0) {
            $this->errormsg="print_teamsByJornada: Jornada $jornada has no Team competition declared";
            throw new Exception($this->errormsg);
        }
		// Datos de equipos de la jornada
        $m=new Equipos("print_teamsByJornada",$prueba,$jornada);
        $this->teams=$m->getTeamsByJornada();
        // Datos de los participantes (indexados por ID de perro)
        $m=new DBObject("print_teamsByJornada");
        $r=$m->__select("*","Resultados","(Jornada=$jornada)","","");
        $this->perros=array();
        foreach($r['rows'] as $item) $this->perros[$item['ID']]=$item;
        // finalmente internacionalizamos cabeceras
		$this->cellHeader = 
				array(_('Dorsal'),_('Nombre'),_('Raza'),_('Lic.'),_('Cat.'),_('Guía'),_('Club'),_('Celo'),_('Observaciones'));
	}
	
	// Cabecera de página
	function Header() {
		$this->print_commonHeader(_("Listado de Equipos"));
	}
	
	// Pie de página
	function Footer() {
		$this->print_commonFooter();
	}
	
	function writeTableHeader() {
		// $this->myLogger->enter();
		// Colores, ancho de línea y fuente en negrita de la cabecera de tabla
		$this->ac_header(1,9);
		for($i=0;$i<count($this->cellHeader);$i++) {
			// en la cabecera texto siempre centrado
			$this->Cell($this->pos[$i],7,$this->cellHeader[$i],1,0,'C',true);
		}
		// Restauración de colores y fuentes
		$this->ac_row(2,9);
		$this->Ln();
		// $this->myLogger->leave();
	}
	
	function printTeamInformation($team) {
		$this->ac_header(2,9);
		$nombre=$this->teams[$team]['Nombre'];
		$this->Cell(185,6,$nombre,'LTBR',0,'R',true);
		$this->ac_row(2,9);
		$this->Ln();
	}
	
	// Tabla coloreada
	function composeTable() {
		$this->myLogger->enter();
        $bg1=$this->config->getEnv('pdf_rowcolor1');
        $bg2=$this->config->getEnv('pdf_rowcolor2');
        $this->ac_SetFillColor($bg1);
        $this->ac_SetDrawColor($this->config->getEnv('pdf_linecolor'));
		$this->SetLineWidth(.3);
		foreach($this->teams as $team) {
			$newTeam=intval($row['Equipo']);
			// REMINDER: $this->cell( width, height, data, borders, where, align, fill)
			// if change in categoria, reset orden counter and force page change
			if ($row['Categoria'] !== $this->categoria) {
				$this->categoria = $row['Categoria'];
				$this->Cell(array_sum($this->pos),0,'','T'); // forzamos linea de cierre
				$rowcount=0;
				$order=0;
				$lastTeam=0;
			} 
			if ($this->isTeam()) {
				// team change: make sure that new team fits in page
				if ($newTeam!=$lastTeam) {
					if ($rowcount>=32) $rowcount=37; // team change: seek at end of page
				}
			}
			if ( ($rowcount==0) || ($rowcount>=37) ) { // assume 38 rows per page ( rowWidth = 6mmts )
				if ($rowcount>0) $this->Cell(array_sum($this->pos),0,'','T'); // linea de cierre en cambio de pagina
				$rowcount=0;
				$this->addPage();
				$this->writeTableHeader();
				$lastTeam=0; // force writting of team header information
			}
			// on team Events and team change add Team header information
			if ( $this->isTeam() && ($newTeam!=$lastTeam) ) {
				$lastTeam=$newTeam;
				$this->printTeamInformation($lastTeam);
				$rowcount++;
			}
            $this->ac_SetFillColor( (($order&0x01)==0)?$bg1:$bg2);
			$this->SetFont('Arial','B',11); // bold 9px
			$this->Cell($this->pos[0],6,($order+1)." - ",'LR',0,$this->align[0],true); // display order
			$this->SetFont('Arial','',9); // remove bold 9px
			$this->Cell($this->pos[1],6,$row['Dorsal'],		'LR',0,$this->align[1],true);
            $this->SetFont('Arial','B',11); // bold 9px
            $this->Cell($this->pos[2],6,$row['Nombre'],		'LR',0,$this->align[2],true);
            $this->SetFont('Arial','',9); // remove bold 9px
            $this->Cell($this->pos[3],6,$row['Raza'],		'LR',0,$this->align[3],true);
            $this->Cell($this->pos[4],6,$row['Licencia'],	'LR',0,$this->align[4],true);
            $this->Cell($this->pos[5],6,$row['Categoria'],	'LR',0,$this->align[5],true);
			$this->Cell($this->pos[6],6,$row['NombreGuia'],	'LR',0,$this->align[6],true);
			$this->Cell($this->pos[7],6,$row['NombreClub'],	'LR',0,$this->align[7],true);
			$this->Cell($this->pos[8],6,($row['Celo']==0)?"":_("Celo"),	'LR',0,$this->align[8],true);
			$this->Cell($this->pos[9],6,$row['Observaciones'],'LR',0,$this->align[9],true);
			$this->Ln();
			$rowcount++;
			$order++;
		}
		// Línea de cierre
		$this->Cell(array_sum($this->pos),0,'','T');
		$this->myLogger->leave();
	}
}

// Consultamos la base de datos
try {
	$prueba=http_request("Prueba","i",0);
	$jornada=http_request("Jornada","i",0);
	// 	Creamos generador de documento
	$pdf = new PDF($prueba,$jornada);
	$pdf->AliasNbPages();
	$pdf->composeTable();
	$pdf->Output("equiposByJornada.pdf","D"); // "D" means open download dialog
} catch (Exception $e) {
	die ("Error accessing database: ".$e->getMessage());
};
?>