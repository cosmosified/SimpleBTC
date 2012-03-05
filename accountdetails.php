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

$pageTitle = "- Account Details";
include ("includes/header.php");

/*
 Copyright (C)  41a240b48fb7c10c68ae4820ac54c0f32a214056bfcfe1c2e7ab4d3fb53187a0 Name Year (sha256)

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 Website Reference:http://www.gnu.org/licenses/gpl-2.0.html

 Note From Author: Keep the original donate address in the source files when transferring or redistrubuting this code.
 Please donate at the following address: 1Fc2ScswXAHPUgj3qzmbRmwWJSLL2yv8Q
 */

if(!$cookieValid) {
	header('Location: /');
	exit;
}
//Execute the following based on what $_POST["act"] is set to
$returnError = "";
$goodMessage = "";
if (isset($_POST["act"])) {
	$act = $_POST["act"];

	if (isset($_POST["authPin"])) {
		$inputAuthPin = hash("sha256", $_POST["authPin"].$salt);
	} else {
		$inputAuthPin = NULL;
	}
		

	//Check if authorization pin has been inputted correctly
	if($inputAuthPin == $authPin && $act){
		if($act == "cashOut"){
			$txfee = 0;
			if ($settings->getsetting("sitetxfee") > 0)
				$txfee = $settings->getsetting("sitetxfee");
			//Get user's balance and send it to set address;
			//Does user have any money in their balance
			if($currentBalance > $txfee) {				
				//Send $currentBalance to $paymentAddress
				//Validate that a $paymentAddress has been set & is valid before sending
				$isValidAddress = $bitcoinController->validateaddress($paymentAddress);
				if($isValidAddress) {
					if (!islocked("money")) {
						//Subtract TX fee, site percentage and donation percentage.
						$sitepercent = $settings->getsetting("sitepercent");
						$currentBalance = ($currentBalance*(1-$sitepercent/100)*(1-$donatePercent/100)) - $txfee;
						//Send money//
						try {
							$paid = 0;
							$result = mysql_query("SELECT IFNULL(paid,'0') as paid FROM accountBalance WHERE userId=".$userId);
							if ($resultrow = mysql_fetch_object($result)) $paid = $resultrow->paid + $currentBalance;
							
							lock("money");
							mysql_query("BEGIN");
							//Reduce balance amount to zero
							mysql_query("UPDATE accountBalance SET balance = '0', paid = '$paid' WHERE userId = $userId");
							if ($bitcoinController->sendtoaddress($paymentAddress, $currentBalance)) {																									
							$goodMessage = "You have successfully sent ".$currentBalance." to the following address:".$paymentAddress;
								mail("$userEmail", "Simplecoin Manual Payout Notification", "Hello,\n\nYour requested manual payout of ". $currentBalance." BTC has been sent to your payment address ".$paymentAddress.".", "From: Simplecoin Notifications <server@simplecoin.us>");
							//Set new variables so it appears on the page flawlessly
							$currentBalance = 0;						
								mysql_query("COMMIT");
						}else{
								mysql_query("ROLLBACK");
							$returnError = "Commodity failed to send.";
						}
						} catch (Exception $e) {							
							mysql_query("ROLLBACK");
					}
						unlock("money");
					}
					else
					{
						$returnError = "Automatic payouts currently in progress, try again later.";
					}
				}else{
					$returnError = "That isn't a valid Bitcoin address";
				}
			}else{
				$returnError = "You have no money in your account!";
			}
		}


		if($act == "updateDetails"){
			//Update user's details
			if (!$userBtcLock)
				$newSendAddress = mysql_real_escape_string($_POST["paymentAddress"]);
			else
				$newSendAddress = $paymentAddress;
			$newDonatePercent = mysql_real_escape_string($_POST["donatePercent"]);
			$newPayoutThreshold = mysql_real_escape_string($_POST["payoutThreshold"]);
			if ($newPayoutThreshold > 25)
				$newPayoutThreshold = 25;
			if ($newPayoutThreshold < 1)
				$newPayoutThreshold = 0;
			if ($newDonatePercent < 0)
                $newDonatePercent = 0;
            if ($newDonatePercent > 100)
            	$newDonatePercent = 100;
            if (isset($_POST["cbxLock"]) && $_POST["cbxLock"] == "1") {
            	mysql_query("UPDATE webUsers SET btc_lock = '1' WHERE id = $userId");
            	$userBtcLock = true;
            }
			$updateSuccess1 = mysql_query("UPDATE accountBalance SET sendAddress = '$newSendAddress', threshold = '$newPayoutThreshold' WHERE userId = $userId");
			if (!is_nan($newDonatePercent))
				$updateSuccess2 = mysql_query("UPDATE webUsers SET donate_percent='$newDonatePercent' WHERE id = $userId");
			else
				$returnError = "Donation % must be numeric.";
				
			if($updateSuccess1 && $updateSuccess2){
				$goodMessage = "Account details are now updated.";
				$paymentAddress = $newSendAddress;
				$donatePercent = $newDonatePercent;
				$payoutThreshold = $newPayoutThreshold;
			}
		}

		if($act == "updatePassword"){
			//Update password
			$oldPass = hash("sha256", mysql_real_escape_string($_POST["currentPassword"]));
			$newPass = mysql_real_escape_string($_POST["newPassword"]);
			$newPassConfirm = mysql_real_escape_string($_POST["newPassword2"]);

			//If hash $oldPass is the same as the DB already hashed password continue you with the password change
			if($oldPass == $hashedPass){
				//Check if new password is valid
				if($newPass != "" && strlen($newPass) > 6){
					//Change the password only if $newPass == $newPassConfirm
					if($newPass == $newPassConfirm){
						//Update hashed password
						$newHashedPass = hash("sha256", $newPass.$salt);
						$passchangeSuccess = mysql_query("UPDATE `webUsers` SET `pass` = '".$newHashedPass."' WHERE `id` = '".$userId."'");
						if($passchangeSuccess){
							$goodMessage = "Password successfully changed.";
						}else{
							$returnError = "Database Failure - Unable to change password";
						}
					}else if($newPass != $newPassConfirm){
						$returnError = "The \"New Password\" and \"New Password Repeat\" fields must match";
					}
				}else{
					$returnError = "Your new password is not valid, Must be longer then 6 characters";
				}

			}else if($oldPass != $hashedPass){
				//Typed in password dosent match DB password
				$returnError = "You must type in the correct current password before you can set a new password.";
			}
		}


	} else if ($inputAuthPin != $authPin && $act) {
		$returnError = "Authorization Pin is Invalid!";
	}
	
	if($act == "addWorker"){
		//Add worker
		$prefixUsername = $userInfo->username;
		$inputUser = $prefixUsername.".".mysql_real_escape_string($_POST["username"]);
		$inputPass = mysql_real_escape_string($_POST["pass"]);

		//Check if username already exists
		$usernameExistsQ = mysql_query("SELECT id FROM pool_worker WHERE associatedUserId = $userId AND username = '$inputUser'");
		$usernameExists = mysql_num_rows($usernameExistsQ);

		if($usernameExists == 0){
			$addWorkerQ = mysql_query("INSERT INTO pool_worker (associatedUserId, username, password) VALUES('$userId', '$inputUser', '$inputPass')");
			if($addWorkerQ){
				$goodMessage = "Worker successfully added!";
			}else if(!$addWorkerQ){
				$returnError = "Database Error - Worker was not added :(";
			}
		}else if($usernameExists == 1){
			$returnError = "Try using a different Worker Username";
		}
	}


	if($act == "Update Worker"){

		//Mysql Injection Protection
		$workerId = mysql_real_escape_string($_POST["workerId"]);
		$workernum = mysql_real_escape_string($_POST["workernum"]);
		$password = mysql_real_escape_string($_POST["password"]);

		$prefixUsername = $userInfo->username;
		$inputUser = $prefixUsername.".".mysql_real_escape_string($_POST["workernum"]);
			//update worker
			mysql_query("UPDATE pool_worker SET username = '$inputUser', password = '$password' WHERE id = '$workerId' AND associatedUserId = '$userId'");
		}


		if($act == "Delete Worker"){

			//Mysql Injection Protection
			$workerId = mysql_real_escape_string($_POST["workerId"]);

			//Delete worker OH NOES!
			mysql_query("DELETE FROM pool_worker WHERE id = '$workerId' AND associatedUserId = '$userId'");
		}
}

