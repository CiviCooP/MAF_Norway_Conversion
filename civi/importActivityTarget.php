<?php

class importActivityTarget {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $count = 0;

	protected $util;
	
	protected $fields;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset, $limit) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->fields = new tempCustomFields($pdo, $api, $config);
		$this->util = new contactUtils($pdo, $civi_pdo, $api, $config);
		
		$this->count = $this->import($offset, $limit);
	}
	
	protected function loadAllRecords($offset, $limit) {
		$sql = "SELECT * FROM `PMF_MAF_AKTIVITET_txt` LIMIT ".$offset.", ".$limit;
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt;
	}
	
	public function getCount() {
		return $this->count;
	}
	
	protected function import($offset, $limit) {
		$stmnt = $this->loadAllRecords($offset, $limit);
		$i=0;
		while ($row = $stmnt->fetch()) {
			$this->importActivityTarget($row);
			$i ++;
		}
		return $i;
	}
	
	protected function importActivityTarget($row) {
		$doNotImport = false;
		
		$contactId = $this->util->getContactIdFromNavn($row['L_NAVN_ID']);
		if (!$contactId) {
			$doNotImport = true;
		}
		
		$activity_id = $this->getActivity($row);
		
		if ($activity_id === false) {
			$doNotImport = true;
		}
		
		if ($doNotImport) {
			return;
		}
		
		$sql_check = "SELECT * FROM `civicrm_activity_target` WHERE `activity_id` = '".$activity_id."' AND `target_contact_id` = '".$contactId."';";
		$civi_check = $this->civi_pdo->prepare($sql_check, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$civi_check->execute();
		if (!$civi_check->rowCount()) {
			$sql = "INSERT INTO `civicrm_activity_target` (`activity_id`, `target_contact_id`) VALUES('".$activity_id."', '".$contactId."');";
			$civi = $this->civi_pdo->exec($sql);
		}
	}
	
	protected function getActivity($row) {
		$activity = false;
		
		
		$group = $this->fields->getCustomGroup('maf_norway_aksjon_import');
		if (!$group) {
			return false;
		}
		$field = $this->fields->getCustomFieldFull('aksjon_id');
		if (!$field) {
			return false;
		}
		
		$sql = "SELECT `entity_id` FROM `".$group->table_name."` WHERE `".$field->column_name."` = '".$row['L_AKSJON_ID']."';";
		$stmnt = $this->civi_pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		if ($r = $stmnt->fetch()) {
			return $r['entity_id'];
		}
		
		return false;
	}

}