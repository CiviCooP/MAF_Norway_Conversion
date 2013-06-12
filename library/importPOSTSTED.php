<?php

require_once('importer.php');

class importPOSTSTED extends importer {

	protected function getFilename() {
		return "PMF_MAF.POSTSTED.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		/*switch ($name) {
			case 'L_NAVN_ID':
				$column->is_primary = true;
				$column->type = "INT";
				$column->length = 10;
				break;
		}*/
		return $column;
	}
}