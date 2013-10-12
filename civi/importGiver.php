<?php

class importGiver {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $count = 0;

	protected $util;

	protected $tags;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset, $limit, $debug) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->util = new contactUtils($pdo, $civi_pdo, $api, $config);
		$this->tags = new importTagsGroups($pdo, $civi_pdo, $api, $config);
		
		$this->count = $this->import($offset, $limit);
	}
	
	protected function loadAllRecords($offset, $limit) {
		$sql = "SELECT * FROM `PMF_MAF_GIVER_txt` `n` ORDER BY `n`.`L_NAVN_ID` LIMIT ".$offset.", ".$limit;
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
			$this->importGiver($row);
			$i ++;
		}
		return $i;
	}
	
	protected function importGiver($row) {
		$doNotImport = false;
		$contactId = $this->util->getContactIdFromNavn($row['L_NAVN_ID']);
		if (!$contactId) {
			echo "<span style=\"color: red;\">Contact ".$row['L_NAVN_ID']." not found</span><br>";
			$doNotImport = true;			
		}
		
		if ($doNotImport) {
			return;
		}
		
		$this->undeleteContact($contactId);
		
		$ThankYouLetters = $row['A_TAKKEBREV'] == 'N' ? false : true;
		$this->updateContact($contactId, $ThankYouLetters);
		
		//update the household members as well
		$api = clone $this->api; //keep a seperate api class for this call otherwise it wil mix up
		if ($api->Relationship->get(array('contact_id_b' => $contactId))) {
			foreach($api->values as $value) {
				if ($value->relationship_type_id == 7 || $value->relationship_type_id == 8) {
					$this->undeleteContact($value->contact_id_a);
					$this->updateContact($value->contact_id_a, $ThankYouLetters);
				}
			}
		}
	}
	
	protected function undeleteContact($contactId) {
		$params['contact_id'] = $contactId;
		$params['is_deleted'] = '0';
		$params['version'] = 3;
		$this->api->Contact->Create($params);
	}
	
	protected function updateContact($contactId, $ThankYouLetters) {
		$tag = "Send thank you letter";
		if (!$ThankYouLetters) {
			$tag = "Do not send thank you letter";
		}
		
		$tag_id = $this->tags->create('Tag', 'name', $tag, false);
		if ($tag_id !== false) {
			$this->tags->add('EntityTag', 'tag_id', $tag_id, $contactId);
		}
	}

}