<?php

class tempCustomFields {
	
	protected $config;
	
	protected $pdo;
	
	protected $api;
	
	protected $fields;
	
	protected $groups;
	
	public function __construct(PDO $pdo, $api, $config) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->api = $api;
		
		$this->fields = array();
		$this->groups = array();
		
		$this->createFields();
	}
	
	protected static function deleteFieldByGroup($groupname, $extends, $api) {
		$params['name'] = $groupname;
		$params['extends'] = $extends;
		$result = $api->CustomGroup->getsingle($params);
		if ($result) {
			$gid = $api->id;
			unset($params);
			$params['custom_group_id'] = $gid;
			$result = $api->CustomField->get($params);
			if (isset($api->values) && is_array($api_>values)) {
				$api2 = clone $api;
				foreach($api->values  as $field) {
					unset($params);
					$params['id'] = $field['id'];
					$api2->CustomField->delete($params);
				}
			}
		
			unset($params);
			$params['id'] = $gid;
			$result = $api->CustomGroup->delete($params);
		}
	}
	
	public static function deleteFields($api) {
		self::deleteFieldByGroup('maf_norway_import', 'Contact', $api);
		self::deleteFieldByGroup('maf_norway_aksjon_import', 'Activity', $api);
		
		/*$params['name'] = 'maf_norway_import';
		$params['extends'] = 'Contact';
		$result = $api->CustomGroup->getsingle($params);
		if ($result) {
			$gid = $api->id;
			unset($params);
			$params['custom_group_id'] = $gid;
			$result = $api->CustomField->get($params);
			if (isset($api->values) && is_array($api_>values)) {
				$api2 = clone $api;
				foreach($api->values  as $field) {
					unset($params);
					$params['id'] = $field['id'];
					$api2->CustomField->delete($params);
				}
			}
		
			unset($params);
			$params['id'] = $gid;
			$result = $api->CustomGroup->delete($params);
		}
		
		$params['name'] = 'maf_norway_aksjon_import';
		$params['extends'] = 'Activity';
		$result = $api->CustomGroup->getsingle($params);
		if ($result) {
			$gid = $api->id;
			unset($params);
			$params['custom_group_id'] = $gid;
			$result = $api->CustomField->get($params);
			if (isset($api->values) && is_array($api_>values)) {
				$api2 = clone $api;
				foreach($api->values  as $field) {
					unset($params);
					$params['id'] = $field['id'];
					$api2->CustomField->delete($params);
				}
			}
		
			unset($params);
			$params['id'] = $gid;
			$result = $api->CustomGroup->delete($params);
		}*/
	}
	
	protected function createGroup($groupname, $extends, $grouptitle) {
		$params['name'] = $groupname;
		$params['extends'] = $extends;
		$result = new stdClass();
		$gid = false;
		if ($this->api->CustomGroup->getsingle($params)) {
			$result = $this->api->result;
			$this->groups[$groupname] = $result;
			$gid = $result->id;
		}
		if (!isset($result->id) || !$result->id) {
			unset($params);
			$params['name'] = $groupname;
			$params['title'] = $grouptitle;
			$params['extends'] = $extends;
			$params['is_active'] = '1';
			$result = new stdClass();
			if ($this->api->CustomGroup->Create($params)) {
				$result = $this->api->result;
				$this->groups[$groupname] = $result;
				$gid = $result->id;
			}
		}
		
		return $gid;
	}
	
	protected function createFields() {
	
		$gid = $this->createGroup('maf_norway_import', 'Contact', 'MAF Norway Import');		
		$this->createField($gid, 'l_navn_id', 'l_navn_id', 'l_navn_id', '1', 'Text', 'String');
		$this->createField($gid, 'NO_SocialSecurityNo', 'NO_SocialSecurityNo', 'NO_SocialSecurityNo', '1', 'Text', 'String');
		$this->createField($gid, 'Organisasjonsnummer', 'Organisasjonsnummer', 'Organisasjonsnummer', '1', 'Text', 'String');
		$this->createField($gid, 'd_opprettet', 'd_opprettet', 'd_opprettet', '1', 'Select Date', 'Date');
		$this->createField($gid, 'd_stoppet', 'd_stoppet', 'd_stoppet', '1', 'Select Date', 'Date');
		$this->createField($gid, 'a_stoppaarsak', 'a_stoppaarsak', 'a_stoppaarsak', '1', 'Text', 'String');
		$this->createField($gid, 'd_offdato', 'd_offdato', 'd_offdato', '1', 'Select Date', 'Date');
		$this->createField($gid, 'a_offhumnei', 'a_offhumnei', 'a_offhumnei', '1', 'Text', 'String');
		$this->createField($gid, 'a_offtelefonnei', 'a_offtelefonnei', 'a_offtelefonnei', '1', 'Text', 'String');
		$this->createField($gid, 'a_offpostnei', 'a_offpostnei', 'a_offpostnei', '1', 'Text', 'String');
		
		
		$gid = $this->createGroup('maf_norway_aksjon_import', 'Activity', 'MAF Norway Aksjon Import');
		$this->createField($gid, 'aksjon_id', 'aksjon_id', 'aksjon_id', '1', 'Text', 'String');
		$this->createField($gid, 'aktivitet_id', 'aktivitet_id', 'aktivitet_id', '1', 'Text', 'String');
		$this->createField($gid, 'aksjon_kid9', 'aksjon_kid9', 'aksjon_kid9', '1', 'Text', 'String');
		$this->createField($gid, 'aksjon_kid15', 'aksjon_kid15', 'aksjon_kid15', '1', 'Text', 'String');
		$this->createField($gid, 'aksjon_kid15_correction', 'aksjon_kid15_correction', 'aksjon_kid15_correction', '1', 'Text', 'String');
		
		$gid = $this->createGroup('maf_norway_contribution_import', 'Contribution', 'MAF Norway Import');
		$this->createField($gid, 'contribution_aksjon_id', 'contribution_aksjon_id', 'contribution_aksjon_id', '1', 'Text', 'String');
		$this->createField($gid, 'contribution_balanskonto', 'contribution_balanskonto', 'contribution_balanskonto', '1', 'Text', 'String');
		
		$gid = $this->createGroup('maf_norway_contribution_recur_import', 'ContributionRecur', 'MAF Norway Import');
		$this->createField($gid, 'contributionrecur_aksjon_id', 'contributionrecur_aksjon_id', 'contributionrecur_aksjon_id', '1', 'Text', 'String');
	}
	
	public function createField($gid, $fname, $name, $label, $is_active, $html_type, $data_type) {
		$params['name'] = $name;
		$params['custom_group_id'] = $gid;
		$result = new stdClass();
		if ($this->api->CustomField->getsingle($params)) {
			$result = $this->api->result;
			$this->fields[$fname] = $result;
		}
		if (!isset($result->id) || !$result->id) {
			unset($params);
			$params['custom_group_id'] = $gid;
			$params['name'] = $name;
			$params['label'] = $label;
			$params['is_active'] = $is_active;
			$params['html_type'] = $html_type;
			$params['data_type'] = $data_type;
			
			$result = new stdClass();
			if ($this->api->CustomField->Create($params)) {
				$result = $this->api->result;
				$this->fields[$fname] = $result;
			}
		}
	}
	
	public function getCustomField($name) {
		if (isset($this->fields[$name])) {
			return $this->fields[$name]->id;
		}
		return null;
	}
	
	public function getCustomFieldFull($name) {
		return $this->fields[$name];
	}
	
	public function getCustomGroup($name) {
		if (isset($this->groups[$name])) {
			return $this->groups[$name];
		}
		return null;
	}

}