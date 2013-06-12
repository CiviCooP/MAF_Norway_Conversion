<?php

require_once('importer.php');

class importKOMMUNE extends importer {

	protected function getFilename() {
		return "PMF_MAF.KOMMUNE.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		/*switch ($name) {
			case 'A_KOMMUNE_ID':
				$column->is_primary = true;
				$column->type = "INT";
				$column->length = 10;
				break;
		}*/
		return $column;
	}
}