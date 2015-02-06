<?php

/**
 * Copyright (c) 2015 Victor Dubiniuk <victor.dubiniuk@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Files_Antivirus\Tests;

abstract class Mapperbase extends \PHPUnit_Framework_TestCase {

	protected $db;
	protected $config;

	public function setUp(){
		parent::setUp();
		$this->db = \OC::$server->getDb();
		$this->config = $this->getMockBuilder('\OCA\Files_Antivirus\Appconfig')
				->disableOriginalConstructor()
				->getMock()
		;
		\OC_App::enable('files_antivirus');
	}
}
