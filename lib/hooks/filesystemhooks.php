<?php

/**
 * Copyright (c) 2015 Victor Dubiniuk <victor.dubiniuk@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Files_Antivirus\Hooks;

class FilesystemHooks {

	private $appConfig;
	private $rootFolder;

	public function __construct($rootFolder, $appConfig) {
		$this->rootFolder = $rootFolder;
		$this->appConfig = $appConfig;
	}

	/**
	 * Register postWrite hook listener
	 */
	public function register(){
		$callback = function(\OCP\Files\Node $node) {
			//doesn't work
			exit();
		};
		$this->rootFolder->listen('\OC\Files', 'postWrite', $callback);
	}

}
