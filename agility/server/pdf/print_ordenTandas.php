<?php
/*
print_ordenTandas.php

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
 * genera un pdf con la secuencia ordenada de tandas de la jornada y los participantes de cada tanda
*/

require_once(__DIR__."/fpdf.php");
require_once(__DIR__."/../tools.php");
require_once(__DIR__."/../logging.php");
require_once(__DIR__.'/../database/classes/DBObject.php');
require_once(__DIR__.'/../database/classes/Pruebas.php');
require_once(__DIR__.'/../database/classes/Jornadas.php');
require_once(__DIR__.'/../database/classes/Mangas.php');
require_once(__DIR__.'/../database/classes/Tandas.php');
require_once(__DIR__."/print_common.php");

class PrintTandas extends PrintCommon {

	protected $orden; // orden de tandas
	
	/**
	 * Constructor
	 * @param {integer} $prueba Prueba ID
	 * @param {integer} $jornada Jornada ID
	 * @throws Exception
	 */
	function __construct($prueba,$jornada) {
		date_default_timezone_set('Europe/Madrid');
		parent::__construct('Portrait',$prueba,$jornada);
		if ( ($prueba<=0) || ($jornada<=0) ) {
			$this->errormsg="printTandas: either prueba or jornada data are invalid";
			throw new Exception($this->errormsg);
		}
		// Datos del orden de salida
		$o = new Tandas("PrintTandas",$prueba,$jornada);
		$ot= $o->getTandas();
		$this->orden=$ot['rows'];
	}
	
	// Cabecera de página
	function Header() {
		// cabecera comun
		$this->print_commonHeader("Programa de la jornada");
		// pintamos identificacion de la jornada
		$this->SetFont('Arial','B',12); // Arial bold 15pt
		$str  = "Jornada: {$this->jornada->Nombre} - {$this->jornada->Fecha}";
		$this->Cell(100,7,$str,0,0,'L',false); // a un lado nombre y fecha de la jornada
		$this->Ln(5);
		$str  = "Hora de comienzo: {$this->jornada->Hora}";
		$this->Cell(90,7,$str,0,0,'L',false); // a un lado nombre y fecha de la jornada
		$this->Ln(10);
	}
	
	// Pie de página
	function Footer() {
		$this->print_commonFooter();
		// Pone un warning sobre la hora estimada
		$this->SetXY(10,-20);
		$this->SetFont('Arial','IB',10);
		$this->Cell(190,5,'(*) IMPORTANTE: La hora indicada es una estimación que NO TIENE consideración de horario oficial',0,0,'L');
	}
	
	function evalTime($time) {
		$str="{$this->jornada->Fecha} {$this->jornada->Hora}";
		$timestamp= strtotime($str);
		$timestamp+=$time;
		return date("H:i:s",$timestamp);
	}
	
	function writeTableHeader() {
		$this->myLogger->enter();
		$this->ac_header(1,10);
		$this->setX(10);
		$this->Cell(75,7,"Actividad",'TLBR',0,'L',true);
		$this->Cell(25,7,"Ring",'TLB',0,'C',true);
		$this->Cell(15,7,"# Participantes",'TLB',0,'C',true);
		$this->Cell(60,7,"Observaciones",'TLB',0,'R',true);
		$this->Cell(15,7,"Horario (*)",'TLBR',0,'C',true);
		$this->Ln();
		$this->myLogger->leave();
	}
	
	// Tabla coloreada
	function composeTable() {
		$this->myLogger->enter();
		
		$this->ac_SetDrawColor($this->config->getEnv('pdf_linecolor'));
		$this->SetLineWidth(.3);
		$rowcount=0;
		foreach($this->orden as $row) {
			// $this->cell(width,height,text,border,start,align,fill)
			if (($rowcount%30)==0) {
				$this->addPage();
				$this->writeTableHeader();
			}
			// imprimimos numero de orden
			$this->ac_header(2,12);
			$this->Cell(10,7,$rowcount+1,'LBR',0,'C',true);
			// imprimimos nombre de la tanda
			$this->ac_row($rowcount,8);
			$this->SetFont('Arial','B',8);
			$this->Cell(65,7,$row['Nombre'],'LBR',0,'R',true); // nombre en negritas
			$this->SetFont('Arial','',8);
			$this->Cell(25,7,$row['NombreSesion'],'LBR',0,'R',true);
			if ($row['Tipo']!=0) {
				$str="( Prueba={$row['Prueba']} ) AND ( Jornada={$row['Jornada']} ) AND (Manga={$row['Manga']})";
				$result=$this->myDBObject->__select("*","Resultados",$str,"","");
				if (!is_array($result)) {
				$this->myLogger->error($result); return $result; }
				// comparamos categoria y grado
				$count=0;
				foreach($result['rows'] as $item) {
					// si el grado es '-' se contabiliza. else si coincide grado se contabiliza
					if (($row['Grado']!=='-') && ($item['Grado']!==$row['Grado']) ) continue;
					// comparamos categorias
					if ( strstr($row['Categoria'],$item['Categoria'])===false ) continue;
					$count++;
				}
				$this->Cell(15,7,$count,'LBR',0,'C',true);
			} else {
				$this->Cell(15,7,"----",'LBR',0,'C',true);
			}
			$this->Cell(60,7,$row['Comentario'],'LB',0,'C',true);
			$this->Cell(15,7,$row['Horario'],'LBR',0,'C',true);
			$rowcount++;
			$this->Ln(7);
		}
		$this->myLogger->leave();
	}
}

// Consultamos la base de datos
try {
	$prueba=http_request("Prueba","i",0);
	$jornada=http_request("Jornada","i",0);
	// 	Creamos generador de documento
	$pdf = new PrintTandas($prueba,$jornada);
	$pdf->AliasNbPages();
	$pdf->composeTable();
	$pdf->Output("ordenTandas.pdf","D"); // "D" means open download dialog	
} catch (Exception $e) {
	die ("Error accessing database: ".$e->getMessage());
};
?>