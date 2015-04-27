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
	
	public $uses = ['Servicestatus', 'Objects'];
	
	//Class variables
	public $servicestatusCache = [];
	public $cacheWorker = null;
	public $maxJobIdleCounter = 500;
	public $createParentHosts = [];
	public $createParentServices = [];
	public $objectCache = [];
	
	
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
				return true; //unused return value just for visualisation that we break back to the main process
				break;
				
			case 'clearObjectsCache':
				$this->clearObjectsCache();
				return true; //unused return value just for visualisation that we break back to the main process
				break;
				
			case 'buildObjectsCache':
				$this->buildObjectsCache();
				return true; //unused return value just for visualisation that we break back to the main process
				break;
				
			case 'objectIdFromCache':
				return $this->objectIdFromCache($payload->objecttype_id, $payload->name1, $payload->name2, $payload->default);
				break;
				
			case 'addObjectToCache':
				return $this->addObjectToCache($payload->objecttype_id, $payload->id, $payload->name1, $payload->name2);
				break;
				
			case 'addParentHostsToCache':
				$this->createParentHosts[$payload->host_id][] = $payload->parentHost;
				return true; //unused return value just for visualisation that we break back to the main process
				break;
				
			case 'getParentHostsCache':
				$return = serialize($this->createParentHosts);
				$this->createParentHosts = [];
				return $return;
				break;
				
			case 'addParentServicesToCache':
				$this->createParentServices[$payload->id_service][] = [
					'host_name' => $payload->host_name,
					'description' => $payload->description
				];
				return true; //unused return value just for visualisation that we break back to the main process
				break;
				
			case 'getParentServicesCache':
				$return = serialize($this->createParentServices);
				$this->createParentServices = [];
				return $return;
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
	
	
	/**
	 * This function fills up the cache array with data out of the DB
	 *
	 * @since 1.0.0
	 * @author Daniel Ziegler <daniel@statusengine.org>
	 *
	 * @return void
	 */
	public function buildObjectsCache(){
		$objects = $this->Objects->find('all', [
			'recursive' => -1 //drops associated data, so we dont get an memory limit error, while processing big data ;)
		]);
		foreach($objects as $object){
			/*if($object['Objects']['objecttype_id'] == OBJECT_SERVICE){
				debug($object);
			}*/
			$this->objectCache[$object['Objects']['objecttype_id']][$object['Objects']['name1'].$object['Objects']['name2']] = [
				'name1' => $object['Objects']['name1'],
				'name2' => $object['Objects']['name2'],
				'object_id' => $object['Objects']['object_id'],
			];
		}
	}
	
	/**
	 * If an object is inside of the cache, we return the object_id
	 * The object is sorted by the objecttype_id, i didn't check php's source code
	 * but i guess a numeric array is the fastes way in php to acces an array
	 *
	 * @since 1.0.0
	 * @author Daniel Ziegler <daniel@statusengine.org>
	 *
	 * @param  int    objecttyoe_id The objecttype_id of the current object we want to lookup
	 * @param  string name1 The first name of the object
	 * @param  string name2 The second name of the object, or empty if the object has no name2 (default: null)
	 * @param  mixed  default If we dont find an entry in our cache we retrun the default value (default: null)
	 * @return int    object_id
	 */
	public function objectIdFromCache($objecttype_id, $name1, $name2 = null, $default = null){
		if(isset($this->objectCache[$objecttype_id][$name1.$name2]['object_id'])){
			return $this->objectCache[$objecttype_id][$name1.$name2]['object_id'];
		}
		
		return $default;
	}
	
	/**
	 * This function adds an new created object to the object cache, or replace it
	 *
	 * @since 1.0.0
	 * @author Daniel Ziegler <daniel@statusengine.org>
	 *
	 * @param  int    objecttype_id The objecttype_id of the object you want to add
	 * @param  string name1 of the object
	 * @param  string name2 of the object (default: null)
	 * @return void
	 */
	public function addObjectToCache($objecttype_id, $id, $name1, $name2 = null){
		if(!isset($this->objectCache[$objecttype_id][$name1.$name2])){
			$this->objectCache[$objecttype_id][$name1.$name2] = [
				'name1' => $name1,
				'name2' => $name2,
				'object_id' => $id,
			];
			return true;
		}
		return false;
	}
	
	/**
	 * Every time we recive an object we need the object_id to run CRUD (create, read, update, delete)
	 * So we dont want to lookup the object id every time again, so we store them in an cache array
	 * The sorting is done by the objecttype_id
	 *
	 * @since 1.0.0
	 * @author Daniel Ziegler <daniel@statusengine.org>
	 *
	 * @return void
	 */
	public function clearObjectsCache(){
		$this->objectCache = [
			12 => [],
			11 => [],
			9 =>  [],
			8 =>  [],
			7 =>  [],
			6 =>  [],
			5 =>  [],
			4 =>  [],
			3 =>  [],
			2 =>  [],
			1 =>  []
		];
	}
	
}
