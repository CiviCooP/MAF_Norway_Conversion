<?php

require_once('importer.php');

class importINFOTYPE extends importer {

	protected function getFilename() {
		return "PMF_MAF.INFOTYPE.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'I_INFOTYPE_ID':
				$column->is_primary = true;
				$column->type = "INT";
				$column->length = 10;
				break;
		}
		return $column;
	}
}