<?php
class CacheTask extends AppShell{
	
	public $uses = ['Servicestatus'];
	
	//Class variables
	public $servicestatusCache = [];
	
	/**
	 * Every time we recive an servucestatus we need to update the last record in DB
	 * Cause of we don't want to lookup the id on every update again, we create us an cache of it
	 *
	 * @since 1.0.0
	 * @author Daniel Ziegler <daniel@statusengine.org>
	 *
	 * @return void
	 */
	public function buildServicestatusCache(){
		$this->servicestatusCache = [];
		foreach($this->Servicestatus->find('all', ['fields' => ['servicestatus_id', 'service_object_id']]) as $ss){
			$this->servicestatusCache[$ss['Servicestatus']['service_object_id']] = $ss['Servicestatus']['servicestatus_id'];
		}
	}
}