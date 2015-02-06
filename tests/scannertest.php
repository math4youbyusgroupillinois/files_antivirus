<?php
/**
 * Copyright (c) 2014 Victor Dubiniuk <victor.dubiniuk@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

use OC\Files\Storage\Temporary;
use OCA\Files_Antivirus\AppInfo\Application;
use \OCA\Files_Antivirus\Db\RuleMapper;
use \OCA\Files_Antivirus\Item;

class Test_Files_Antivirus_Scanner extends \OCA\Files_Antivirus\Tests\Testbase {
	
	const TEST_CLEAN_FILENAME = 'foo.txt';
	const TEST_INFECTED_FILENAME = 'kitten.inf';

	protected $ruleMapper;

	/**
	 * @var Temporary
	 */
	private $storage;
	
	private $proxies = array();
	
	public function setUp() {
		parent::setUp();
		\OC_App::enable('files_antivirus');
		$this->proxies = \OC_FileProxy::getProxies();
		\OC_FileProxy::clearProxies();

		\OC_User::clearBackends();
		\OC_User::useBackend(new \OC_User_Dummy());
		\OC\Files\Filesystem::clearMounts();

		//login
		\OC_User::createUser('test', 'test');
		\OC::$server->getSession()->set('user_id', 'test');
		
		$this->storage = new \OC\Files\Storage\Temporary(array());
		\OC\Files\Filesystem::init('test', '');
		\OC\Files\Filesystem::clearMounts();
		\OC\Files\Filesystem::mount($this->storage, array(), '/');
		\OC\Files\Filesystem::file_put_contents(self::TEST_CLEAN_FILENAME, self::TEST_CLEAN_FILENAME);
		\OC\Files\Filesystem::file_put_contents(self::TEST_INFECTED_FILENAME, self::TEST_INFECTED_FILENAME);
		
		$this->config->method('getAvMode')->willReturn('executable');
		$this->config->method('getAvPath')->willReturn('av_path', __DIR__ . '/avir.sh');
		$this->config->method('getAvChunkSize')->willReturn('1024');

		$this->ruleMapper = new RuleMapper($this->db);
		$this->ruleMapper->deleteAll();
		$this->ruleMapper->populate();
	}
	
	public function tearDown() {
		foreach ($this->proxies as $proxy){
			\OC_FileProxy::register($proxy);
		}
	}
	
	public function testBackgroundScan(){
		$application = new Application();
		$container = $application->getContainer();
		$container->query('BackgroundScanner')->run();
	}
	
	public function testCleanFile() {
		$fileView = new \OC\Files\View('');
		
		$cleanItem = new Item($fileView, self::TEST_CLEAN_FILENAME);
		$cleanStatus = \OCA\Files_Antivirus\Scanner::scanFile($cleanItem);
		$this->assertInstanceOf('\OCA\Files_Antivirus\Status', $cleanStatus);
		$this->assertEquals(\OCA\Files_Antivirus\Status::SCANRESULT_CLEAN, $cleanStatus->getNumericStatus());
		
	}
	
	public function testNotExisting() {
		$this->setExpectedException('RuntimeException');
		
		$fileView = new \OC\Files\View('');
		$nonExistingItem = new Item($fileView, 'non-existing.file');
		$unknownStatus = \OCA\Files_Antivirus\Scanner::scanFile($nonExistingItem);
		$this->assertInstanceOf('\OCA\Files_Antivirus\Status', $unknownStatus);
		$this->assertEquals(\OCA\Files_Antivirus\Status::SCANRESULT_UNCHECKED, $unknownStatus->getNumericStatus());
	}
	
	public function testInfected() {
		$fileView = new \OC\Files\View('');
		$infectedItem = new Item($fileView, self::TEST_INFECTED_FILENAME);
		$infectedStatus = \OCA\Files_Antivirus\Scanner::scanFile($infectedItem);
		$this->assertInstanceOf('\OCA\Files_Antivirus\Status', $infectedStatus);
		$this->assertEquals(\OCA\Files_Antivirus\Status::SCANRESULT_INFECTED, $infectedStatus->getNumericStatus());
	}
}
