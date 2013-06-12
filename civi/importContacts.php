<?php

require_once('tempCustomFields.php');
require_once('importTagsGroups.php');

class importContacts {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $fields;
		
	protected $importTagsGroups;
	
	protected $base_id = 0;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->fields = new tempCustomFields($pdo, $api, $config);
		$this->importTagsGroups = new importTagsGroups($pdo, $civi_pdo, $api, $config);
		
		$this->civi_pdo->query("ALTER TABLE  `civicrm_contact` AUTO_INCREMENT =45000");
		$this->import();
	}
	
	protected function loadAllRecords() {
		$sql = "SELECT * FROM `pmf_maf_navn_txt` `n` INNER JOIN `pmf_maf_adresse_txt` `a` ON `n`.`L_NAVN_ID` = `a`.`L_NAVN_ID`";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt;
	}
	
	protected function findByPostcode($row) {
		$sql = "SELECT * FROM `pmf_maf_poststed_txt` WHERE `A_LAND_ID` = '".$row['A_LAND_ID']."' AND `A_POSTNUMMER` = '".$row['A_POSTNUMMER']."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
	
	protected function findKommune($kommune_id) {
		$sql = "SELECT * FROM `pmf_maf_kommune_txt` WHERE `A_KOMMUNE_ID` = '".$kommune_id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
	
	protected function findFylke($fylke_id, $land_id) {
		$sql = "SELECT * FROM `pmf_maf_fylke_txt` WHERE `I_FYLKE_ID` = '".$fylke_id."' AND `A_LAND_ID` = '".$land_id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
	
	protected function import() {
		$stmnt = $this->loadAllRecords();
		while ($row = $stmnt->fetch()) {
			$this->importContact($row);
		}
	}
	
	protected function importContact($row) {
		//ignore unknown contacts
		if ($row['A_NAVNETYPE_ID'] == 'D') {
			return false;
		}
		//ignore duplicate contacts
		if (isset($row['L_DUPLIKATVINNER_ID']) && $row['L_DUPLIKATVINNER_ID']) {
			return false;
		}
	
		$l_navn_id = $row['L_NAVN_ID'];
	
		$params = $this->getParamsForNewContact($row);		
		$sql = "INSERT INTO `civicrm_contact` (`id`, `contact_type`) VALUES (".(int) $params['contact_id'].", '".$params['contact_type']."');";
		$stmnt = $this->civi_pdo->query($sql);
		
		if (!$this->api->Contact->Update($params)) {
			return false;
		}
		$contact_id = $this->api->id;
		
		
		unset($params);
		$params = array();
		$params['contact_id'] = $contact_id;
		$this->determineAddress($row, $params);
		$result = $this->api->Address->Create($params);
		$this->importPhones($row, $contact_id);
		$this->importEmail($row, $contact_id);
		$this->importWebsite($row, $contact_id);
		
		$this->importTagsGroups->import($contact_id, $l_navn_id);
		
		/**
		 * If contact is a family then create a household and its members. 
		 * Main individual of the household is already created
		 */
		if ($row['A_NAVNETYPE_ID'] == 'C') {
			$this->createHouseHold($contact_id, $row);
		}
		
		return true;
	}
	
	protected function createHouseHold($main_member_id, $row) {
		$l_navn_id = $row['L_NAVN_ID'];
		$params = array();
		$params['household_name'] = $row['A_FORNAVN'] . ' ' . $row['A_ETTERNAVN'];
		$params['contact_type'] = 'Household';
		if ($this->api->Contact->Create($params)) {
			$household_id = $this->api->id;
			
			$this->importTagsGroups->import($household_id, $l_navn_id);
			
			unset($params);
			$params = array();
			$params['contact_id'] = $household_id;
			$this->determineAddress($row, $params);
			$result = $this->api->Address->Create($params);
			$this->importPhones($row, $household_id);
			$this->importEmail($row, $household_id);
			$this->importWebsite($row, $household_id);
			
			unset($params);
			$params = array();
			$params['contact_id_a'] = $main_member_id;
			$params['contact_id_b'] = $household_id;
			$params['relationship_type_id'] = 7; //Head of houshold (standard type)
			$this->api->Relationship->Create($params);
			
			unset($params);
			$params = array();
			$params['nick_name'] = $row['A_KALLENAVN'];
			$params['last_name'] = $row['A_ETTERNAVN'];
			$params['frist_name'] = $row['A_FORNAVN'];
			if (($pos = strpos($params['nick_name'], ' og '))!==false) {
				$params['nick_name'] = substr($params['nick_name'], $pos + 4);
			}
			if (($pos = strpos($params['frist_name'], ' og '))!==false) {
				$params['frist_name'] = substr($params['frist_name'], $pos + 4);
			}
			$params['contact_type'] = 'Individual';
			if ($this->api->Contact->Create($params)) {
				$partner_id = $this->api->id;
				
				$this->importTagsGroups->import($partner_id, $l_navn_id);
				
				unset($params);
				$params = array();
				$params['contact_id_a'] = $partner_id;
				$params['contact_id_b'] = $household_id;
				$params['relationship_type_id'] = 8; //member of houshold (standard type)
				$this->api->Relationship->Create($params);
			}
			
		}
	}
	
	protected function importEmail($row, $contact_id) {
		$params = array();
		$params['contact_id'] = $contact_id;
		
		//email
		$params['email'] = $row['A_EPOSTADR'];
		$params['location_type_id'] = 1; //home
		$this->api->Email->Create($params);
	}
	
	protected function importWebsite($row, $contact_id) {
		$params = array();
		$params['contact_id'] = $contact_id;
		
		//email
		$params['website'] = $row['A_WEBADR'];
		$this->api->Website->Create($params);
	}
	
	protected function importPhones($row, $contact_id) {
		$params = array();
		$params['contact_id'] = $contact_id;
		
		//home phone
		$params['phone'] = $row['A_TLFPRIVAT'];
		$params['location_type_id'] = 1; //home
		$this->api->Phone->Create($params);
		
		//work phone
		$params['phone'] = $row['A_TLFJOBB'];
		$params['location_type_id'] = 2; //work
		$this->api->Phone->Create($params);
		
		//mobile 
		$params['phone'] = $row['A_TLFMOBIL'];
		$params['location_type_id'] = 1; //home
		$params['phone_type_id'] = 2; //mobile
		$this->api->Phone->Create($params);
		
		//fax 
		$params['phone'] = $row['A_TLFTELEFAX'];
		$params['location_type_id'] = 1; //work
		$params['phone_type_id'] = 3; //fax
		$this->api->Phone->Create($params);
	}
	
	protected function getParamsForNewContact($row) {
		$params = array();
		
		$params['custom_'.$this->fields->getCustomField('l_navn_id')] = $row['L_NAVN_ID'];
		$params['contact_id'] = $this->base_id + (int) $row['L_NAVN_ID'];
		
		if ($params['contact_id'] < 3) {
			//there are two contacts with id 1 and 2. But civi has initially also two contacts with id 1 and 2
			$params['contact_id'] = $params['contact_id'] + 2;
		}
		
		$this->determineContactType($row, $params);
		$this->determineName($row, $params);
		$this->determineBirthdate($row, $params);
		$this->determineSocialSecurityNumber($row, $params);		
		return $params;
	}
	
	protected function determineAddress($row, &$params) {
		$params['location_type_id']  = 1;
		$params['street_address'] = $row['A_ADRESSE2'];
		if (strlen($row['A_ADRESSE1'])) {
			$params['supplemental_address_1'] = $row['A_ADRESSE1'];
		}
		
		if ($this->api->Country->get(array('iso_code'=>$row['A_LAND_ID']))) {
				$params['country_id'] = $this->api->id;
		}
		
		$params['postal_code'] = $row['A_POSTNUMMER'];	
		$postcode = $this->findByPostcode($row);		
		$params['city'] = $postcode['A_POSTSTED'];
		if ($postcode['A_KOMMUNE_ID'] != 'X') {
			//find state
			$kommune = $this->findKommune($postcode['A_KOMMUNE_ID']);
			if ($kommune['I_FYLKE_ID'] > 0) {
				$fylke = $this->findFylke($kommune['I_FYLKE_ID'], $row['A_LAND_ID']);
				$params['state_province_id'] = $fylke['A_NAVN'];
			}
		}
	}
	
	protected function determineSocialSecurityNumber($row, &$params) {
		switch ($row['A_NAVNETYPE_ID']) {
			case 'A':
			case 'B':
			case 'C':
				if ($this->api->CustomField->getsingle(array('name' => 'F_dselsnr'))) {
					if (strlen($row['A_NAVNEID'])) { 
						$params['custom_'.$this->api->id] = $row['A_NAVNEID'];
					}
				}
				break;
			default:
				if ($this->api->CustomField->getsingle(array('name' => 'Organisasjonsnummer'))) {
					if (strlen($row['A_NAVNEID'])) { 
						$params['custom_'.$this->api->id] = $row['A_NAVNEID'];
					}
				}
				break;
		}
	}
	
	protected function determineBirthdate($row, &$params) {
		if (strlen($row['I_FOEDSELSAAR']) && strlen($row['I_FOEDSELSMAANED']) && strlen($row['I_FOEDSELSDAG'])) {
			$params['birth_date']  = $row['I_FOEDSELSAAR'].'-'.$row['I_FOEDSELSMAANED'].'-'.$row['I_FOEDSELSDAG'];
		}
	}
	
	protected function determineName($row, &$params) {
		switch($row['A_NAVNETYPE_ID']) {
			case 'A': //Kvinne = female
			case 'B': //Mann = male
			case 'U': //Ufullstendig = Incomplete
			case 'D': //Ukjent = unknown
				$params['first_name'] = $row['A_FORNAVN'];
				$params['nick_name'] = $row['A_KALLENAVN'];
				$params['last_name'] = $row['A_ETTERNAVN'];
				break;
			case 'C': //familie = family
				$params['first_name'] = $row['A_FORNAVN'];
				$params['nick_name'] = $row['A_KALLENAVN'];
				$params['last_name'] = $row['A_ETTERNAVN'];
				if (($pos = strpos($params['first_name'], ' og '))!==false) {
					$params['first_name'] = substr($params['first_name'], 0, $pos);
				}
				if (($pos = strpos($params['nick_name'], ' og '))!==false) {
					$params['nick_name'] = substr($params['nick_name'], 0, $pos);
				}				
				break;
			case 'F': //Firma = Company
			case 'G': //Forening = Association
			case 'H': //Flypas = airport
			case 'O': //Organisasjon = organisation
				$params['organization_name'] = $row['A_ETTERNAVN'];;
				break;
		}
	}
	
	protected function determineContactType($row, &$params) {
		switch($row['A_NAVNETYPE_ID']) {
			case 'A': //Kvinne = female
				$params['contact_type'] = 'Individual';
				$params['gender_id'] = 1; 
				break;
			case 'B': //Mann = male
				$params['contact_type'] = 'Individual';
				$params['gender_id'] = 1; 
				break;
			case 'C': //familie = family
				$params['contact_type'] = 'Individual';
				break;
			case 'U': //Ufullstendig = Incomplete
			case 'D': //Ukjent = unknown
				$params['contact_type'] = 'Individual';
				break;
			case 'F': //Firma = Company
			case 'G': //Forening = Association
			case 'H': //Flypas = airport
			case 'O': //Organisasjon = organisation
				$params['contact_type'] = 'Organization';
				break;
		}
	}
}