<?php

/**
* ownCloud - files_antivirus
*
* @author Manuel Deglado
* @copyright 2012 Manuel Deglado manuel.delgado@ucr.ac.cr
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

namespace OCA\Files_Antivirus;

use OCA\Files_Antivirus\Item;

abstract class Scanner {
	// null if not initialized
	// false if an error occurred
	// Scanner subclass if initialized
	protected static $instance = null;
	
	// Last scan status
	protected $status;
	
	protected static $appConfig;

	/**
	 * @param string $path
	 */
	public static function av_scan($path) {
		$path = $path[\OC\Files\Filesystem::signal_param_path];
		if (empty($path)) {
			return;
		}
				
		if (isset($_POST['dirToken'])){
			//Public upload case
			$filesView = \OC\Files\Filesystem::getView();
		} else {
			$filesView = \OCP\Files::getStorage("files");
		}
			
		$item = new Item($filesView, $path);
		if (!$item->isValid()){
			return;
		}
			
		$fileStatus = self::scanFile($item);
		$fileStatus->dispatch($item);
	}

	/**
	 * @param Item $item
	 * @return Status
	 */
	public static function scanFile($item) {
		$application = new \OCA\Files_Antivirus\AppInfo\Application();
		self::$appConfig = $application->getContainer()->query('Appconfig');
		$instance = self::getInstance();

		if ($instance instanceof Scanner){
			try {
				$instance->scan($item);
			} catch (\Exception $e){
				\OCP\Util::writeLog('files_antivirus', $e->getMessage(), \OCP\Util::ERROR);
			}
		}
		
		return self::getStatus();
	}
	
	
	protected static function getStatus(){
		$instance = self::getInstance();
		if ($instance->status instanceof Status){
			return $instance->status;
		}
		return new Status();
	}

	
	private static function getInstance(){
		if (is_null(self::$instance)){
			try {
				$avMode = self::$appConfig->getAvMode();
				switch($avMode) {
					case 'daemon':
					case 'socket':
						self::$instance = new \OCA\Files_Antivirus\Scanner\External();
						break;
					case 'executable':
						self::$instance = new \OCA\Files_Antivirus\Scanner\Local();
						break;
					default:
						self::$instance = false;
						\OCP\Util::writeLog('files_antivirus', 'Unknown mode: ' . $avMode, \OCP\Util::WARN);
						break;
				}
			} catch (\Exception $e){
				self::$instance = false;
			}
		}
		
		return self::$instance;
	}

	/**
	 * @param Item $item
	 * @return mixed
	 */
	abstract protected function scan(Item $item);
}
