<?php

require_once('tempCustomFields.php');
require_once('importTagsGroups.php');

class importContacts {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $utils;
	
	protected $fields;
		
	protected $importTagsGroups;
	
	protected $base_id = 0;
	
	protected $count = 0;
	
	protected $isDeleted = '1';
	
	protected $debug = false;
	
	protected $tax_deduction_tag = false;
	protected $no_tax_deduction_tag = false;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset=0, $limit=100, $debug=false) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		if ($debug) {
			$this->isDeleted = '0';
			$this->debug = true;
		}
		
		$this->fields = new tempCustomFields($pdo, $api, $config);
		$this->importTagsGroups = new importTagsGroups($pdo, $civi_pdo, $api, $config);
		$this->utils = new contactUtils($pdo, $civi_pdo, $api, $config);
		
		$this->tax_deduction_tag = $this->importTagsGroups->create('Tag', 'name', 'Tax Deduction', false);
		$this->no_tax_deduction_tag = $this->importTagsGroups->create('Tag', 'name', 'No Tax Deduction', false);
		
		if ($offset == 0) {
			$this->civi_pdo->query("ALTER TABLE  `civicrm_contact` AUTO_INCREMENT =45000");
		}
		$this->count = $this->import($offset, $limit);
	}
	
	public function getCount() {
		return $this->count;
	}
	
	protected function loadAllRecords($offset, $limit) {
		if ($this->debug) {
			$cids = array(85, 22435, 1638, 23536, 195, 26763, 27550, 22225, 23604, 24175, 1750, 27604, 1481, 22372, 25267, 23522, 23438, 28446, 31240, 31416, 791, 28311, 26099, 27140, 29405, 1937, 29593, 23759, 23436, 27968, 24617, 26319, 24104, 28966, 30019, 27346, 28207, 25049, 25651, 780, 26763, 27529, 23261, 27862, 22311, 26519, 29839, 29173, 29221, 29697, 2190, 29079, 31054, 22214, 27985, 24745, 23242);
			$sql = "SELECT * FROM `PMF_MAF_NAVN_txt` `n` LEFT JOIN `PMF_MAF_ADRESSE_txt` `a` ON `n`.`L_NAVN_ID` = `a`.`L_NAVN_ID` WHERE `n`.`L_NAVN_ID` IN(".implode(",", $cids).") ORDER BY `a`.`L_NAVN_ID` LIMIT ".$offset.", ".$limit;
			echo $sql; echo "<br>";
		} else {
			$sql = "SELECT * FROM `PMF_MAF_NAVN_txt` `n` LEFT JOIN `PMF_MAF_ADRESSE_txt` `a` ON `n`.`L_NAVN_ID` = `a`.`L_NAVN_ID` ORDER BY `a`.`L_NAVN_ID` LIMIT ".$offset.", ".$limit;
		}
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt;
	}
	
	protected function findByPostcode($row) {
		$sql = "SELECT * FROM `PMF_MAF_POSTSTED_txt` WHERE `A_LAND_ID` = '".$row['A_LAND_ID']."' AND `A_POSTNUMMER` = '".$row['A_POSTNUMMER']."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
	
	protected function findKommune($kommune_id) {
		$sql = "SELECT * FROM `PMF_MAF_KOMMUNE_txt` WHERE `A_KOMMUNE_ID` = '".$kommune_id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
	
	protected function findFylke($fylke_id, $land_id) {
		$sql = "SELECT * FROM `PMF_MAF_FYLKE_txt` WHERE `I_FYLKE_ID` = '".$fylke_id."' AND `A_LAND_ID` = '".$land_id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
	
	protected function import($offset, $limit) {
		$stmnt = $this->loadAllRecords($offset, $limit);
		$i =0;
		while ($row = $stmnt->fetch()) {
			$this->importContact($row);
			$i++;
		}
		return $i;
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
		
		// skip the first contacts
		if ($row['L_NAVN_ID'] < 14) {
			return false;
		}
	
		$l_navn_id = $row['L_NAVN_ID'];
	
		$params = $this->getParamsForNewContact($row);
		
		$sql = "INSERT INTO `civicrm_contact` (`id`, `contact_type`) VALUES (".(int) $params['contact_id'].", '".$params['contact_type']."');";
		$stmnt = $this->civi_pdo->query($sql);
		
		if (!$this->api->Contact->Create($params)) {
			$sql = "DELETE FROM `civicrm_contact` WHERE `id` = '".(int) $params['contact_id']."';";
			$stmnt = $this->civi_pdo->query($sql);
			return false;
		}
		$contact_id = $this->api->id;
		
		
		unset($params);
		$params = array();
		$params['contact_id'] = $contact_id;
		$address_id = false;
		if ($this->determineAddress($row, $params)) {
			if ($this->api->Address->Create($params)) {
				$address_id = $this->api->id;
			}
		}
		$this->importPhones($row, $contact_id);
		$this->importEmail($row, $contact_id);
		$this->importWebsite($row, $contact_id);
		
		$this->importTagsGroups->import($contact_id, $l_navn_id);
		
		unset($params);
		$params['entity_table'] = 'civicrm_contact';
		$params['entity_id'] = $contact_id;
		$params['note']  = $row['A_KOMMENTAR'];
		$this->api->Note->create($params);
		
		//tax deduction
		if ($row['A_SKATTEFRAOK'] == 'J') {
			if ($this->tax_deduction_tag !== false) {
				$this->importTagsGroups->add('EntityTag', 'tag_id', $this->tax_deduction_tag, $contact_id);
			}
		} elseif ($row['A_SKATTEFRAOK'] == 'N') {
			if ($this->tax_deduction_tag !== false) {
				$this->importTagsGroups->add('EntityTag', 'tag_id', $this->no_tax_deduction_tag, $contact_id);
			}
		}
		
		/**
		 * If contact is a family then create a household members. The household it self is already created
		 */
		if ($row['A_NAVNETYPE_ID'] == 'C') {
			$this->createHouseHoldMembers($contact_id, $address_id, $row);
		}
		
		return true;
	}
	
	protected function createHouseHoldMembers($household_id, $household_address_id, $row) {
		$l_navn_id = $row['L_NAVN_ID'];
		$params = array();
				
		$params['first_name'] = $row['A_FORNAVN'];
		$params['nick_name'] = $row['A_KALLENAVN'];
		$params['last_name'] = $row['A_ETTERNAVN'];
		if (($pos = strpos($params['first_name'], ' og '))!==false) {
			$params['first_name'] = substr($params['first_name'], 0, $pos);
		}
		if (($pos = strpos($params['nick_name'], ' og '))!==false) {
			$params['nick_name'] = substr($params['nick_name'], 0, $pos);
		}
		
		$params['contact_type'] = 'Individual';
		$params['is_deleted'] = $this->isDeleted;
		if ($this->api->Contact->Create($params)) {
			$main_member_id = $this->api->id;
			
			$this->importTagsGroups->import($main_member_id, $l_navn_id);
			
			unset($params);
			$params = array();
			$params['contact_id'] = $main_member_id;
			$this->determineAddress($row, $params);
			if ($household_address_id) {
				$params['master_id'] = $household_address_id;
				$result = $this->api->Address->Create($params);
			}
			$this->importPhones($row, $main_member_id);
			$this->importEmail($row, $main_member_id);
			$this->importWebsite($row, $main_member_id);
			
			//after creating an address with a master id the relationship household member is automaticly created. 
			//So we will delete it and replace it by a relationship Head of household.
			unset($params);
			$params['contact_id_a'] = $main_member_id;
			$params['contact_id_b'] = $household_id;
			$params['relationship_type_id'] = 8; //household member
			if ($this->api->Relationship->GetSingle($params)) {
				$params['id'] = $this->api->id;
				$params['relationship_type_id'] = 7; //change to head of household
				$this->api->Relationship->Create($params);
			} else {
				unset($params);
				$params = array();
				$params['contact_id_a'] = $main_member_id;
				$params['contact_id_b'] = $household_id;
				$params['relationship_type_id'] = 7; //Head of houshold (standard type)
				$this->api->Relationship->Create($params);
			}
			
			unset($params);
			$params = array();
			$params['nick_name'] = $row['A_KALLENAVN'];
			$params['last_name'] = $row['A_ETTERNAVN'];
			$params['first_name'] = $row['A_FORNAVN'];
			if (($pos = strpos($params['nick_name'], ' og '))!==false) {
				$params['nick_name'] = substr($params['nick_name'], $pos + 4);
			}
			if (($pos = strpos($params['first_name'], ' og '))!==false) {
				$params['first_name'] = substr($params['first_name'], $pos + 4);
			}
			$params['contact_type'] = 'Individual';
			$params['is_deleted'] = $this->isDeleted;
			if ($this->api->Contact->Create($params)) {
				$partner_id = $this->api->id;
				
				unset($params);
				$params = array();
				$params['contact_id'] = $partner_id;
				$this->determineAddress($row, $params);
				if ($household_address_id) {
					$params['master_id'] = $household_address_id;
					$result = $this->api->Address->Create($params);
				}				
				
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
		if ($row['A_EPOSTADR']) {
			$params['email'] = $row['A_EPOSTADR'];
			$params['location_type_id'] = 1; //home
			$this->api->Email->Create($params);
		}
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
		if ($row['A_TLFPRIVAT']) {
			$params['phone'] = $row['A_TLFPRIVAT'];
			$params['location_type_id'] = 1; //home
			$this->api->Phone->Create($params);
		}
		
		//work phone
		if ($row['A_TLFJOBB']) {
			$params['phone'] = $row['A_TLFJOBB'];
			$params['location_type_id'] = 2; //work
			$this->api->Phone->Create($params);
		}
		
		//mobile 
		if ($row['A_TLFMOBIL']) {
			$params['phone'] = $row['A_TLFMOBIL'];
			$params['location_type_id'] = 1; //home
			$params['phone_type_id'] = 2; //mobile
			$this->api->Phone->Create($params);
		}
		
		//fax 
		if ($row['A_TLFTELEFAX']) {
			$params['phone'] = $row['A_TLFTELEFAX'];
			$params['location_type_id'] = 1; //work
			$params['phone_type_id'] = 3; //fax
			$this->api->Phone->Create($params);
		}
	}
	
	protected function getParamsForNewContact($row) {
		$params = array();
		
		$params['custom_'.$this->fields->getCustomField('l_navn_id')] = $row['L_NAVN_ID'];
		$params['contact_id'] = $this->base_id + (int) $row['L_NAVN_ID'];
		$params['is_deleted'] = $this->isDeleted;
		
		/*if ($params['contact_id'] < 3) {
			//there are two contacts with id 1 and 2. But civi has initially also two contacts with id 1 and 2
			$params['contact_id'] = $params['contact_id'] + 2;
		}*/
		
		$this->determineContactType($row, $params);
		$this->determineName($row, $params);
		$this->determineBirthdate($row, $params);
		$this->determineSocialSecurityNumber($row, $params);

		$this->determineReasonStop($row, $params);
		
		$this->determinePreferredCommunicationMethods($row, $params);
		
		return $params;
	}
	
	protected function determinePreferredCommunicationMethods($row, &$params) {
		$prefCom = array();
		if ($row['A_TMOK'] == 'J') {
			$prefCom[] = 1; //telephone
		}
		if ($row['A_POSTOK'] == 'J') {
			$prefCom[] = 3; //post
		}
		if ($row['A_BRUKAVEPOSTOK'] == 'J') {
			$prefCom[] = 2; //email
		}
		if ($row['A_BRUKAVSMSOK'] == 'J') {
			$prefCom[] = 4; //sms
		}
		if (count($prefCom)) {
			$params['preferred_communication_method'] = implode("", $prefCom);
		}
		
		if ($row['D_OFFDATO']) {
			$params['custom_'.$this->fields->getCustomField('d_offdato')] = $this->utils->formatDate($row['D_OFFDATO']);
		}
		$params['custom_'.$this->fields->getCustomField('a_offhumnei')] = $row['A_OFFHUMNEI'];
		$params['custom_'.$this->fields->getCustomField('a_offtelefonnei')] = $row['A_OFFTELEFONNEI'];
		$params['custom_'.$this->fields->getCustomField('a_offpostnei')] = $row['A_OFFPOSTNEI'];
	}
	
	protected function determineReasonStop($row, &$params) {
		if ($row['D_SLUTTET']) {
			$params['custom_'.$this->fields->getCustomField('d_stoppet')] = $this->utils->formatDate($row['D_SLUTTET']);
			$params['custom_'.$this->fields->getCustomField('a_stoppaarsak')] = $this->utils->getReason($row['A_SLUTTAARSAK']);
		} elseif ($row['D_STOPPET']) {
			$params['custom_'.$this->fields->getCustomField('d_stoppet')] = $this->utils->formatDate($row['D_STOPPET']);
			$params['custom_'.$this->fields->getCustomField('a_stoppaarsak')] = $this->utils->getReason($row['A_STOPPAARSAK']);
		}
		
		$params['custom_'.$this->fields->getCustomField('d_opprettet')] = $this->utils->formatDate($row['D_OPPRETTET']);
	}
	
	protected function determineAddress($row, &$params) {
		
		if ($row['I_ADRESSENR'] === null) {
			return false;
		}
	
		$params['location_type_id']  = 1;
		$params['manual_geo_code'] = 1;
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
		return true;
	}
	
	protected function determineSocialSecurityNumber($row, &$params) {
		switch ($row['A_NAVNETYPE_ID']) {
			case 'A':
			case 'B':
			case 'C':
				if (strlen($row['A_NAVNEID'])) { 
					$params['custom_'.$this->fields->getCustomField('NO_SocialSecurityNo')] = $row['A_NAVNEID'];
				}
				break;
			default:
				if (strlen($row['A_NAVNEID'])) { 
					$params['custom_'.$this->fields->getCustomField('Organisasjonsnummer')] = $row['A_NAVNEID'];
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
				$params['household_name'] = $row['A_FORNAVN'] . ' ' . $row['A_ETTERNAVN'];
				$params['nick_name'] = $row['A_KALLENAVN'];
				break;
			case 'F': //Firma = Company
			case 'G': //Forening = Association
			case 'H': //Flyplass = airport
			case 'O': //Organisasjon = organisation
				$params['organization_name'] = $row['A_ETTERNAVN'];
				$params['nick_name'] = $row['A_KALLENAVN'];
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
				$params['gender_id'] = 2; 
				break;
			case 'C': //familie = family
				$params['contact_type'] = 'Household';
				break;
			case 'U': //Ufullstendig = Incomplete
			case 'D': //Ukjent = unknown
				$params['contact_type'] = 'Individual';
				break;
			case 'F': //Firma = Company
				$params['contact_type'] = 'Organization';
				$params['contact_sub_type'] = $this->utils->getContactType('Firma', 'Firma', 3);				
				break;
			case 'G': //Forening = Association
				$params['contact_type'] = 'Organization';
				$params['contact_sub_type'] = $this->utils->getContactType('Forening', 'Forening', 3);
				break;
			case 'H': //Flyplass = airport
				$params['contact_type'] = 'Organization';
				$params['contact_sub_type'] = $this->utils->getContactType('Flyplass', 'Flyplass', 3);
				break;
			case 'O': //Organisasjon = organisation
				$params['contact_type'] = 'Organization';
				break;
		}
	}
}