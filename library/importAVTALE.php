<?php

require_once('importer.php');

class importAVTALE extends importer {

	protected function getFilename() {
		return "PMF_MAF.AVTALE.txt";
	}
}