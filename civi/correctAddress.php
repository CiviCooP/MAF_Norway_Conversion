<?php

require_once('contactUtils.php');

class correctAddress {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $utils;
	
	protected $count = 0;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset=0, $limit=100, $debug=false) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->utils = new contactUtils($pdo, $civi_pdo, $api, $config);
		
		$this->count = $this->import($offset, $limit);
	}
	
	public function getCount() {
		return $this->count;
	}
	
	protected function import($offset, $limit) {
		$stmnt = $this->loadAllRecords($offset, $limit);
		$i =0;
		while ($row = $stmnt->fetch()) {
			$this->correctAddress($row);
			$i++;
		}
		return $i;
	}
	
	protected function loadAllRecords($offset, $limit) {
		$sql = "SELECT `a`.*, `p`.`A_KOMMUNE_ID`, `p`.`A_POSTSTED` FROM `PMF_MAF_ADRESSE_txt` `a` INNER JOIN `PMF_MAF_POSTSTED_txt` `p` ON `a`.`A_POSTNUMMER` = `p`.`A_POSTNUMMER` INNER JOIN `PMF_MAF_KOMMUNE_txt` `k` ON `p`.`A_KOMMUNE_ID` = `k`.`A_KOMMUNE_ID` WHERE `a`.`A_LAND_ID` = 'NO' AND `k`.`I_FYLKE_ID` = 15 LIMIT ".$offset.", ".$limit;
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		echo $sql; echo "<hr>";
		return $stmnt;
	}
	
	protected function correctAddress($row) {
		$doNotImport = false;
		$contactId = false;
		echo $row['L_NAVN_ID'].' - ';
		$contactId = $this->utils->getContactIdFromNavn($row['L_NAVN_ID']);
		if (!$contactId) {
			$doNotImport = true;
		}
		
		if ($doNotImport) {
			return;
		}
		//echo $contactId; exit();
		$params = array();
		$params['contact_id'] = $contactId;
		$address_id = false;
		if ($this->determineAddress($row, $params)) {
			if ($this->api->Address->Create($params)) {
				$address_id = $this->api->id;
				echo "changed address for ".$contactId."<br>";
			}
		}
		
		$rel_api = clone $this->api;
		if ($rel_api->Relationship->get(array('contact_id_b' => $contactId))) {
			foreach($rel_api->result->values as $value) {
				if ($value->relationship_type_id == 7 || $value->relationship_type_id == 8) {
					$params['contact_id']  = $value->contact_id_a;
					$params['master_id']  = $address_id;
					$result = $this->api->Address->Create($params);
					
					//a master address by relationship type 8 also create a relationship type 7.
					if ($value->relationship_type_id == 7) {					
						if ($this->api->Relationship->getsingle(array('contact_id_b' => $contactId, 'contact_id_a' => $value->contact_id_a, 'relationship_type_id' => 8))) {
							$this->api->Relationship->Delete(array('id' => $this->api->id));
						}
					}
					
					echo "changed address for ".$params['contact_id']."<br>";
				}
			}
		}
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
		$params['city'] = $row['A_POSTSTED'];
		$params['state_province_id'] = 'Møre ag Romsdal';
		return true;
	}
	
}