<?php

require_once('config.php');
require_once('library/importfile.php');

class Conversion {

	protected $config;
	
	protected $pdo;
	
	public function __construct(Conversion_Config $config) {
	
		set_time_limit(8*60); 
	
		$this->config = $config;
		$this->pdo = new PDO('mysql:host='.$this->config->db_hostname.';dbname='.$this->config->db_name.";charset=utf-8", $this->config->db_username, $this->config->db_password,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
		$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); 
	}
	
	public function import() {
		$this->importfile('PMF_MAF.FYLKE.txt'); //counties
		$this->importfile('PMF_MAF.KOMMUNE.txt'); //kommune
		$this->importfile('PMF_MAF.POSTSTED.txt'); //postcodes
		$this->importfile('PMF_MAF.NAVN.txt');
		$this->importfile('PMF_MAF.ADRESSE.txt');
		//tags and groups
		$this->importfile('PMF_MAF.INFOTYPE.txt');
		$this->importfile('PMF_MAF.INFOPROFIL.txt');
	}
	
	public function importPledges() {
		$this->importfile('PMF_MAF.AVTALE.txt'); //pledges
		$this->importfile('PMF_MAF.PRODUKTTYPE.txt'); //financial types
	}
	
	protected function importFile($name) {
		$import = new importfile($this->pdo, $this->config, $name);
	}
}

$conversion = new Conversion(new Conversion_Config());
if ($_GET['pledges'] == 1) {
	$conversion->importPledges();
} else {
	$conversion->import();
}