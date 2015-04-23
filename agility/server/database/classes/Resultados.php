<?php
/*
Resultados.php

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


require_once("DBObject.php");
require_once("OrdenSalida.php");
require_once("Mangas.php");
require_once("Jornadas.php");

class Resultados extends DBObject {
	protected $IDManga; // ID de la manga
	protected $IDJornada; // ID de la jornada
	protected $dmanga=null; // datos de la manga
	protected $djornada=null;  // datos de la jornada

	private function getDatosManga() {
		if ($this->dmanga!=null) return $this->dmanga;
		$idmanga=$this->IDManga;
		// si no los tenemos todavia consultamos la base de datos
		$obj=$this->__getObject("Mangas", $idmanga);
		if (!is_object($obj)) {
			$this->error("Manga $idmanga does not exists in database");
			return null;
		}
		// add some extra info
		$obj->TipoManga=Mangas::$tipo_manga[$obj->Tipo][1];
		$this->dmanga=$obj;
		return $this->dmanga;
	}
	
	private function getDatosJornada() {
		if ($this->djornada!=null) return $this->djornada;
		$manga=$this->getDatosManga();
		$this->IDJornada=$manga->Jornada;
		$idjornada=$this->IDJornada;
		$idmanga=$this->IDManga;
		$obj=$this->__getObject("Jornadas", $this->IDJornada);
		if (!is_object($obj)) {
			$this->error("Cannot locate JornadaID: $idjornada for MangaID:$idmanga in database");
			return null;
		}
		$this->djornada=$obj;
		return $this->djornada;
	}
	
	private function isCerrada() {
		$jrd=$this->getDatosJornada();
		return 	($jrd->Cerrada!=0)? true:false;
	}

	/**
	 * Constructor
	 * @param {string} $file caller for this object
	 * @param {integer} $manga Manga ID
	 * @throws Exception when
	 * - cannot contact database
	 * - invalid manga ID
	 * - manga is closed
	 */
	function __construct($file,$prueba,$manga) {
		parent::__construct($file);
		if ($manga<=0) {
			$this->errormsg="Resultados::Construct invalid Manga ID: $manga";
			throw new Exception($this->errormsg);
		}
		if ($prueba<=0) {
			$this->errormsg="Resultados::Construct invalid Prueba ID: $prueba";
			throw new Exception($this->errormsg);
		}
		$this->IDPrueba=$prueba;
		$this->IDJornada=0; // to be filled
		$this->IDManga=$manga;
		$this->dprueba=null;
		$this->dmanga=null;
		$this->djornada=null;
	}
	
	/**
	 * gets distance, obstacles, trs and trm
	 * @param {integer} $mode 0:Large 1:Medium 2:Small 3:M+S 4:L+M+S
	 * @param {array} current results data according $mode 
	 * @return array('dist','obst','trs','trm','vel') or null on error
	 */
	private function evalTRS($mode,$data) {
		$dmanga=(array) $this->getDatosManga();
		$result= array();
		// vemos de donde tenemos que tomar los datos
		$suffix='L';
		switch($mode) {
			case 0: $suffix='L'; break; // L
 			case 1: $suffix='M'; break; // M
			case 2: $suffix='S'; break; // S
			case 3: $suffix='M'; break; // M+S
			case 4: $suffix='L'; break; // L+M+S
			// extra values for rfec contests
			case 5: $suffix='T'; break; // T
			case 6: $suffix='L'; break; // L+M
			case 7: $suffix='S'; break; // S+T
			case 8: $suffix="L"; break; // L+M+S+T
		}
		// evaluamos distancia y obstaculos
		$result['dist']= $dmanga["Dist_$suffix"]; 
		$result['obst']= $dmanga["Obst_$suffix"];
		// evaluamos mejor tiempo y media de los tres mejores
		$best1=0;
		$best3=0;
		if (count($data)==0) { $best1=0; $best3=0;} // no hay ni resultados ni tiempos
		if (count($data)==1) { $best1=$data[0]['Tiempo']; $best3=$data[0]['Tiempo'];}
		if (count($data)==2) { $best1=$data[0]['Tiempo']; $best3=($data[0]['Tiempo']+$data[1]['Tiempo'])/2;}
		if (count($data)>=3) { $best1=$data[0]['Tiempo']; $best3=($data[0]['Tiempo']+$data[1]['Tiempo']+$data[2]['Tiempo'])/3;}
		// Evaluamos TRS
		switch ($dmanga["TRS_{$suffix}_Tipo"]) {
			// NOTA IMPORTANTE: 
			// No se hace chequeo de tipos, con lo que si por error en un calculo de TRS standard se pide un tipo STD+XX
			// la aplicacion entrará en un bucle infinito
			case 0: // tiempo fijo
				$result['trs']=$dmanga["TRS_{$suffix}_Factor"];
				break;
			case 1: // mejor tiempo
				if ($dmanga["TRS_{$suffix}_Unit"]==="s") $result['trs']= $best1 + $dmanga["TRS_${suffix}_Factor"]; // ( + X segundos )
				else $result['trs']= $best1 * ( (100+$dmanga["TRS_{$suffix}_Factor"]) / 100) ; // (+ X por ciento)
				break;
			case 2: // media de los tres mejores tiempos
				if ($dmanga["TRS_{$suffix}_Unit"]==="s") $result['trs']= $best3 + $dmanga["TRS_${suffix}_Factor"]; // ( + X segundos )
				else $result['trs']= $best3 * ( (100+$dmanga["TRS_{$suffix}_Factor"]) / 100) ; // (+ X por ciento)
				break;
			case 3: // trs standard +xxx						
				$result_std=$this->getResultados(0)['trs'];
				if ($dmanga["TRS_{$suffix}_Unit"]==="s") 
					$result['trs']= $result_std['trs'] + $dmanga["TRS_${suffix}_Factor"]; // ( + X segundos )
				else $result['trs']= $result_std['trs'] * ( (100+$dmanga["TRS_{$suffix}_Factor"]) / 100) ; // (+ X por ciento)
				break;
			case 4: // trs medium + xx						
				$result_med=$this->getResultados(1)['trs'];
				if ($dmanga["TRS_{$suffix}_Unit"]==="s") 
					$result['trs']= $result_med['trs'] + $dmanga["TRS_${suffix}_Factor"]; // ( + X segundos )
				else $result['trs']= $result_med['trs'] * ( (100+$dmanga["TRS_{$suffix}_Factor"]) / 100) ; // (+ X por ciento)
				break;
			case 5: // trs small + xx
				$result_med=$this->getResultados(2)['trs'];
				if ($dmanga["TRS_{$suffix}_Unit"]==="s")
					$result['trs']= $result_med['trs'] + $dmanga["TRS_${suffix}_Factor"]; // ( + X segundos )
				else $result['trs']= $result_med['trs'] * ( (100+$dmanga["TRS_{$suffix}_Factor"]) / 100) ; // (+ X por ciento)
				break;
		}
		$result['trs']=ceil($result['trs']); // redondeamos hacia arriba
		// Evaluamos TRM
		switch($dmanga["TRM_{$suffix}_Tipo"]) {
			case 0: // TRM Fijo
				$result['trm']=$dmanga["TRM_{$suffix}_Factor"];
				break;
			case 1: // TRS + (segs o porcentaje)
				if ($dmanga["TRM_{$suffix}_Unit"]==="s") $result['trm']=$result['trs'] + $dmanga["TRM_{$suffix}_Factor"]; // ( + X segundos )
				else $result['trm'] = $result['trs'] * ( (100+$dmanga["TRM_{$suffix}_Factor"]) / 100) ; // (+ X por ciento)
				break;
		}
		$result['trm']=ceil($result['trm']); // redondeamos hacia arriba
		// Finalmente evaluamos la velocidad de la ronda
		$result['vel']= ($result['trs']==0)?0:number_format($result['dist']/$result['trs'],1);
		// esto es todo amigos
		return $result;
	}

		
	/**
	 * Inserta perro en la lista de resultados de la manga
	 * los datos del perro se toman de la tabla perroguiaclub
	 * @param {array} $objperro datos perroguiaclub
	 * @param {array} $inscripcion datos de la inscripcion
	 * @return "" on success; else error string
	 */
	function insertByData($objperro,$inscripcion) {
		$error="";
		$idmanga=$this->IDManga;
		$this->myLogger->enter();
		if ($this->isCerrada()) 
			return $this->error("Manga $idmanga comes from closed Jornada:".$this->IDJornada);	
		
		// If row pkey(manga,perro) exists, just update; else insert
		$sql="REPLACE INTO Resultados (Prueba,Jornada,Manga,Dorsal,Perro,Nombre,Licencia,Categoria,Grado,NombreGuia,NombreClub) 
				VALUES (?,?,?,?,?,?,?,?,?,?,?)";
		$stmt=$this->conn->prepare($sql);
		if (!$stmt) return $this->conn->error;
		$res=$stmt->bind_param('iiiiissssss',$prueba,$jornada,$manga,$dorsal,$perro,$nombre,$licencia,$categoria,$grado,$guia,$club);
		if (!$res) return $this->error($stmt->error);
		$prueba=$this->IDPrueba;
		$jornada=$this->IDJornada;
		$manga=$idmanga;
		$perro=$objperro['ID'];
		$dorsal=$inscripcion['Dorsal'];
		$nombre=$objperro['Nombre'];
		$licencia=$objperro['Licencia'];
		$categoria=$objperro['Categoria'];
		$grado=$objperro['Grado'];
		$guia=$objperro['NombreGuia'];
		$club=$objperro['NombreClub'];
		// ejecutamos el query
		$res=$stmt->execute();
		if (!$res) $error=$stmt->error;
		$stmt->close();
		$this->myLogger->leave();
		return $error;
	}
	
	/**
	 * Borra el idperro de la lista de resultados de la manga
	 * @param {integer} $idperro
	 * @return "" on success; null on error
	 */
	function delete($idperro) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		if ($idperro<=0) return $this->error("No Perro ID specified");
		if ($this->isCerrada()) 
			return $this->error("Manga $idmanga comes from closed Jornada:".$this->IDJornada);
		$str="DELETE FROM Resultados WHERE ( Perro=$idperro ) AND ( Manga=$idmanga)";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		$this->myLogger->leave();
		return "";
	}
	
	/**
	 * selecciona los datos del idperro indicado desde la lista de resultados de la manga
	 * @param {integer} $idperro
	 * @return {array} [key=>value,...] on success; null on error
	 */
	function select($idperro) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		if ($idperro<=0) return $this->error("No Perro ID specified");
		$row=$this->__selectAsArray("*", "Resultados", "(Perro=$idperro) AND (Manga=$idmanga)");
		if(!is_array($row)) 
			return $this->error("No Results for Perro:$idperro on Manga:$idmanga");
		$this->myLogger->leave();
		return $row;
	}
	
	/**
	 * Actualiza los resultados de la manga para el idperro indicado
	 * @param {integer} $idperro
	 * @return datos actualizados desde la DB; null on error
	 */
	function update($idperro) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		if ($idperro<=0) return $this->error("No Perro ID specified");
		if ($this->isCerrada()) 
			return $this->error("Manga $idmanga comes from closed Jornada:".$this->IDJornada);
		// buscamos la lista de parametros a actualizar
		$entrada=http_request("Entrada","s",date("Y-m-d H:i:s"));
		$comienzo=http_request("Comienzo","s",date("Y-m-d H:i:s"));
		$faltas=http_request("Faltas","i",0);
		$rehuses=http_request("Rehuses","i",0);
		$tocados=http_request("Tocados","i",0);
		$nopresentado=http_request("NoPresentado","i",0);
		$eliminado=http_request("Eliminado","i",0);
		$tiempo=http_request("Tiempo","d",0.0);
		$observaciones=http_request("Observaciones","s","");
		// si la actualizacion esta marcada como pendiente
		$pendiente=http_request("Pendiente","i",1);
		if ($pendiente==0) {
			// comprobamos la coherencia de los datos recibidos y ajustamos
			// NOTA: el orden de estas comprobaciones es MUY importante
			if ($rehuses>=3) { $tiempo=0; $eliminado=1; $nopresentado=0;}
			if ($tiempo>0) {$nopresentado=0;}
			if ($eliminado==1) { $tiempo=0; $nopresentado=0; }
			if ($nopresentado==1) { $tiempo=0; $eliminado=0; $faltas=0; $rehuses=0; $tocados=0; }
			if ( ($tiempo==0) && ($eliminado==0)) { $nopresentado=1; $faltas=0; $rehuses=0; $tocados=0; }
			if ( ($tiempo==0) && ($eliminado==1)) { $nopresentado=0; }
		}
		// efectuamos el update, marcando "pendiente" como false
		$sql="UPDATE Resultados 
			SET Entrada='$entrada' , Comienzo='$comienzo' , 
				Faltas=$faltas , Rehuses=$rehuses , Tocados=$tocados ,
				NoPresentado=$nopresentado , Eliminado=$eliminado , 
				Tiempo=$tiempo , Observaciones='$observaciones' , Pendiente=$pendiente
			WHERE (Perro=$idperro) AND (Manga=$this->IDManga)";
		$rs=$this->query($sql);
		if (!$rs) return $this->error($this->conn->error);
		$this->myLogger->leave();
		return $this->select($idperro);
	}
	
	/**
	 * Elabora una lista con los perros pendientes de salir en la manga
	 */
	function getPendientes($mode) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		$where="(Manga=$idmanga) AND (Pendiente=1) "; // para comprobar pendientes
		$cat="";
		switch ($mode) {
			case 0: /* Large */		$cat= "AND (Categoria='L')"; break;
			case 1: /* Medium */	$cat= "AND (Categoria='M')"; break;
			case 2: /* Small */		$cat= "AND (Categoria='S')"; break;
			case 3: /* Med+Small */ $cat= "AND ( (Categoria='M') OR (Categoria='S') )"; break;
			case 4: /* L+M+S */ 	$cat= "AND ( (Categoria='L') OR (Categoria='M') OR (Categoria='S') )"; break;
			case 5: /* Tiny */		$cat= "AND (Categoria='T')"; break;
			case 6: /* L+M */		$cat= "AND ( (Categoria='L') OR (Categoria='M') )"; break;
			case 7: /* S+T */		$cat= "AND ( (Categoria='S') OR (Categoria='T') )"; break;
			case 8: /* L+M+S+T */	break; // no check categoria
			default: return $this->error("modo de recorrido desconocido:$mode");
		}
		// FASE 0: comprobamos si hay perros pendientes de salir
		return $this->__select(
			/* SELECT */	"*",
			/* FROM */		"Resultados",
			/* WHERE */		"$where $cat",
			/* ORDER BY */	"Nombre ASC",
			/* LIMIT */		""
		);
		$this->myLogger->leave();
	}
	
	/**
	 * Presenta una tabla ordenada segun los resultados de la manga
	 * @return null on error else array en formato easyui datagrid
	 *@param {integer} mode 0:L 1:M 2:S 3:MS 4:LMS.
	 *@return requested data or error
	 */
	
	function getResultados($mode) {
		$this->myLogger->enter();
		$idmanga=$this->IDManga;
		
		// FASE 0: en funcion del tipo de recorrido y modo pedido
		// ajustamos el criterio de busqueda de la tabla de resultados
		$where="(Manga=$idmanga) AND (Pendiente=0) ";
		$cat="";
		switch ($mode) {
			case 0: /* Large */		$cat= "AND (Categoria='L')"; break;
			case 1: /* Medium */	$cat= "AND (Categoria='M')"; break;
			case 2: /* Small */		$cat= "AND (Categoria='S')"; break;
			case 3: /* Med+Small */ $cat= "AND ( (Categoria='M') OR (Categoria='S') )"; break;
			case 4: /* L+M+S */ 	$cat= "AND ( (Categoria='L') OR (Categoria='M') OR (Categoria='S') )"; break;
			case 5: /* Tiny */		$cat= "AND (Categoria='T')"; break;
			case 6: /* L+M */		$cat= "AND ( (Categoria='L') OR (Categoria='M') )"; break;
			case 7: /* S+T */		$cat= "AND ( (Categoria='S') OR (Categoria='T') )"; break;
			case 8: /* L+M+S+T */	break; // no check categoria
			default: return $this->error("modo de recorrido desconocido:$mode");
		}
		// FASE 1: recogemos resultados ordenados por precorrido y tiempo
		$res=$this->__select(
				"Dorsal,Perro,Nombre,Licencia,Categoria,Grado,NombreGuia,NombreClub,Faltas,Tocados,Rehuses,Tiempo,
					( 5*Faltas + 5*Rehuses + 5*Tocados + 100*Eliminado + 200*NoPresentado ) AS PRecorrido,
					0 AS PTiempo, 0 AS Penalizacion, '' AS Calificacion, 0 AS Velocidad", 
				"Resultados", 
				"$where $cat", 
				" PRecorrido ASC, Tiempo ASC", 
				"");
		if (!is_array($res))
			return $this->error($this->conn->error);
		$table=$res['rows'];
		$this->myLogger->leave();
		// FASE 2: evaluamos TRS Y TRM
		$tdata=$this->evalTRS($mode,$table); // array( 'dist' 'obst' 'trs' 'trm', 'vel')
		$res['trs']=$tdata; // store trs data into result
		$trs=$tdata['trs'];
		$trm=$tdata['trm'];
		// FASE 3: añadimos ptiempo, puntuacion y clasificacion
		$size=count($table);
		for ($idx=0;$idx<$size;$idx++ ){ 
			// importante: las asignaciones se hacen en base a $table[$idx], 
			// pues si no solo se actualiza la copia
			
			if ($trs==0) {
				// si TRS==0 no hay penalizacion por tiempo
				$table[$idx]['PTiempo']		= 	0.0; 
				$table[$idx]['Penalizacion']=	$table[$idx]['PRecorrido'];
			} else {
				// evaluamos penalizacion por tiempo y penalizacion final
				if ($table[$idx]['Tiempo']<$trs) { // Por debajo del TRS
					$table[$idx]['PTiempo']		= 	0.0; 
					$table[$idx]['Penalizacion']=	$table[$idx]['PRecorrido'];
				}
				if ($table[$idx]['Tiempo']>=$trs) { // Superado TRS
					$table[$idx]['PTiempo']		=	$table[$idx]['Tiempo'] 		-	$trs; 
					$table[$idx]['Penalizacion']=	floatval($table[$idx]['PRecorrido'])	+	$table[$idx]['PTiempo'];
				}
				if ($table[$idx]['Tiempo']>$trm) { // Superado TRM: eliminado
					$table[$idx]['Penalizacion']=	100.0;
				}
			}
				
			// evaluamos velocidad 
			if ($table[$idx]['Tiempo']==0)	$table[$idx]['Velocidad'] = 0;
			else 	$table[$idx]['Velocidad'] =  $tdata['dist'] / $table[$idx]['Tiempo'];
			
			// evaluamos calificacion 
			if ($table[$idx]['Penalizacion']>=200)  {
				$table[$idx]['Penalizacion']=200.0; 
				$table[$idx]['Calificacion'] = "No Presentado"; 
				$table[$idx]['CShort'] = "N.P."; 
			}
			else if ($table[$idx]['Penalizacion']>=100) {
				$table[$idx]['Penalizacion']=100.0; 
				$table[$idx]['Calificacion'] = "Eliminado";
				$table[$idx]['CShort'] = "Elim"; 
			}
			else if ($table[$idx]['Penalizacion']>=26)	{
				$table[$idx]['Calificacion'] = "No Clasificado";
				$table[$idx]['CShort'] = "N.C.";
			}
			else if ($table[$idx]['Penalizacion']>=16)	{
				$table[$idx]['Calificacion'] = "Bueno";
				$table[$idx]['CShort'] = "Bien";
			}
			else if ($table[$idx]['Penalizacion']>=6)	{
				$table[$idx]['Calificacion'] = "Muy Bien";
				$table[$idx]['CShort'] = "M.B.";
			}
			else if ($table[$idx]['Penalizacion']>0)	{
				$table[$idx]['Calificacion'] = "Excelente";
				$table[$idx]['CShort'] = "Exc ";
			}
			else if ($table[$idx]['Penalizacion']==0)	{
				$table[$idx]['Calificacion'] = "Excelente (p)";
				$table[$idx]['CShort'] = "Ex P";
			}
		}
		// FASE 4: re-ordenamos los datos en base a la puntuacion y calculamos campo "Puesto"
		usort($table, function($a, $b) {
			if ( $a['Penalizacion'] == $b['Penalizacion'] )	return ($a['Tiempo'] > $b['Tiempo'])? 1:-1;
			return ( $a['Penalizacion'] > $b['Penalizacion'])?1:-1;
		});
		
		// format output data and take care con duplicated penalizacion and time
		$puesto=1;
		$last=0;
		for($idx=0;$idx<$size;$idx++) {
			// ajustamos puesto
			$now=100*$table[$idx]['Penalizacion']+$table[$idx]['Tiempo'];
			if ($last!=$now) { $last=$now; $puesto=1+$idx; }
			$table[$idx]['Puesto']=$puesto;
			/*
			// This should be done at javascript view level
			// ajustamos penalizacion y tiempo con 2 decimales
			$table[$idx]['Penalizacion'] =number_format($table[$idx]['Penalizacion'],2);
			$table[$idx]['Velocidad'] =number_format($table[$idx]['Velocidad'],1); 
			$table[$idx]['Tiempo'] =number_format($table[$idx]['Tiempo'],2);
			if ($table[$idx]['Penalizacion']>=100) { $table[$idx]['Tiempo']="-"; $table[$idx]['Velocidad']="-"; }
			if ($table[$idx]['Penalizacion']>=200) $table[$idx]['Puesto']="-";
			*/
		}
		// finalmente anyadimos info de la manga y retornamos array
		$this->myLogger->leave();
		$res['rows']=$table;
		$res['manga']=$this->getDatosManga();
		$res['trs']=$tdata;
		return $res;
	}
	
	function getTRS($mode) {
		$this->myLogger->enter();
		$trs=getResultados($mode)['trs'];
		$this->myLogger->leave();
		return $trs;
	}
	static function enumerateResultados($jornadaid) {
		if ($jornadaid<=0) { // no jornada id provided
			return array('total'=>0,'rows'=>array());
		}
		$dbobj=new DBObject("enumerateResultados");
		$dbobj->myLogger->enter();
		$jornada=$dbobj->__getArray("Jornadas",$jornadaid);
		$prueba=$dbobj->__getArray("pruebas",$jornada['Prueba']);
		$mangas=$dbobj->__select("*","Mangas","(Jornada=$jornadaid)","","")['rows'];
		$rows=array();
		foreach($mangas as $manga) {
			// datos comunes a todos los resultados posibles de una misma manga
			$item=array();
			$item['Prueba']=$prueba['ID'];
			$item['Jornada']=$jornadaid;
			$item['Manga']=$manga['ID'];
			$item['TipoManga']=$manga['Tipo'];
			$mid=$manga['ID'];
			switch($manga['Recorrido']){
				case 0: // recorridos separados
					$l	=array_merge( array('ID'=>$mid.',0', 'Mode'=>0,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[0][0]),$item);
					array_push($rows,$l);
					$m	=array_merge( array('ID'=>$mid.',1','Mode'=>1,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[1][0]),$item);
					array_push($rows,$m);
					$s	=array_merge( array('ID'=>$mid.',2','Mode'=>2,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[2][0]),$item);
					array_push($rows,$s);
					if($prueba['RSCE']!=0) {
						$t=array_merge( array('ID'=>$mid.',5','Mode'=>5,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[5][0]),$item);
						array_push($rows,$t);
					}
					break;
				case 1: // recorridos mixto
					if ($prueba['RSCE']==0){
						$l	=array_merge( array('ID'=>$mid.',0','Mode'=>0,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[0][0]),$item);
						array_push($rows,$l);
						$ms	=array_merge( array('ID'=>$mid.',3','Mode'=>3,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[3][0]),$item);
						array_push($rows,$ms);
					} else {
						$lm	=array_merge( array('ID'=>$mid.',6','Mode'=>6,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[6][0]),$item);
						array_push($rows,$lm);
						$st	=array_merge( array('ID'=>$mid.',7','Mode'=>7,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[7][0]),$item);
						array_push($rows,$st);
					}
					break;
				case 2: // recorridos conjuntos
					if ($prueba['RSCE']==0){
						$lms =array_merge( array('ID'=>$mid.',4','Mode'=>4,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[4][0]),$item);
						array_push($rows,$lms);
					} else {
						$lmst=array_merge( array('ID'=>$mid.',8','Mode'=>8,'Nombre'=>Mangas::$tipo_manga[$manga['Tipo']][1]." - ".Mangas::$manga_modes[8][0]),$item);
						array_push($rows,$lmst);
					}
					break;
			}
		} /* foreach */
		$result=array('total'=>count($rows),'rows'=>$rows);
		return $result;
	}
	
	// version extendida de Jornadas::roundByJornada() en la que se genera una entrada por cada recorrido de cada categoria
	static function enumerateClasificaciones($jornadaid) {
	
		/*inner functions */
		function __searchManga($tipo,$mangas) {
			foreach($mangas as $manga) {
				if ($manga['Tipo']==$tipo) return $manga;
			}
			return null;
		}
		function __getArray($p,$j,$t,$r,$m,$m1,$m2) {
			return array(
					'Prueba'=>$p,
					'Jornada'=>$j,
					'Rondas'=> Jornadas::$tipo_ronda[$t][0],
					'Nombre'=> Jornadas::$tipo_ronda[$t][1]." - ".Mangas::$manga_modes[$m][0],
					'Recorrido'=>$r,
					'Mode'=>$m,
					'Manga1'=>$m1['ID'],
					'Manga2'=>($m2!==null)?$m2['ID']:0,
					'NombreManga1'=>Mangas::$tipo_manga[$m1['Tipo']][1],
					'NombreManga2'=>($m2!==null)?Mangas::$tipo_manga[$m2['Tipo']][1]:''
			);
		}
	
		if ($jornadaid<=0) { // no jornada id provided
			return array('total'=>0,'rows'=>array());
		}
		$dbobj=new DBObject("enumerateClasificaciones");
		$dbobj->myLogger->enter();
		$jornada=$dbobj->__getArray("Jornadas",$jornadaid);
		$prueba=$dbobj->__getArray("pruebas",$jornada['Prueba']);
		$mangas=$dbobj->__select("*","Mangas","(Jornada=$jornadaid)","TIPO ASC","")['rows'];
	
		$data=array();
		if ($jornada['PreAgility2']!=0) {
			// $dbobj->myLogger->trace("Procesando mangas de preagility-2");
			/* Pre-Agility siempre tiene recorrido comun para todas las categorias */
			$m1=__searchManga(1,$mangas); // PA-1
			$m2=__searchManga(2,$mangas); // PA-2
			array_push($data,__getArray($prueba['ID'],$jornadaid,2,$m1['Recorrido'],($prueba['RSCE']==0)?4:8,$m1,$m2));
		} else if ($jornada['PreAgility']!=0) {
			// $dbobj->myLogger->trace("Procesando mangas de preagility-1");
			/* Pre-Agility siempre tiene recorrido comun para todas las categorias */
			$m1=__searchManga(1,$mangas); // PA-1
			array_push($data,__getArray($prueba['ID'],$jornadaid,1,$m1['Recorrido'],($prueba['RSCE']==0)?4:8,$m1,null));
		}
		if ($jornada['Grado1']!=0) {
			$m1=__searchManga(3,$mangas); // Agility 1 Grado I
			$m2=__searchManga(4,$mangas); // Agility 2 Grado I
			switch($m1['Recorrido']){
				case 0: // separado
					array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],0,$m1,$m2)); // large
					array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],1,$m1,$m2)); // medium
					array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],2,$m1,$m2)); // small
					if($prueba['RSCE']!=0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],5,$m1,$m2)); // tiny
					}
					break;
				case 1: // mixto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],0,$m1,$m2)); // large
						array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],3,$m1,$m2)); // m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],6,$m1,$m2)); // l+m
						array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],7,$m1,$m2)); // s+t
					}
					break;
				case 2: // conjunto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],4,$m1,$m2)); // l+m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,3,$m1['Recorrido'],8,$m1,$m2)); // l+m+s+t
					}
					break;
			}
		}
	
		if ($jornada['Grado2']!=0) {
			$m1=__searchManga(5,$mangas); // Agility Grado II
			$m2=__searchManga(10,$mangas); // Jumping Grado II
			switch($m1['Recorrido']){
				case 0: // separado
					array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],0,$m1,$m2)); // large
					array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],1,$m1,$m2)); // medium
					array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],2,$m1,$m2)); // small
					if($prueba['RSCE']!=0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],5,$m1,$m2)); // tiny
					}
					break;
				case 1: // mixto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],0,$m1,$m2)); // large
						array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],3,$m1,$m2)); // m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],6,$m1,$m2)); // l+m
						array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],7,$m1,$m2)); // s+t
					}
					break;
				case 2: // conjunto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],4,$m1,$m2)); // l+m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,4,$m1['Recorrido'],8,$m1,$m2)); // l+m+s+t
					}
					break;
			}
		}
	
		if ($jornada['Grado3']!=0) {
			$m1=__searchManga(6,$mangas); // Agility Grado III
			$m2=__searchManga(11,$mangas); // Jumping Grado III
			switch($m1['Recorrido']){
				case 0: // separado
					array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],0,$m1,$m2)); // large
					array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],1,$m1,$m2)); // medium
					array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],2,$m1,$m2)); // small
					if($prueba['RSCE']!=0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],5,$m1,$m2)); // tiny
					}
					break;
				case 1: // mixto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],0,$m1,$m2)); // large
						array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],3,$m1,$m2)); // m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],6,$m1,$m2)); // l+m
						array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],7,$m1,$m2)); // s+t
					}
					break;
				case 2: // conjunto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],4,$m1,$m2)); // l+m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,5,$m1['Recorrido'],8,$m1,$m2)); // l+m+s+t
					}
					break;
			}
		}
	
		if ($jornada['Open']!=0) {
			$m1=__searchManga(7,$mangas); // Agility Open
			$m2=__searchManga(12,$mangas); // Jumping Open
			switch($m1['Recorrido']){
				case 0: // separado
					array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],0,$m1,$m2)); // large
					array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],1,$m1,$m2)); // medium
					array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],2,$m1,$m2)); // small
					if($prueba['RSCE']!=0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],5,$m1,$m2)); // tiny
					}
					break;
				case 1: // mixto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],0,$m1,$m2)); // large
						array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],3,$m1,$m2)); // m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],6,$m1,$m2)); // l+m
						array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],7,$m1,$m2)); // s+t
					}
					break;
				case 2: // conjunto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],4,$m1,$m2)); // l+m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,6,$m1['Recorrido'],8,$m1,$m2)); // l+m+s+t
					}
					break;
			}
		}
	
		if ($jornada['Equipos3']!=0) {
			// $dbobj->myLogger->trace("Procesando mangas equipos-3");
			// TODO: write
			return null;
		}
		if ($jornada['Equipos4']!=0) {
			// $dbobj->myLogger->trace("Procesando mangas equipos 4");
			// TODO: write
			return null;
		}
		if ($jornada['KO']!=0) {
			// $dbobj->myLogger->trace("Procesando mangas K.O.");
			// TODO: write
			return null;
		}
		if ($jornada['Especial']!=0) {
			$m1=__searchManga(16,$mangas); // Manga especial a una vuelta
			switch($m1['Recorrido']){
				case 0: // separado
					array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],0,$m1,null)); // large
					array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],1,$m1,null)); // medium
					array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],2,$m1,null)); // small
					if($prueba['RSCE']!=0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],5,$m1,null)); // tiny
					}
					break;
				case 1: // mixto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],0,$m1,null)); // large
						array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],3,$m1,null)); // m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],6,$m1,null)); // l+m
						array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],7,$m1,null)); // s+t
					}
					break;
				case 2: // conjunto
					if($prueba['RSCE']==0) {
						array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],4,$m1,null)); // l+m+s
					} else {
						array_push($data,__getArray($prueba['ID'],$jornadaid,10,$m1['Recorrido'],8,$m1,null)); // l+m+s+t
					}
					break;
			}
		}
	
		$result=array('total'=>count($data),'rows'=>$data);
		return $result;
	}

}
?>