<?php

require_once('tempCustomFields.php');
require_once('importTagsGroups.php');

class importPledges {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
	}
	
	protected function loadAllRecords() {
		$sql = "SELECT * FROM `PMF_MAF_AVTALE_txt` `n`";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt;
	}
	
	protected function import() {
		$stmnt = $this->loadAllRecords();
		while ($row = $stmnt->fetch()) {
			$this->importPledge($row);
		}
	}
	
	protected function importPledge($row) {
	}
}