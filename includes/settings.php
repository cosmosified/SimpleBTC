<?php
//    Copyright (C) 2011  Mike Allison <dj.mikeallison@gmail.com>
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <http://www.gnu.org/licenses/>.

// 	  BTC Donations: 163Pv9cUDJTNUbadV4HMRQSSj3ipwLURRc

class Settings {
	
	var $settingsarray = array();	
	
	function Settings() {				
		$this->loadsettings();		
	}
	
	function loadsettings() {			    
		$settingsQ = mysql_query_cache("SELECT setting, value FROM settings"); 
		foreach ($settingsQ as$settingsR ) {		
			$setting = $settingsR->setting;
			$value = $settingsR->value;
			$this->settingsarray[$setting] = $value;
		}	
	}
	
	function getsetting($settingname){
		if (isset($this->settingsarray[$settingname])) return $this->settingsarray[$settingname];
	}
	
	function setsetting($settingname, $value) {
	$query="UPDATE settings SET value='$value' WHERE setting ='$settingname'";
	//echo $query;
      	mysql_query($query);
		$this->settingsarray[$settingname] = $value;
		var_dump($this->settingsarray);
		removeSqlCache("SELECT setting, value FROM settings");
	}
}

?>
