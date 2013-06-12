<?php

class importTagsGroups {

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
	
	public function import($civi_contact_id, $l_navn_id) {
		$sql = "SELECT * FROM `pmf_maf_infoprofil_txt` `p` LEFT JOIN `pmf_maf_infotype_txt` `t` ON `p`.`I_INFOTYPE_ID` = `t`.`I_INFOTYPE_ID` WHERE `p`.`L_NAVN_ID` = '".$l_navn_id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		while ($row = $stmnt->fetch()) {
			switch ($row['I_INFOTYPE_ID']) {
				case '21':
				case '22':
				case '23':
				case '24':
					$this->importTag($civi_contact_id, $l_navn_id, $row);
					break;
				default:
					$this->importGroup($civi_contact_id, $l_navn_id, $row);
					break;
			}
		}
	}
	
	protected function importTag($civi_contact_id, $l_navn_id, $row) {
		$tag = $row['A_BESKRIVELSE'];
		switch ($row['I_INFOTYPE_ID']) {
			case '21':
				$tag = 'Ambulanse';
				break;
			case '22':
				$tag = 'Utvikling';
				break;	
			case '23':
				$tag = 'Misjon';
				break;
			case '24':
				$tag = 'Nødhjelp';
				break;
		}
		$tag_id = $this->create('Tag', 'name', $tag);
		if ($tag_id !== false) {
			$this->add('EntityTag', 'tag_id', $tag_id, $civi_contact_id);
		}
	}
	
	protected function importGroup($civi_contact_id, $l_navn_id, $row) {
		$group = $row['A_BESKRIVELSE'];
		$group_id = $this->create('Group', 'title', $group);
		if ($group_id !== false) {
			$this->add('GroupContact', 'group_id', $group_id, $civi_contact_id);
		}
	}
	
	protected function add($entity, $id_field, $id, $contact_id) {
		$params = array(
			'contact_id' => $contact_id,
			$id_field => $id
		);
		return $this->api->$entity->create($params);
	}
	
	protected function create($entity, $name_field, $name) {
		if ($this->api->$entity->getsingle(array($name_field => $name))) {
			return $this->api->id;
		}
		if ($this->api->$entity->create(array($name_field => $name))) {
			return $this->api->id;
		}
		return false;
	}

}

?>