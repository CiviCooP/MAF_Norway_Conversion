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
		$sql = "SELECT * FROM `PMF_MAF_INFOPROFIL_txt` `p` LEFT JOIN `PMF_MAF_INFOTYPE_txt` `t` ON `p`.`I_INFOTYPE_ID` = `t`.`I_INFOTYPE_ID` WHERE `p`.`L_NAVN_ID` = '".$l_navn_id."'";
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
				$tag = 'Utviklingshjelp';
				break;	
			case '23':
				$tag = 'Misjon';
				break;
			case '24':
				$tag = 'Nødhjelp';
				break;
		}
		$tag_id = $this->create('Tag', 'name', $tag, false);
		if ($tag_id !== false) {
			$this->add('EntityTag', 'tag_id', $tag_id, $civi_contact_id);
		}
	}
	
	protected function importGroup($civi_contact_id, $l_navn_id, $row) {
		$group = $row['A_BESKRIVELSE'];
		$group_id = $this->create('Group', 'title', $group, true);
		if ($group_id !== false) {
			$this->add('GroupContact', 'group_id', $group_id, $civi_contact_id, $row['D_FRADATO']);
		}
	}
	
	protected function add($entity, $id_field, $id, $contact_id, $in_date = false) {
		$params = array(
			'contact_id' => $contact_id,
			$id_field => $id
		);
		if ($in_date !== false) {
			$params['in_date'] = $in_date;
		}
		return $this->api->$entity->create($params);
	}
	
	protected function create($entity, $name_field, $name, $reserved = false) {
		if ($this->api->$entity->getsingle(array($name_field => $name))) {
			return $this->api->id;
		}
		$params = array(
			$name_field => $name
		);
		if ($reserved && strtolower($entity) == 'group' && $name != 'import') {
			$pid = $this->create('Group', 'title', 'import', true);
			if ($pid === false) {
				return false;
			}
			$params['parents'] = $pid;
		}
		if ($reserved && strtolower($entity) == 'group') {
			$params['is_active'] = '0';
			$params['is_reserved'] = '1';
			$params['is_hidden'] = '1';
		}
		
		if ($this->api->$entity->create($params)) {
			return $this->api->id;
		}
		return false;
	}

}

?>