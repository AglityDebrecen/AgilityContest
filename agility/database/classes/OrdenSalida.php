<?php
require_once("DBObject.php");
require_once("Resultados.php");
require_once("Clasificaciones.php");
require_once("Inscripciones.php");

class OrdenSalida extends DBObject {
	
	// tablas utilizadas para componer e insertar los idperroes en el string de orden de salida
	protected $default_orden = "BEGIN,TAG_-0,TAG_-1,TAG_L0,TAG_L1,TAG_M0,TAG_M1,TAG_S0,TAG_S1,TAG_T0,TAG_T1,END";
	protected $tags_orden = array ( // orden LargeMediumSmall/Tiny
			'-0' => 'TAG_-1',
			'-1' => 'TAG_L0',
			'L0' => 'TAG_L1',
			'L1' => 'TAG_M0',
			'M0' => 'TAG_M1',
			'M1' => 'TAG_S0',
			'S0' => 'TAG_S1',
			'S1' => 'TAG_T0',
			'T0' => 'TAG_T1',
			'T1' => 'END' 
	);

	/* use parent constructor and destructor */
	
	/**
	 * Retrieve Mangas.Orden_Salida
	 * @param unknown $manga
	 * @return {string} orden de salida. "" si vacio; null on error
	 */
	function getOrden($manga) {
		$this->myLogger->enter();
		$res=$this->__selectObject("Orden_Salida", "Mangas", "( ID=$manga )");
		$result = $res->Orden_Salida;
		$this->myLogger->leave();
		return ($result==="")?$this->default_orden:$result;
	}
	
	/**
	 * Update Mangas.Orden_Salida with new value
	 * @param {integer} $manga manga ID
	 * @param {string} $orden new ordensalida
	 * @return {string} "" if success; null on error
	 */
	function setOrden($manga, $orden) {
		$this->myLogger->enter();
		$sql = "UPDATE Mangas SET Orden_Salida = '" . $orden . "' WHERE ( ID=$manga )";
		$rs = $this->query ($sql);
		// do not call $rs->free() as no resultset returned
		if (!$rs) return $this->error($this->conn->error);
		$this->myLogger->leave();
		return "";
	}
	
	/**
	 * coge el string con el orden de salida e inserta un elemento al final de su grupo
	 * Porsiaca lo intenta borrar previamente
	 * @param {string} $ordensalida Orden de salida actual
	 * @param {integer} $idperro
	 * @param {string[1]} $cat
	 * @param {integer[1]} $celo
	 * @return {string} nuevo orden de salida
	 */
	function insertIntoList($ordensalida, $idperro, $cat, $celo) {
		$this->myLogger->enter();
		// $this->myLogger->debug("inserting idperro:$idperro cat:$cat celo:$celo" );
		// lo borramos para evitar una posible doble insercion
		$str = "," . $idperro . ",";
		$nuevoorden = str_replace ( $str, ",", $ordensalida );
		// componemos el tag que hay que insertar
		$myTag = $idperro . "," . $this->tags_orden [$cat . $celo];
		// y lo insertamos en lugar que corresponde
		$result = str_replace ( $this->tags_orden [$cat . $celo], $myTag, $nuevoorden );
		$this->myLogger->leave();
		return $result;
	}
	
	/**
	 * Elimina un idperro del orden de salida indicao
	 * @param {string} $ordensalida orden de salida actual
	 * @param {integer} $idperro
	 * @return {string} nuevo orden de salida
	 */
	function removeFromList($ordensalida,$idperro) {
		$this->myLogger->enter();
		$str = "," . $idperro . ",";
		$nuevoorden = str_replace ( $str, ",", $ordensalida );
		$this->myLogger->leave();
		return $nuevoorden;
	}
	
	/**
	 * Comprueba si el perro esta bien insertado
	 * @param unknown $ordensalida
	 * @param unknown $idperro
	 * @param unknown $cat
	 * @param unknown $celo
	 * @return true o false
	 */
	function verify($ordensalida, $idperro, $cat, $celo) {
		$this->myLogger->enter();
		// si no esta insertado indica error
		if (strpos($ordensalida,',$idperro,')===false) return false;
		$tag="$cat$celo";
		$from="";$to="";
		switch($tag) {
			case "-0": $from="TAG_-0"; $to="TAG_-1"; break;
			case "-1": $from="TAG_-1"; $to="TAG_L0"; break;
			case "L0": $from="TAG_L0"; $to="TAG_L1"; break;
			case "L1": $from="TAG_L1"; $to="TAG_M0"; break;
			case "M0": $from="TAG_M0"; $to="TAG_M1"; break;
			case "M1": $from="TAG_M1"; $to="TAG_S0"; break;
			case "S0": $from="TAG_S0"; $to="TAG_S1"; break;
			case "S1": $from="TAG_S1"; $to="TAG_T0"; break;
			case "T0": $from="TAG_T0"; $to="TAG_T1"; break;
			case "T1": $from="TAG_T1"; $to="END"; break;
			default:
				$this->myLogger->error("Invalid Categoria/Celo values ($cat,$celo) $for idperro $idperro");
				return false;
		}
		$f=strpos($ordensalida,$from);
		$l=strpos($ordensalida,$to)-f;
		$str=substr($ordensalida,f,l);
		$this->myLogger->leave();
		return (strpos($str,',$idperro,')===false)?false:true;
	}
	
