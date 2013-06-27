<?php

require_once('importer.php');

class importPOSTERING extends importer {

	protected function getFilename() {
		return "PMF_MAF.POSTERING.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_NAVN_ID':
				$column->is_primary = false;
				$column->type = "INT";
				$column->length = 10;
				break;
			case 'L_AKSION_ID':
				$column->is_primary = false;
				$column->type = "INT";
				$column->length = 10;
				break;
			case 'L_INNBETALING_ID':
				$column->is_primary = false;
				$column->type = "INT";
				$column->length = 10;
				break;
		}
		return $column;
	}
}