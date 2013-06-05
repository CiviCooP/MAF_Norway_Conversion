<?php

require_once('config.php');
require_once('importfile.php');

class Conversion {

	protected $config;
	
	protected $pdo;
	
	public function __construct(Conversion_Config $config) {
	
		set_time_limit(4*60); 
	
		$this->config = $config;
		$this->pdo = new PDO('mysql:host='.$this->config->db_hostname.';dbname='.$this->config->db_name, $this->config->db_username, $this->config->db_password);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
		$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); 
	}
	
	public function import() {
		$this->importfile('PMF_MAF.NAVN.txt');
	}
	
	protected function importFile($name) {
		$import = new importfile($this->pdo, $this->config, $name);
	}
}

$conversion = new Conversion(new Conversion_Config());
$conversion->import();