//Display Error and Good Messages(If Any)
echo "<span class=\"goodMessage\">".$goodMessage."</span><br/>";
echo "<span class=\"returnMessage\">".$returnError."</span>";
?>
<b><u>Account Details</u></b><br/>
<form action="/accountdetails.php" method="post"><input type="hidden" name="act" value="updateDetails">
<table>
	<tr><td>Username: </td><td><?php echo antiXss($userInfo->username);?></td></tr>
	<tr><td>User Id: </td><td><?php echo antiXss($userId); ?></td></tr>
	<tr><td><a href="api.php?api_key=<?php echo antiXss($userApiKey) ?>" style="color: blue" target="_blank">API</a> Key: </td><td><?php echo antiXss($userApiKey); ?></td></tr>
	<tr><td></td></tr>
	<tr><td>Payment Address: </td>
		<td><?php if (!$userBtcLock) { ?><input type="text" name="paymentAddress" value="<?php echo antiXss($paymentAddress); ?>" size="40"><?php } else { echo antiXss($paymentAddress); } ?></td></tr>
	<?php if (!$userBtcLock) { ?><tr><td colspan="2"><input type="checkbox" name="cbxLock" value="1" /> Permanently lock Bitcoin address to this account</td></tr><?php } ?>
	<tr><td>Donation %: </td><td><input type="text" name="donatePercent" value="<?php echo antiXss($donatePercent);?>" size="4"></td></tr>
	<tr><td>Automatic Payout: <br />(1-25 BTC, 0 for manual)</td><td valign="top"><input type="text" name="payoutThreshold" value="<?php echo antiXss($payoutThreshold);?>" size="2" maxlength="2"></td></tr>
	<tr><td>Authorize Pin: </td><td><input type="password" name="authPin" size="4" maxlength="4" autocomplete="off"></td></tr>
