<?php

class importAktivitet {
	
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
		$sql = "SELECT a.*, `at`.`A_BESKRIVELSE` AS `activity_type`, `n`.`A_BESKRIVELSE` AS `A_BESKRIVELSE`, `n`.`A_AKSJONSTYPE_ID` AS `A_AKSJONSTYPE_ID` FROM `PMF_MAF_AKTIVITET_txt` AS `a` INNER JOIN `PMF_MAF_AKSJON_txt` `n` ON `n`.`L_AKSJON_ID` = `a`.`L_AKSJON_ID` INNER JOIN `PMF_MAF_AKSJONSTYPE_txt` `at` ON `n`.`A_AKSJONSTYPE_ID` = `at`.`A_AKSJONSTYPE_ID` WHERE `a`.`A_AKTIVITETSTYPE_ID` = 'DM' OR `a`.`A_AKTIVITETSTYPE_ID` = 'BL' OR `a`.`A_AKTIVITETSTYPE_ID` = 'UA'  LIMIT ".$offset.", ".$limit;
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
			$this->importAktivitet($row);
			$i ++;
		}
		return $i;
	}
	
	protected function importAktivitet($row) {
		$doNotImport = false;
		$activity_type = false;
		$contactId = false;
		
		if (!$doNotImport) {
			$activity_type = $this->createActivityType($row);
		}		
		if ($activity_type === false) {
			$doNotImport = true;
		}
		
		if (!$doNotImport) {
			$contactId = $this->util->getContactIdFromNavn($row['L_NAVN_ID']);
		}
		if (!$contactId) {
			$doNotImport = true;
		}
		
		if ($doNotImport) {
			return;
		}
		
		$params['source_contact_id'] = $contactId; 
		$params['activity_type_id'] = $activity_type;
		$params['custom_'.$this->fields->getCustomField('aksjon_id')] = $row['L_AKSJON_ID'];
		$params['custom_'.$this->fields->getCustomField('aktivitet_id')] = $row['L_AKTIVITET_ID'];
		$params['custom_'.$this->fields->getCustomField('aksjon_kid9')] = $this->kid_number_generate_9digit($row['L_AKTIVITET_ID']);
		$params['custom_'.$this->fields->getCustomField('aksjon_kid15')] = $this->kid_number_generate_15digit($row['L_AKTIVITET_ID'], $row['L_NAVN_ID']);
		
		$params['subject'] = $row['A_BESKRIVELSE'];
		$params['activity_date_time'] = $this->util->formatDate($row['D_DATO']);
		$params['status_id']  = 2; //completed
		if ($this->api->Activity->Create($params)) {
			$activity_id = $this->api->id;
		}
	}
	
	/*
	* Generate checksum digit using the Luhn algorithm
	*/
	protected function kid_number_generate_checksum_digit($number) {
		$sum    = 0;
		$parity = strlen((string)$number) % 2;
		for ($i = strlen((string)$number)-1; $i >= 0; $i--) {
			$digit = $number[$i];
			if (!$parity == ($i % 2)) {
				$digit <<= 1;
			}
			$digit = ($digit > 9) ? ($digit - 9) : $digit;
			$sum += $digit;
		}
		return $sum % 10;
	}
	
	/*
	* Generate a 9 digit kid number
	*/
	protected function kid_number_generate_9digit($aktiviteit_id) {
		$kid_number = str_pad($aktiviteit_id, 8, '0', STR_PAD_LEFT);
		return $kid_number . $this->kid_number_generate_checksum_digit($kid_number);
	}
	
	/*
	* Generate a 15 digit kid number
	*/
	protected function kid_number_generate_15digit($navn_id, $aktiviteit_id) {
		$kid_number = str_pad($navn_id, 6, '0', STR_PAD_LEFT);
		$kid_number = $kid_number . str_pad($aktiviteit_id, 8, '0', STR_PAD_LEFT);
		return $kid_number . $this->kid_number_generate_checksum_digit($kid_number);
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