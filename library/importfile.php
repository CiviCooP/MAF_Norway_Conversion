<?php

require_once('importNAVN.php');
require_once('importADRESSE.php');
require_once('importPOSTSTED.php');
require_once('importFYLKE.php');
require_once('importKOMMUNE.php');
require_once('importINFOTYPE.php');
require_once('importINFOPROFIL.php');
require_once('importAVTALE.php');
require_once('importPRODUKTTYPE.php');
require_once('importGIVER.php');
require_once('importINNBETALING.php');
require_once('importPOSTERING.php');
require_once('importAKTIVITET.php');

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
			case 'PMF_MAF.ADRESSE.txt':
				return new importADRESSE($this->pdo, $this->config);
				break;
			case 'PMF_MAF.POSTSTED.txt':
				return new importPOSTSTED($this->pdo, $this->config);
				break;
			case 'PMF_MAF.KOMMUNE.txt':
				return new importKOMMUNE($this->pdo, $this->config);
				break;
			case 'PMF_MAF.FYLKE.txt':
				return new importFYLKE($this->pdo, $this->config);
				break;
			case 'PMF_MAF.INFOTYPE.txt':
				return new importINFOTYPE($this->pdo, $this->config);
				break;
			case 'PMF_MAF.INFOPROFIL.txt':
				return new importINFOPROFIL($this->pdo, $this->config);
				break;
			case 'PMF_MAF.AVTALE.txt':
				return new importAVTALE($this->pdo, $this->config);
				break;
			case 'PMF_MAF.PRODUKTTYPE.txt':
				return new importPRODUKTTYPE($this->pdo, $this->config);
				break;
			case 'PMF_MAF.GIVER.txt':
				return new importGIVER($this->pdo, $this->config);
				break;
			case 'PMF_MAF.INNBETALING.txt':
				return new importINNBETALING($this->pdo, $this->config);
				break;
			case 'PMF_MAF.POSTERING.txt':
				return new importPOSTERING($this->pdo, $this->config);
				break;
			case 'PMF_MAF.AKTIVITET.txt':
				return new importAKTIVITET($this->pdo, $this->config);
				break;
		}
		return null;
	}

}