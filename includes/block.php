<?php
/*
Copyright (C) 2013 Jesse B. Crawford

This file is part of SimpleBTC.

    SimpleBTC is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    SimpleBTC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Foobar.  If not, see <http://www.gnu.org/licenses/>.

SimpleBTC (overhaul) Developer: 16LJ4z5BzZpDTzXBL2n34o8Me6WAM2RhLd
SimpleCoin (unmaintained original) Developer: 1Fc2ScswXAHPUgj3qzmbRmwWJSLL2yv8Q
*/


class Block {
	
	function getLatestDbBlockNumber() {		
		$result = mysql_query("SELECT blockNumber FROM networkBlocks ORDER BY blockNumber DESC LIMIT 1");
		if ($result )
		if ($row = mysql_fetch_row($result)) { 
			if (count($row) > 0) 
				return $row[0];
		}
		return 0;	
	}	
	
	function InsertNetworkBlocks($lastBlockNumber,$lastwinningid) {		
		//Check to see if last block number exists in the db.		
		$inDatabaseQ = mysql_query("SELECT id FROM networkBlocks WHERE blockNumber = $lastBlockNumber LIMIT 0,1");
		if ( $inDatabaseQ )
		$inDatabase = mysql_num_rows($inDatabaseQ);
		if ( isset( $inDatabase ) )
		if(!$inDatabase) {
			//If not, insert it.
			$currentTime = time();
			mysql_query("INSERT INTO networkBlocks (blockNumber, timestamp) VALUES ($lastBlockNumber, $currentTime)");
			
			//Save winning share (if there is one)
			$winningShareQ = mysql_query("SELECT id, username FROM shares where upstream_result = 'Y' AND id > $lastwinningid");
			if ( $winningShareQ )
			while ($winningShareR = mysql_fetch_object($winningShareQ)) {		
				mysql_query("INSERT INTO winning_shares (blockNumber, username, share_id) VALUES ($lastBlockNumber,'$winningShareR->username',$winningShareR->id)");
				removeCache("last_winning_share_id");
			}	
		}
	}

	function UpdateConfirms($bitcoinController) {	
		$winningAccountQ = mysql_query("SELECT id, txid FROM winning_shares WHERE txid <> '' AND confirms < 120");
		if ( $winningAccountQ )
		while ($winningAccountR = mysql_fetch_object($winningAccountQ)) {
			$txInfo = $bitcoinController->query("gettransaction", $winningAccountR->txid);
			if (count($txInfo["confirmations"]) > 0) {
				mysql_query("UPDATE winning_shares SET confirms = ".$txInfo["confirmations"]." WHERE id = $winningAccountR->id");
			}
		}
	}
	
	function FindNewGenerations($bitcoinController) {
		//Get list of last 200 transactions
		$transactions = $bitcoinController->query("listtransactions", "*", "200");
		
		//Go through all the transactions check if there is 50BTC inside
		$numAccounts = count($transactions);
		
		for($i = 0; $i < $numAccounts; $i++) {
			//Check for 50BTC inside only if they are in the generate category
			if($transactions[$i]["category"] == "generate" || $transactions[$i]["category"] == "immature") {		
				//At this point we may or may not have found a block,
				//Check to see if this account addres is already added to `networkBlocks`
				$accountExistsQ = mysql_query("SELECT id FROM winning_shares WHERE txid = '".$transactions[$i]["txid"]."' ORDER BY blockNumber DESC LIMIT 0,1");				
				$accountExists = mysql_num_rows($accountExistsQ);		
			    //Insert txid into latest network block
				if (!$accountExists) {									
					//Get last winning block			
					$lastSuccessfullBlockQ = mysql_query("SELECT n.id, w.id as winningid FROM networkBlocks n, winning_shares w where n.blockNumber = w.blockNumber ORDER BY w.id DESC LIMIT 1");
					$lastSuccessfullBlockR = mysql_fetch_object($lastSuccessfullBlockQ);
					$lastEmptyBlock = $lastSuccessfullBlockR->id;		
					$winningid = $lastSuccessfullBlockR->winningid;								
					mysql_query("UPDATE winning_shares SET amount = '".$transactions[$i]["amount"]."', txid = '".$transactions[$i]["txid"]."' WHERE id = $winningid")or die(mysql_error());
				}			
			}
		}
	}
	
	function CheckUnrewardedBlocks() {		
		$result = mysql_query("SELECT id FROM winning_shares WHERE rewarded = 'N' AND confirms > 119 LIMIT 0,1") or die(mysql_error());
		if ($row = mysql_fetch_object($result))
			return true;
		return false;		
	}
	
