<?php

require_once('config.php');

class Civi {

	protected $api;
	
	protected $config;
	
	protected $pdo;
	
	public function __construct(Conversion_Config $config) {
		$this->config = $config;
		
		$this->pdo = new PDO('mysql:host='.$this->config->db_hostname.';dbname='.$this->config->db_name, $this->config->db_username, $this->config->db_password);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
		$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); 
		
		require_once($this->config->civi_path."api/class.api.php");
		$this->api = new civicrm_api3 (array('conf_path'=> $this->config->drupal_path));
	}

}

$civi = new Civi(new Conversion_Config());