<?php
/**
* Copyright (C) 2015 Daniel Ziegler <daniel@statusengine.org>
* 
* This file is part of Statusengine.
* 
* Statusengine is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 2 of the License, or
* (at your option) any later version.
* 
* Statusengine is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with Statusengine.  If not, see <http://www.gnu.org/licenses/>.
*/
class ExternalcommandsComponent extends Component{
	public function initialize(Controller $controller, $settings = []){
		$this->Controller = $controller;
	}
	
	public function checkCmd(){
		$commandFile = $this->Controller->Configvariable->getCommandFile();
		$commandFileError = false;
		if($commandFile === false){
			$commandFileError = 'External command file not found in database! Check your app/Config/Statusengine.php => coreconfig settings!';
		}else{
			if(!is_writable($commandFile)){
				$commandFileError = 'External command file '.Configure::read('Interface.command_file').' is not writable';
			}
			if(!file_exists($commandFile)){
				$commandFileError = 'External command file '.Configure::read('Interface.command_file').' does not exists';
			}
		}
		
		$this->Controller->set('commandFileError', $commandFileError);
	}
}