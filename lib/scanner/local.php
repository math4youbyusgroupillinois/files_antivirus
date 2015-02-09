<?php
/**
 * Copyright (c) 2014 Victor Dubiniuk <victor.dubiniuk@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */


namespace OCA\Files_Antivirus\Scanner;

use OCA\Files_Antivirus\Status;
use OCA\Files_Antivirus\Item;

class Local extends \OCA\Files_Antivirus\Scanner{
	
	protected $avPath;
	
	public function __construct(){
		
		// get the path to the executable
		$avPath = self::$appConfig->getAvPath();

		// check that the executable is available
		if (!file_exists($avPath)) {
			throw new \RuntimeException('The antivirus executable could not be found at '.$avPath);
		}
		
		$this->avPath = $avPath;
	} 

	/**
	 * Scan a file
	 * @param Item $item - item to scan
	 * @return int
	 * @throws \RuntimeException
	 */
	protected function scan(Item $item) {
		$this->status = new Status();
		
		$avCmdOptions = self::$appConfig->getAvCmdOptions();
		$shellArgs = explode(',', $avCmdOptions);
		$shellArgs = array_map(function($i){
				return escapeshellarg($i);
			},
			$shellArgs
		);
		
		$preparedArgs = '';
		if (count($shellArgs)){
			$preparedArgs = implode(' ', $shellArgs);
		}

		// using 2>&1 to grab the full command-line output.
		$cmd = escapeshellcmd($this->avPath) . " " . $preparedArgs ." - 2>&1";
		$descriptorSpec = array(
			0 => array("pipe","r"), // STDIN
			1 => array("pipe","w")  // STDOUT
		);
		
		$pipes = array();
		$process = proc_open($cmd, $descriptorSpec, $pipes);
		if (!is_resource($process)) {
			throw new \RuntimeException('Error starting process');
		}

		// write to stdin
		$shandler = $pipes[0];
		while (false !== $chunk = $item->fread()) {
			fwrite($shandler, $chunk);
		}
		fclose($shandler);

		$output = stream_get_contents($pipes[1]);

		fclose($pipes[1]);

		$result = proc_close($process);

		$this->status->parseResponse($output, $result);
		
		return $this->status->getNumericStatus();
	}
}
