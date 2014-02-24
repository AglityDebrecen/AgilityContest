<?php
require_once("DBObject.php");
require_once("OrdenSalida.php");
require_once("Mangas.php");

class Resultados extends DBObject {
	protected $manga;
	protected $jornada;
	protected $cerrada;

	/**
	 * Constructor
	 * @param {string} $file caller for this object
	 * @param {string} $manga Manga ID
	 * @throws Exception when
	 * - cannot contact database 
	 * - invalid manga ID
	 * - manga is closed
	 */
	function __construct($file,$manga) {
		parent::__construct($file);
		if ($manga<=0) {
			$this->errormsg="Resultados::Construct invalid Manga ID";
			throw new Exception($this->errormsg);
		}
		$this->manga=$manga;
		// obtenemos el id de jornada y vemos si la manga esta cerrada
		$str="SELECT Jornada,Cerrada FROM Mangas WHERE ID=$manga";
		$rs=$this->query($str);
		if (!$rs) {
			$this->errormsg("Cannot retrieve data for manga $manga");
			throw new Exception($this->errormsg);
		}
		$row=$rs->fetch_object();
		$rs->free();
		if (!$row) {
			$this->errormsg("Manga $manga does not exists in database");
			throw new Exception($this->errormsg);
		}
		$this->jornada=$row->Jornada;
		$this->cerrada=$row->Cerrada;
	}
		
	
	/**
	 * Inserta perro en la lista de resultados de la manga
	 * los datos del perro se toman de la tabla perroguiaclub
	 * @param {integer} $idperro
	 * @return "" on success; null on error
	 */
	function insert($idperro) {
		$this->myLogger->enter();
		if ($idperro<=0) return $this->error("No idperro specified");
		if ($this->cerrada!=0) return $this->error("Manga ".$this->manga." is closed");
		
		// phase 1: retrieve dog data
		$str="SELECT * FROM PerroGuiaClub WHERE ( IDPerro = $idperro )";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		$perro =$rs->fetch_object(); // should be only one item
		$rs->free();
		if (!$perro) return $this->error("No information on IDPerro: $idperro");
		
		// phase 2: insert into resultados. On duplicate ($manga,$idperro) key an error will occur
		$str="INSERT INTO Resultados ( Manga , Nombre , IDPerro , Licencia , Categoria , Grado , Guia , Club ) VALUES ("
				.$this->manga.		",'"	.$perro->Nombre. 	"'," 	.$perro->IDPerro.	",'"	.$perro->Licencia.	"','"
				.$perro->Categoria. "','" 	.$perro->Grado.		"','"	.$perro->Guia.		"','"	.$perro->Club .	"')";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		$this->myLogger->leave();
		return "";
	}
	
	/**
	 * Borra el idperro de la lista de resultados de la manga
	 * @param {integer} $idperro
	 * @return "" on success; null on error
	 */
	function delete($idperro) {
		$this->myLogger->enter();
		if ($idperro<=0) return $this->error("No IDPerro specified");
		if ($this->cerrada!=0) return $this->error("Manga ".$this->manga." is closed");
		$str="DELETE * FROM Resultados WHERE ( IDPerro = $idperro ) AND ( Manga=".$this->manga.")";
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
		if ($idperro<=0) return $this->error("No IDPerro specified");
		$str="SELECT * FROM Resultados WHERE ( IDPerro = $idperro ) AND ( Manga=".$this->manga.")";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		$row=$rs->fetch_array();
		$rs->free();
		if(!$row) return $this->error("No Results for IDPerro:$idperro on Manga:".$this->manga);
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
		if ($idperro<=0) return $this->error("No IDPerro specified");
		if ($this->cerrada!=0) return $this->error("Manga ".$this->manga." is closed");
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
		// comprobamos la coherencia de los datos recibidos y ajustamos
		// NOTA: el orden de estas comprobaciones es MUY importante
		if ($rehuses>=3) { $tiempo=0; $eliminado=1; $nopresentado=0;}
		if ($tiempo>0) {$nopresentado=0;}
		if ($eliminado==1) { $tiempo=0; $nopresentado=0; }
		if ($nopresentado==1) { $tiempo=0; $eliminado=0; $faltas=0; $rehuses=0; $tocados=0; }
		if ( ($tiempo==0) && ($eliminado==0)) { $nopresentado=1; $faltas=0; $rehuses=0; $tocados=0; }
		if ( ($tiempo==0) && ($eliminado==1)) { $nopresentado=0; }
		// efectuamos el update
		$sql="UPDATE Resultados 
			SET Entrada='$entrada' , Comienzo='$comienzo' , 
				Faltas=$faltas , Rehuses=$rehuses , Tocados=$tocados ,
				NoPresentado=$nopresentado , Eliminado=$eliminado , 
				Tiempo=$tiempo , Observaciones='$observaciones' 
			WHERE (IDPerro=$idperro) AND (Manga=".$this->manga.")";
		$rs=$this->query($sql);
		if (!$rs) return $this->error($this->conn->error);
		$this->myLogger->leave();
		return $this->select($idperro);
	}
	
	/**
	 * Presenta una tabla ordenada segun el orden de salida asociado a la manga
	 * @return null on error else array en formato easyui datagrid
	 */
	function enumerate() {
		$this->myLogger->enter();
		
		// fase 1: obtenemos el orden de salida
		$os=new OrdenSalida("Competicion");
		$orden=$os->getOrden($this->manga);
		if ($orden==="") {
			// si no hay orden de salida predefinido, genera uno al azar
			$this->myLogger->notice("There is no OrdenSalida predefined for manga:".$this->manga);
			$orden= $os->random($this->jornada,$this->manga);
		}
		$this->myLogger->debug("El orden de salida es: \n$orden");
		$lista = explode ( ",", $orden );
		
		// fase 2: obtenemos todos los resultados de esta manga 
		$str="SELECT * FROM Resultados WHERE (Manga=".$this->manga.")";
		$rs=$this->query($str);
		if (!$rs) return $this->error($this->conn->error);
		// y los guardamos en un array indexado por el idperro
		$data=array();
		while($row=$rs->fetch_array()) $data[$row["IDPerro"]]=$row;
		$rs->free();
		
		// fase 3 componemos el resultado siguiendo el orden de salida
		$items=array();
		$count=0;
		$celo=0;
		foreach ($lista as $idperro) {
			switch($idperro) {
				// separadores
				case "BEGIN": case END: continue;
				case "TAG_-0": case "TAG_L0": case "TAG_M0": case "TAG_S0": case "TAG_T0": $celo=0; continue;
				case "TAG_-1": case "TAG_L1": case "TAG_M1": case "TAG_S1": case "TAG_T1": $celo=1; continue;
				default: // idperroes
					if (!isset($data[$idperro])) {
						$this->myLogger->warn("No Results for idperro:$idperro. Creating default one");
						$this->insert($idperro);
						$data[$idperro]=$this->select($idperro);
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
}
?>