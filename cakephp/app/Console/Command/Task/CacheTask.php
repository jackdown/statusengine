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
*                             Thread safe Cache Task
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

class CacheTask extends AppShell{
	
	public $uses = ['Servicestatus'];
	
	//Class variables
	public $servicestatusCache = [];
	public $cacheWorker = null;
	public $maxJobIdleCounter = 500;
	
	public function gearmanConnect(){
		Configure::load('Statusengine');
		$this->cacheWorker = new GearmanWorker();
		
		/* Avoid that gearman will stuck at GearmanWorker::work() if no jobs are present
		 * witch is bad because if GearmanWorker::work() stuck, PHP can not execute the signal handler
		 */
		$this->cacheWorker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
		$this->cacheWorker->addServer(Configure::read('server'), Configure::read('port'));
		$this->cacheWorker->addFunction('statusngin_cachecom', [$this, 'queryHandler']);
		
		declare(ticks = 1);
		pcntl_signal(SIGTERM, [$this, 'signalHandler']);
		pcntl_signal(SIGUSR1, [$this, 'signalHandler']);
		pcntl_signal(SIGUSR2, [$this, 'signalHandler']);
	}
	
	public function signalHandler($signo){
		switch($signo){
			case SIGTERM:
				//$this->Logfile->clog('CacheTask: Will kill myself :-(');
				exit(0);
				break;
				
			default:
				break;
		}
		
		pcntl_signal(SIGTERM, [$this, 'signalHandler']);
		pcntl_signal(SIGUSR1, [$this, 'signalHandler']);
		pcntl_signal(SIGUSR2, [$this, 'signalHandler']);
	}
	
	public function work(){
		$jobIdleCounter = 0;
		while(true){
			pcntl_signal_dispatch();
			if($this->cacheWorker->work() === false){
				//Worker returend false, looks like the queue is empty
				if($jobIdleCounter < $this->maxJobIdleCounter){
					$jobIdleCounter++;
				}
			}else{
				$jobIdleCounter = 0;
			}
			if($jobIdleCounter === $this->maxJobIdleCounter){
				//The worker will sleep because therer are no jobs to do
				//This will save CPU time!
				usleep(50000);
			}
		}
	}
	
	/**
	 * Callback function of GearmanWorker to handle cache requests
	 *
	 * @param $job		Job object of GearmanWorker
	 * @since 1.2.3
	 * @author Daniel Ziegler <daniel@statusengine.org>
	 *
	 * @return void
	 */
	public function queryHandler($job){
		$payload = json_decode($job->workload());
		
		switch($payload->task){
			case 'servicestatusIdFromCache':
				return $this->servicestatusIdFromCache($payload->serviceObjectId);
				break;
				
			case 'addToServicestatusCache':
				$this->addToServicestatusCache($payload->serviceObjectId, $payload->servicestatus_id);
				return true;
				break;
				
			case 'buildServicestatusCache':
				$this->buildServicestatusCache();
				break;
		}
	}
	
	/**
	 * Every time we recive an servucestatus we need to update the last record in DB
	 * Cause of we don't want to lookup the id on every update again, we create us an cache of it
	 *
	 * @since 1.2.3
	 * @author Daniel Ziegler <daniel@statusengine.org>
	 *
	 * @return void
	 */
	public function buildServicestatusCache(){
		//Avoid MySQL is missing error due to forking
		$this->Servicestatus->getDatasource()->reconnect();
		
		$this->servicestatusCache = [];
		foreach($this->Servicestatus->find('all', ['fields' => ['servicestatus_id', 'service_object_id']]) as $ss){
			$this->servicestatusCache[$ss['Servicestatus']['service_object_id']] = $ss['Servicestatus']['servicestatus_id'];
		}
	}
	
	public function addToServicestatusCache($serviceObjectId, $servicestatus_id){
		$this->servicestatusCache[$serviceObjectId] = $servicestatus_id;
	}
	
	public function servicestatusIdFromCache($serviceObjectId){
		if(isset($this->servicestatusCache[$serviceObjectId])){
			return $this->servicestatusCache[$serviceObjectId];
		}
		
		return null;
	}
	
}
