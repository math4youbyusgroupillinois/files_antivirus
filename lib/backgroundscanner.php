<?php
/**
 * Copyright (c) 2012 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Files_Antivirus;

use OCA\Files_Antivirus\AppInfo\Application;
use \OCA\Files_Antivirus\Scanner;
use OCA\Files_Antivirus\Item;

class BackgroundScanner {
	
	private $rootFolder;
	
	public function __construct($rootFolder){
		$this->rootFolder = $rootFolder;
	}
	
	/**
	 * Background scanner main job
	 * @return null
	 */
	public function run(){
		// locate files that are not checked yet
		$dirMimetype = $this->getDirectoryMimetype();
		$sql = 'SELECT `*PREFIX*filecache`.`fileid`, `*PREFIX*storages`.*'
			.' FROM `*PREFIX*filecache`'
			.' LEFT JOIN `*PREFIX*files_antivirus` ON `*PREFIX*files_antivirus`.`fileid` = `*PREFIX*filecache`.`fileid`'
			.' JOIN `*PREFIX*storages` ON `*PREFIX*storages`.`numeric_id` = `*PREFIX*filecache`.`storage`'
			.' WHERE `mimetype` != ?'
			.' AND (`*PREFIX*storages`.`id` LIKE ? OR `*PREFIX*storages`.`id` LIKE ?)'
			.' AND (`*PREFIX*files_antivirus`.`fileid` IS NULL OR `mtime` > `check_time`)'
			.' AND `path` LIKE ?';
		$stmt = \OCP\DB::prepare($sql, 5);
		try {
			$result = $stmt->execute(array($dirMimetype, 'local::%', 'home::%', 'files/%'));
			if (\OCP\DB::isError($result)) {
				\OCP\Util::writeLog('files_antivirus', __METHOD__. 'DB error: ' . \OC_DB::getErrorMessage($result), \OCP\Util::ERROR);
				return;
			}
		} catch(\Exception $e) {
			\OCP\Util::writeLog('files_antivirus', __METHOD__.', exception: '.$e->getMessage(), \OCP\Util::ERROR);
			return;
		}

		\OC_Util::tearDownFS();
		\OC_Util::setupFS();
		$view = new \OC\Files\View('/');
		while ($row = $result->fetchRow()) {
			$path = $view->getPath($row['fileid']);
			var_dump($row);
			var_dump($path);
			if (!is_null($path)) {
				$fileStatus = Scanner::scanFile(
						new Item($view, $path, $row['fileid'])
				);
				$fileStatus->dispatch($item, true);
			}
		}
	}
	
	/**
	 * Get a mimetypeId for httpd/unix-directory
	 * @return int
	 */
	protected function getDirectoryMimetype(){
		$query = \OCP\DB::prepare('SELECT `id` FROM `*PREFIX*mimetypes` WHERE `mimetype` = ?');
		$result = $query->execute(array('httpd/unix-directory'));
		$row = $result->fetchRow();
		$dirMimetype = $row ? $row['id'] : 0;
		return $dirMimetype;
	}
	
	/**
	 * @deprecated 
	 */
	public static function check() {
		if (!\OCP\App::isEnabled('files_antivirus')){
			return;
		}
		
		$application = new Application();
		$container = $application->getContainer();
		$container->query('BackgroundScanner')->run();
	}
}
