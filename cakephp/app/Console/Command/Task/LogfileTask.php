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
*                                  Logfile Task
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

class LogfileTask extends AppShell{
	
	public function init(){
		Configure::load('Statusengine');
		$this->logfile = Configure::read('logfile');
		$this->log = null;
		$this->open();
	}
	
	public function stlog($str = ''){
		if(!is_resource($this->log)){
			$this->open();
		}
		fwrite($this->log, $str.PHP_EOL);
	}
	
	public function clog($str = ''){
		$this->stlog('['.getmypid().'] '.$str);
	}
	
	public function open(){
		$this->log = fopen($this->logfile, 'a+');
	}
	
	public function welcome(){
		$this->stlog('');
		$this->stlog('    #####');
		$this->stlog('   #     # #####   ##   ##### #    #  ####  ###### #    #  ####  # #    # ######');
		$this->stlog('   #         #    #  #    #   #    # #      #      ##   # #    # # ##   # #');
		$this->stlog('    #####    #   #    #   #   #    #  ####  #####  # #  # #      # # #  # #####');
		$this->stlog('         #   #   ######   #   #    #      # #      #  # # #  ### # #  # # #');
		$this->stlog('   #     #   #   #    #   #   #    # #    # #      #   ## #    # # #   ## #');
		$this->stlog('    #####    #   #    #   #    ####   ####  ###### #    #  ####  # #    # ######');
		$this->stlog('');
		$this->stlog('                            the missing event broker');
		$this->stlog('');
		$this->stlog('Copyright (c) 2014 - present Daniel Ziegler <daniel@statusengine.org>');
		$this->stlog('Please visit http://www.statusengine.org for more information');
		$this->stlog('Contribute to Statusenigne at: https://github.com/nook24/statusengine');
		$this->stlog('');
		$this->stlog('');
		$this->stlog('');
	}
}