	/**
	 * Obtiene la lista (actualizada) de perros de una manga
	 * En el proceso de inscripcion ya hemos creado la tabla de resultados, y el orden de salida
	 * con lo que la cosa es sencilla:
	 * Cogemos los perros inscritos en la manga y los ordenamos segun el orden establecido, añadiendo
	 * el campo "Celo"
	 *
	 * @param {int} $prueba ID de prueba
	 * @param {int} $jornada ID de jornada
	 * @param {int} $manga ID de manga
	 * @return array[count,[data]] array ordenado segun ordensalida de datos de perros de una manga 
	 */
	function getData($prueba,$jornada, $manga) {
		$this->myLogger->enter();
		// fase 1: obtenemos el orden de salida
		$orden=$this->getOrden($manga);
		if ($orden==="") {
			// si no hay orden de salida predefinido,
			// quiere decir que no hay nadie inscrito. Indica error
			return $this->myLogger->error("No hay inscripciones en Prueba:$prueba Jornada:$jornada Manga:$manga");
		}
		$this->myLogger->debug("OrdenSalida::getData() Manga:$manga El orden de salida es: \n$orden");
		$lista = explode ( ",", $orden );
		
		// fase 2: obtenemos todos los resultados de esta manga 
		$rs=$this->__select("*", "Resultados", "(Manga=$manga)", "", "");
		if (!$rs) return $this->error($this->conn->error);
		// y los guardamos en un array indexado por el idperro
		$data=array();
		foreach($rs['rows'] as $row) { $data[$row['Perro']]=$row;}
		
		// fase 3 componemos el resultado siguiendo el orden de salida
		$items=array();
		$count=0;
		$celo=0;
		foreach ($lista as $idperro) {
			switch($idperro) {
				// separadores2
				case "BEGIN": case "END": continue;
				case "TAG_-0": case "TAG_L0": case "TAG_M0": case "TAG_S0": case "TAG_T0": $celo=0; continue;
				case "TAG_-1": case "TAG_L1": case "TAG_M1": case "TAG_S1": case "TAG_T1": $celo=1; continue;
				default: // idperroes
					if (!isset($data[$idperro])) {
						$this->myLogger->error("No hay entrada en 'Resultados' para perro:$idperro Manga:$manga");
						// TODO: esto no deberia ocurrir pero por si acaso ver como resolverlo
					}
					$data[$idperro]['Celo']=$celo;
					array_push($items,$data[$idperro]);
					$count++;
					break;
			}
		}
		$result = array();
		$result["total"] = $count;
		$result["rows"] = $items;
		$this->myLogger->leave();
		return $result;
	}
	
	/**
	 * Reordena el orden de salida de una manga al azar
	 * @param  	{int} $jornada ID de jornada
	 * @param	{int} $manga ID de manga
	 * @return {string} nuevo orden de salida
	 */
	function random($jornada, $manga) {
		$this->myLogger->enter();
		// obtenemos el orden de salida
		$ordensalida=$this->getOrden($manga);
		if ($ordensalida==="") {
			// si no hay orden de salida predefinido,
			// quiere decir que no hay nadie inscrito. Indica error
			return $this->myLogger->error("No hay inscripciones en Jornada:$jornada Manga:$manga");
		}
		$this->myLogger->debug("OrdenSalida::Random() Manga:$manga Orden original: \n$ordensalida");
		// "troceamos" el orden por categorias, y reorganizamos cada subcategoria al azar
		// "BEGIN,TAG_-0,TAG_-1,TAG_L0,TAG_L1,TAG_M0,TAG_M1,TAG_S0,TAG_S1,TAG_T0,TAG_T1,END";
		$newOrden="BEGIN,";
		$str=getInnerString($ordensalida,"BEGIN,",",TAG_-0");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_-0,";

		$str=getInnerString($ordensalida,"TAG_-0,",",TAG_-1");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_-1,";

		$str=getInnerString($ordensalida,"TAG_-1,",",TAG_L0");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_L0,";

		$str=getInnerString($ordensalida,"TAG_L0,",",TAG_L1");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_L1,";

		$str=getInnerString($ordensalida,"TAG_L1,",",TAG_M0");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_M0,";

		$str=getInnerString($ordensalida,"TAG_M0,",",TAG_M1");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_M1,";

		$str=getInnerString($ordensalida,"TAG_M1,",",TAG_S0");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_S0,";

		$str=getInnerString($ordensalida,"TAG_S0,",",TAG_S1");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_S1,";

		$str=getInnerString($ordensalida,"TAG_S1,",",TAG_T0");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_T0,";

		$str=getInnerString($ordensalida,"TAG_T0,",",TAG_T1");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "TAG_T1,";

		$str=getInnerString($ordensalida,"TAG_T1,",",END");
		if ($str!=="") $newOrden = $newOrden . implode(",",aleatorio(explode(",", $str))) . ",";
		$newOrden = $newOrden . "END";

		// almacenamos el nuevo orden de salida
		$this->myLogger->debug("OrdenSalida::Random() Manga:$manga Orden final: \n$newOrden");
		$this->setOrden ( $manga, $newOrden );
		
		// limpieza y retorno de resultados
		$this->myLogger->leave();
		return $ordensalida;
	}
	
