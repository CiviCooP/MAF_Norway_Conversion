<?php

require_once('importNAVN.php');

class importfile {

	protected $config;
	
	protected $pdo;
	
	protected $importer;
	
	public function __construct(PDO $pdo, $config, $name) {
		$this->pdo = $pdo;
		$this->config = $config;
		
		$this->importer = $this->getImporter($name);
	}
	
	protected function getImporter($name) {
		switch($name) {
			case 'PMF_MAF.NAVN.txt':
				return new importNAVN($this->pdo, $this->config);
				break;
		}
		return null;
	}

}