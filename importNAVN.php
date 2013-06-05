<?php

require_once('importer.php');

class importNAVN extends importer {

	protected function getFilename() {
		return "PMF_MAF.NAVN.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_NAVN_ID':
				$column->is_primary = true;
				$column->type = "INT";
				$column->length = 10;
				break;
			case 'A_DUPLIKATSAMLINGVALGTAV':
				$column->type = "TEXT";
				$column->length = false;
				break;
		}
		return $column;
	}
}