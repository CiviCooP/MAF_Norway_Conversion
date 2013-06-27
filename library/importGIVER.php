<?php

require_once('importer.php');

class importGIVER extends importer {

	protected function getFilename() {
		return "PMF_MAF.GIVER.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_NAVN_ID':
				$column->is_primary = false;
				$column->type = "INT";
				$column->length = 10;
				break;
		}
		return $column;
	}
}