	/**
	 * Calcula el orden de salida de una manga en funcion del orden inverso al resultado de su manga "hermana"
	 * @param {integer} $jornada ID De Jornada
	 * @param {integer} $manga ID De la manga de la que hay que calcular el orden de salida
	 * @return {string} nuevo orden de salida; null on error
	 */
	function reverse($jornada,$manga) {
		$this->myLogger->enter();
		// fase 1: buscamos la "manga hermana"
		$str="SELECT Grado,Orden_Salida FROM Mangas WHERE (Jornada=$jornada) AND (ID=$manga)";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		$obj=$rs->fetch_object();
		$rs->free();
		if (!$obj) return $this->error("No manga found with ID=$manga");
		$grado=$obj->Grado;
		$str="Select * FROM Mangas WHERE (Jornada=$jornada) AND (Grado='$grado') AND (ID!=$manga)";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		$manga2=$rs->fetch_object();
		$rs->free();
		if (!$manga2) return $this->error("No manga found with with same 'Grado' as ID=$manga");
		$mangaid=$manga2->ID;
	
		// fase 2: evaluamos resultados
		// nos aseguramos de que la manga tiene la tabla de resultados asociada
		$r=new Resultados("OrdenSalida::Reverse",$mangaid);
		$r->enumerate();
		if (!$r) {
			$this->myLoger->error("OrdenSalida::reverse::enumerate $mangaid failed");
			return null;
		}
		$r=new Clasificaciones("OrdenSalida::Reverse");
		$orden=$r->reverseOrden($mangaid);
		if (!$orden) {
			$this->myLogger->error("OrdenSalida::reverse::orden $mangaid failed");
			return null;
		}
		
		// fase 3: como la tabla de resultados no contiene informacion sobre el celo
		// y para ahorrar consultas a la DB, vamos a sacar dicha información del orden actual
		$registrados = explode ( ",", $obj->Orden_Salida);
		$celo=array();
		$lastCelo=0;
		foreach($registrados as $idperro) {
			switch($idperro) {
				case "BEGIN": continue;
				case "END": continue;
				case "TAG_-0": case "TAG_L0": case "TAG_M0": case "TAG_S0": case "TAG_T0": $lastCelo=0; break;
				case "TAG_-1": case "TAG_L1": case "TAG_M1": case "TAG_S1": case "TAG_T1": $lastCelo=1; break;
				default: $celo[$idperro]=$lastCelo;
			}
		}
		// fase 4: componemos el orden de salida en base a los resultados obtenidos
		$ordensalida=$this->default_orden;
		foreach ($orden['rows'] as $item) {
			// $this->myLogger->trace("parsing row:".$item['IDPerro']);
			$ordensalida=$this->insertIntoList($ordensalida,$item['IDPerro'],$item['Categoria'],$celo[$item['IDPerro']]);
		}
		$this->setOrden($manga,$ordensalida);
		$this->myLogger->trace("El orden de salida original era:\n$obj->Orden_Salida");
		$this->myLogger->trace("El orden de salida nuevo es:\n$ordensalida");
		return $ordensalida;
		$this->myLogger->leave();
	}
	
	function dragAndDrop($jornada,$manga,$from,$to,$where) {
		$this->myLogger->enter();
		if ( ($manga<=0) || ($from<=0) || ($to<=0)) {
			return $this->error("dnd: either Manga:$manga or SrcIDPerro:$from or DestIDPerro:$to are invalid");
		}
		// recuperamos el orden de salida
		$ordensalida = $this->getOrden ( $manga );
		// extraemos "from" de donde este
		$str = ",$from,";
		$ordensalida = str_replace ( $str , "," , $ordensalida );
		$str1 = ",$to,";
		$str2 = ($where==0)? ",$from,$to," : ",$to,$from,";
		$ordensalida = str_replace( $str1 , $str2 , $ordensalida );
		$this->setOrden($manga,$ordensalida);	
		$this->myLogger->leave();
		return "";
	}

} // class

?>