</table>
<input type="submit" value="Update Account Settings"></form>
<br />
<br />
<b><u>Cash Out</u></b><br/>
<?php if ($settings->getsetting("sitetxfee") > 0) {?><i>(Please note: a <?php echo $settings->getsetting("sitetxfee")?> btc transaction fee is required by the bitcoin client for processing)</i><br/><?php } ?>
<form action="/accountdetails.php" method="post">
<input type="hidden" name="act" value="cashOut">
<table>
	<tr><td>Account Balance: </td><td><?php echo antiXss($currentBalance); ?></td></tr>
	<tr><td>Payout to: </td><td><?php echo antiXss($paymentAddress); ?></td></tr>
	<tr><td>Authorize Pin: </td><td><input type="password" name="authPin" size="4" maxlength="4" autocomplete="off"></td></tr>
</table>
<input type="submit" value="Cash Out"></form>
<br />
<br />

<b><u>Change Password</u></b><br/>
<form action="/accountdetails.php" method="post"><input type="hidden" name="act" value="updatePassword">
<table>
	<tr><td>Current Password: </td><td><input type="password" name="currentPassword" autocomplete="off"></td></tr>
	<tr><td>New Password: </td><td><input type="password" name="newPassword" autocomplete="off" ></td></tr>
	<tr><td>New Password Repeat: </td><td><input type="password" name="newPassword2" autocomplete="off"></td></tr>
	<tr><td>Authorize Pin: </td><td><input type="password" name="authPin" size="4"	maxlength="4"></td></tr>
</table>
<span style="text-decoration: underline;">(You will be redirected to the login screen upon success)</span> <br />
<input type="submit" value="Update Password Settings"></form>
<br />
<br />

<b><u>Workers</u></b><br/>
<table border="1" cellpadding="1" cellspacing="1">
<tr><td><u>Worker Name </u></td><td><u>Worker Password</u></td><td><u>Active</u></td><td><u>Hashrate (Mhash/s)</u></td><td><u>Update</u></td><td><u>Delete</u></td></tr>
<?php	
//Get list of workers from the associatedUserId
$getWorkers = mysql_query("SELECT id, username, password FROM pool_worker WHERE associatedUserId = $userId");
while($worker = mysql_fetch_array($getWorkers)){
?>
<form action="/accountdetails.php" method="post">
<input type="hidden" name="workerId" value="<?php echo $worker["id"]?>">
<?php
	//Display worker information and the forms to edit or update them
	$splitUsername = explode(".", $worker["username"]);
	$realUsername = $splitUsername[1];
	?>
	<tr>

		<td <?php if ($stats->workerhashrate($worker["username"]) == 0) { ?>style="color: red"<?php } ?>><?php echo antiXss($userInfo->username); ?>.<input type="text" name="workernum" value="<?php echo antiXss($realUsername); ?>" size="10"></td>
	    <td><input type="text" name="password" value="<?php echo antiXss($worker["password"]);?>" size="10"></td>
	    <td><?php if ($stats->workerhashrate($worker["username"]) > 0) echo "Y"; else echo "N"; ?>
	    <td><?php echo $stats->workerhashrate($worker["username"]); ?></td>
		<td><input type="submit" name="act" value="Update Worker"></td>
		<td><input type="submit" name="act" value="Delete Worker"/></td>
	</tr>
</form>
	<?php
}
?>
</table>
<form action="/accountdetails.php" method="post"><input type="hidden"
	name="act" value="addWorker"><!--  AuthPin:<input type="password"
	name="authPin" size="4" maxlength="4"><br /> -->
<?php echo antiXss($userInfo->username);?>.<input type="text" name="username"
	value="user" size="10" maxlength="20"> &middot; <input type="text"
	name="pass" value="pass" size="10" maxlength="20" autocomplete="off"> <input type="submit"
	value="Add worker"></form>

<br />
<br />

<?php include ("includes/footer.php");?>