	function CheckUnscoredBlocks() {		
		$result = mysql_query("SELECT id FROM winning_shares WHERE scored = 'N' LIMIT 0,1") or die(mysql_error());
		if ($row = mysql_fetch_object($result))
			return true;
		return false;		
	}
	
	function NeedsArchiving($siterewardtype, $difficulty) {
		if ($siterewardtype == 0) {
			$sharesDesired = $difficulty/2;
			$result = mysql_query("SELECT share_id, rewarded FROM winning_shares ORDER BY id DESC");
			while ($row = mysql_fetch_object($result)) {
				if ($row->rewarded == 'N') {
					$result2 = mysql_query("SELECT count(id) FROM shares WHERE id < $row->share_id and our_result='Y'");
					if ($row2 = mysql_fetch_row($result2)) {
						if ($row2[0] < $sharesDesired)
							return false;
					}
				} else {
					$result2 = mysql_query("SELECT count(id) FROM shares WHERE id > $row->share_id and our_result='Y'");
					if ($row2 = mysql_fetch_row($result2)) {
						if ($row2[0] > $sharesDesired)
							return true;
					}
				}				
			}						
		} else {
			$result = mysql_query("SELECT count(s.id) FROM shares s, (SELECT max(share_id) as share_id FROM winning_shares WHERE rewarded='Y') w WHERE s.id < w.share_id");
			if ( $result )
			{
			 $row = mysql_fetch_row($result);
			 if ($row[0] > 0) 
				return true;
			}
			else return false;
		}
		return false;
	}
	
	function Archive($siterewardtype, $difficulty) {
		//echo "Archival requested\n";
		$maxShareId = 0;
		$blockNumber = 0;
		//Get count since last winning share
		if ($siterewardtype == 0) {
			//Last N Shares
			$sharesDesired = round($difficulty/2);
			//echo "Desired Shares: $sharesDesired\n";
			$result = mysql_query("SELECT share_id, blockNumber FROM winning_shares WHERE rewarded = 'Y' ORDER BY id DESC");
			while ($row = mysql_fetch_object($result)) {
				$result2 = mysql_query("SELECT count(id) FROM shares WHERE id > $row->share_id and our_result='Y'");
				if ($row2 = mysql_fetch_row($result2)) {					
					if ($row2[0] > $sharesDesired) {
						$maxShareId = $row->share_id;
						$blockNumber = $row->blockNumber;
						break;
					}
				}
			}			
		} else {
			$result = mysql_query("SELECT max(share_id), max(blockNumber) FROM winning_shares WHERE rewarded='Y'");
			if ($row = mysql_fetch_row($result)) {	
				$maxShareId = $row[0];		
				$blockNumber = $row[1];
			}					
		}
		if ($maxShareId > 0 && $blockNumber > 0) {
			//echo "Archiving\n";
			//echo "Share Id: $maxShareId\n";
			//echo "Block Number: $blockNumber\n";
			//get counted shares by user id and move to shares_counted
			$sql = "SELECT p.associatedUserId, sum(s.valid) as valid, IFNULL(sum(si.invalid),0) as invalid FROM ". 
				"(SELECT username, count(id) as valid  FROM shares WHERE id <= $maxShareId AND our_result='Y' GROUP BY username) s LEFT JOIN ".
				"(SELECT username, count(id) as invalid FROM shares WHERE id <= $maxShareId AND our_result='N' GROUP BY username) si ON s.username=si.username ". 
				"INNER JOIN pool_worker p ON p.username = s.username ".
				"GROUP BY associatedUserId";
			$sharesQ = mysql_query($sql);
			$i = 0;	
			$shareInputSql = "";
			while ($sharesR = mysql_fetch_object($sharesQ)) {	
				if ($i == 0) {
					$shareInputSql = "INSERT INTO shares_counted (blockNumber, userId, count, invalid) VALUES ";
				}
				if ($i > 0) {
					$shareInputSql .= ",";
				}				
				$i++;
				$shareInputSql .= "($blockNumber,$sharesR->associatedUserId,$sharesR->valid,$sharesR->invalid)";
				if ($i > 20)
				{		
					echo "$shareInputSql\n";
					mysql_query($shareInputSql);
					$shareInputSql = "";
					$i = 0;
				}		
			}
			if (strlen($shareInputSql) > 0) {
				echo "$shareInputSql\n";
				mysql_query($shareInputSql);
			}
			
			//Remove counted shares from shares_history
			//echo "DELETE FROM shares WHERE id <= $maxShareId\n";
			mysql_query("DELETE FROM shares WHERE id <= $maxShareId");	
		}
	}
}
?>
