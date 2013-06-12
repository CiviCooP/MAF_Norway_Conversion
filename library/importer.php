<?php

require_once('columndefinition.php');

abstract class importer {
	
	protected $config;
	
	protected $pdo;
	
	protected $fRes; //file resources
	
	protected $columns;
	
	protected $currentLine = 0;
	
	/**
	 * @return String the filename
	 */
	abstract protected function getFilename();
	
	public function __construct(PDO $pdo, $config) {
		$this->pdo = $pdo;
		$this->config = $config;
		
		//open file
		$this->fRes = fopen( $this->getFullFilename(), 'r' );
		$this->init();
		$this->import();
	}
	
	public function __destruct() {
		fclose($this->fRes);
	}
	
	protected function import() {
		while($this->importLine()) {
			echo $this->currentLine . " = " . $this->pdo->lastInsertId() ."<br />\n";
			flush();
		}
		
	}
	
	protected function importLine() {
		$line = $this->readNextLine();
		if ($line === false) {
			return false;
		}
		$data = explode(";", $line);
		while(count($data) < count($this->columns)) {
			$line = $this->readNextLine();
			if ($line === false) {
				return false;
			}
			
			$data1 = $data;
			$data2 = explode(";", $line);
			$count = count($data);
			for($i=0; $i < count($data2); $i++) {
				if ($i==0) {
					$data1[$count - 1 + $i] .= $data2[$i];
				} else { 
					$data1[$count - 1 + $i] = $data2[$i];
				}
			}
			$data = $data1;
		} 
		$data = $this->prepareData($data);
		
		if (count($data) != count($this->columns)) {
			echo "Invalid dataset on line ".$this->currentLine."<br />";
			return true;
		}
		
		
		
		$columns = "";
		foreach($this->columns as $column) {
			if (strlen($columns)) {
				$columns .= ", ";
			}
			$columns .= "`".$column->name."`";
		}
		$values = "";
		$arrValues = array();
		foreach($data as $i => $value) {
			if (strlen($values)) {
				$values .= ", ";
			}
			
			if ($this->columns[$i]->type == "DATETIME") {
				$value = str_replace(".", "-", $value);
				$dt = new DateTime($value);
				$value = $dt->format("Y-m-d H:i:s");
			} elseif ($this->columns[$i]->type == "VARCHAR") {
				$value = utf8_encode($value);
			} elseif ($this->columns[$i]->type == "TEXT") {
				$value = utf8_encode($value);
			}
			
			$values .= ":".$this->columns[$i]->name;
			$arrValues[":".$this->columns[$i]->name] = $value;
		}
		$table = $this->getTableName();
		$query = "INSERT INTO `".$table."` (".$columns.") VALUES (".$values.")";
		$q = $this->pdo->prepare($query);
		return $q->execute($arrValues);
		return true;
	}
	
	protected function prepareData($data) {
		return $data;
	}
	
	protected function getColumnDefinition($name) {
		return new ColumnDefinition($name);
	}
	
	protected function init() {
		$line = $this->readNextLine();
		$headings = explode(";", $line);
		$this->columns = array();
		$i = 0;
		foreach($headings as $heading) {
			$this->columns[$i] = $this->getColumnDefinition($heading);
			$i++;
		}
		$this->createTable();
	}
	
	protected function createTable() {
		$fieldDefinitions = "";
		$indexDefinitions = "";
		
		foreach($this->columns as $col) {
			if (strlen($fieldDefinitions)) {
				$fieldDefinitions .= ",\n";
			}
			$fieldDefinitions .= $col->getDefinition();
		}
		
		$this->dropTable();
		
		$tablename = $this->getTableName();
		$def = "CREATE TABLE `".$tablename."`(\n".$fieldDefinitions."\n) ENGINE=innodb DEFAULT CHARSET=utf8;";
		$this->pdo->query($def);
	}
	
	protected function getTableName() {
		return str_replace(".", "_", $this->getFilename());
	}
	
	protected function dropTable() {
		$tableExists = true;
		$tablename = $this->getTableName();
		try {
			$stmnt = $this->pdo->query("SELECT 1 FROM `".$tablename."`");
			$stmnt->fetchAll();
		} catch (Exception $e) {
			$tableExists = false;
		}
		
		if ($tableExists) {
			$def = "DROP TABLE `".$tablename."`;";
			$this->pdo->query($def);
		}
	}
	
	protected function readNextline() {
		$this->currentLine++;
		return fgets($this->fRes);
	}
	
	protected function getFullFilename() {
		return $this->config->path . $this->getFilename();
	}
}