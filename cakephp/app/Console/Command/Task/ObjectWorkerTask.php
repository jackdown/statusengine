<?php
/**********************************************************************************
*
*    #####
*   #     # #####   ##   ##### #    #  ####  ###### #    #  ####  # #    # ######
*   #         #    #  #    #   #    # #      #      ##   # #    # # ##   # #
*    #####    #   #    #   #   #    #  ####  #####  # #  # #      # # #  # #####
*         #   #   ######   #   #    #      # #      #  # # #  ### # #  # # #
*   #     #   #   #    #   #   #    # #    # #      #   ## #    # # #   ## #
*    #####    #   #    #   #    ####   ####  ###### #    #  ####  # #    # ######
*
*                            the missing event broker
*                               ObjectWorker Task
*
* --------------------------------------------------------------------------------
*
* Copyright (c) 2014 - present Daniel Ziegler <daniel@statusengine.org>
*
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation in version 2
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*
* --------------------------------------------------------------------------------
*/

class ObjectWorkerTask extends AppShell{
	
	public $lastProcessedObjectType = 12; // 12 == OBJECT_COMMAND
	
	public $tmpWorker = null;
	
	public function gearmanConnect($ipaddress, $port = 4730, $StatusengineShellThis){
		$this->tmpWorker = new GearmanWorker();
		$this->tmpWorker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
		$this->tmpWorker->addServer($ipaddress, $port);
		$this->tmpWorker->addFunction('statusngin_objects', [$StatusengineShellThis, 'dumpObjects']);
		return $this->tmpWorker;
	}
	
	public function work(){
		$maxJobIdleCounter = 500;
		$jobIdleCounter = 0;
		if($this->tmpWorker->work() === false){
			//Worker returend false, looks like the queue is empty
			if($jobIdleCounter < $maxJobIdleCounter){
				$jobIdleCounter++;
			}
		}else{
			$jobIdleCounter = 0;
		}

		if($jobIdleCounter === $maxJobIdleCounter){
			//Nothing to do anymore, so i kill myself
			exit(0);
		}
	}
	
	public function sleepIfLastProjectObjectWasAnotherObjectType($currentObjectType){
		if($this->lastProcessedObjectType != $currentObjectType){
			usleep(150000);
			$this->lastProcessedObjectType = $currentObjectType;
		}
	}
}
