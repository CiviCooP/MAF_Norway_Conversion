<?php

require_once('contactUtils.php');
require_once('importTagsGroups.php');

class correctRecurringContribution {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $count = 0;	
	
	protected $utils;
	
	protected $tags;
	
	protected $fields;
	
	protected $tag_pt_1;
	protected $tag_pt_2;
	protected $tag_pt_3;
	
	protected $tag_recurring_id;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset, $limit) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->utils = new contactUtils($pdo, $civi_pdo, $api, $config);
		$this->tags = new importTagsGroups($pdo, $civi_pdo, $api, $config);
		
		$this->tag_recurring_id = $this->tags->create('Tag', 'name', 'Has active recurring contribution', false);
		$this->tag_pt_3 = $this->tags->create('Tag', 'name', 'Recurring contribution: Printed giro', false);
		$this->tag_pt_2 = $this->tags->create('Tag', 'name', 'Recurring contribution: Avtale giro', false);
		$this->tag_pt_1 = $this->tags->create('Tag', 'name', 'Recurring contribution: Donor Managed', false);
		
		$this->count = $this->import($offset, $limit);
	}
	
	public function getCount() {
		return $this->count;
	}
	
	protected function loadAllRecords($offset, $limit) {
		$sql = "SELECT r.*, o.payment_type_id FROM `civicrm_contribution_recur` `r` LEFT JOIN `civicrm_contribution_recur_offline` `o` ON `r`.`id` = `o`.`recur_id` WHERE `r`.`end_date` IS NULL  LIMIT ".$offset .", ". $limit;
		$stmnt = $this->civi_pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt;
	}
	
	protected function import($offset, $limit) {
		$stmnt = $this->loadAllRecords($offset, $limit);
		$i =0;
		while ($row = $stmnt->fetch()) {
			$this->correctRecuringContribution($row);
			$i++;
		}
		return $i;
	}
	
	protected function correctRecuringContribution($row) {
		$doNotImport = false;
		$contactId = false;
		$contactId = $row['contact_id'];
		if (!$contactId) {
			$doNotImport = true;
		}
		
		if ($doNotImport) {
			return;
		}
		
		if ($this->tag_recurring_id !== false) {
			$this->tags->add('EntityTag', 'tag_id', $this->tag_recurring_id, $contactId);
		}
		switch($row['payment_type_id']) {
			case 1:
				if ($this->tag_pt_1 !== false) {
					$this->tags->add('EntityTag', 'tag_id', $this->tag_pt_1, $contactId);
				}
				break;
			case 2:
				if ($this->tag_pt_2 !== false) {
					$this->tags->add('EntityTag', 'tag_id', $this->tag_pt_2, $contactId);
				}
				break;
			case 3:
				if ($this->tag_pt_3 !== false) {
					$this->tags->add('EntityTag', 'tag_id', $this->tag_pt_3, $contactId);
				}
				break;
		}
	
	}
	
}