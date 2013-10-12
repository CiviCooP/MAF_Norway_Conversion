<?php

class importAksjon {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $count = 0;
	
	protected $activity_type_group_id;

	protected $util;
	
	protected $fields;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset, $limit) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->fields = new tempCustomFields($pdo, $api, $config);
		$this->util = new contactUtils($pdo, $civi_pdo, $api, $config);
		
		$this->activity_type_group_id = $this->loadActivityOptionGroup();
		
		$this->count = $this->import($offset, $limit);
	}
	
	protected function loadActivityOptionGroup() {
		if (!$this->api->OptionGroup->getSingle(array('name' => 'activity_type'))) {
			Throw new Exception('No activity type in option group');
		}
		return $this->api->id;
	}
	
	protected function loadAllRecords($offset, $limit) {
		$sql = "SELECT n.*, `at`.`A_BESKRIVELSE` AS `activity_type` FROM `PMF_MAF_AKSJON_txt` `n` INNER JOIN `PMF_MAF_AKSJONSTYPE_txt` `at` ON `n`.`A_AKSJONSTYPE_ID` = `at`.`A_AKSJONSTYPE_ID` LIMIT ".$offset.", ".$limit;
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
			$this->importAksjon($row);
			$i ++;
		}
		return $i;
	}
	
	protected function importAksjon($row) {
		$doNotImport = false;
		
		
		$activity_type = $this->createActivityType($row);
		
		if ($activity_type === false) {
			$doNotImport = true;
		}
		
		$sql = "SELECT * FROM `PMF_MAF_AKTIVITET_txt` WHERE `L_AKSJON_ID` = '".$row['L_AKSJON_ID']."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		if (!$stmnt->rowCount()) {
			$doNotImport = true; //no contacts to link this activity to
		}
		
		if ($doNotImport) {
			return;
		}
		
		$sql = "SELECT COUNT(*) AS `total`, D_DATO FROM `PMF_MAF_AKTIVITET_txt` WHERE `L_AKSJON_ID` = '".$row['L_AKSJON_ID']."' GROUP BY D_DATO ORDER BY `total`";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		if ($r = $stmnt->fetch()) {		
			$params['source_contact_id'] = 1; 
			$params['activity_type_id'] = $activity_type;
			$params['custom_'.$this->fields->getCustomField('aksjon_id')] = $row['L_AKSJON_ID'];
			$params['subject'] = $row['A_BESKRIVELSE'];
			$params['activity_date_time'] = $this->util->formatDate($r['D_DATO']);
			$params['status_id']  = 2; //completed
			
			if ($this->api->Activity->Create($params)) {
				$activity_id = $this->api->id;
			}
		}
	}
	
	protected function createActivityType($row) {
		$type = $row['A_AKSJONSTYPE_ID'];
		$description = $row['activity_type'];
		
		if ($this->api->OptionValue->getSingle(array('option_group_id' => $this->activity_type_group_id, 'name' => $type))) {
			//return $this->api->id;
			return $this->api->result->value;
		}
		
		$params['name'] = $type;
		$params['label']  = $type;
		$params['option_group_id'] = $this->activity_type_group_id;
		$params['description']  = $description;
		$params['is_active'] = '1';
		if ($this->api->OptionValue->Create($params)) {
			if ($this->api->OptionValue->getSingle(array('option_group_id' => $this->activity_type_group_id, 'name' => $type))) {
				//return $this->api->id;
				return $this->api->result->value;
			}
		}
		return false;
	}

}