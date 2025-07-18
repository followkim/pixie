<?php 
	
	/*
	 * viewVitals.php
	 * 
	 * This is the main page where users can view vital signs for a single animal.
	 * Someone might come to this page for a bunch of reasons:
	 * 1. Simple GET-- the user navigated here from the viewAnimal page, and 
	 * 		the wants to view all the vital signs.  In this case, only animalID is set in the URL.
	 * 2. Edit GET - the user wants to edit a particular value.  In this case,
	 * 		the three primary keys are passed in via GET-- vitalDateTime, animalID and vitalID.
	 * 		Also, "edit" is passed in at the action.
	 * 2.5. Edit POST - the user wants to edit an exsisting row.  Ther user comes here after clicking
	 * 		the submit button after navigating to the page via path #2.
	 * 3. Delete GET - - the user wants to delete a particular row.  In this case,
	 * 		the three primary keys are passed in via GET-- vitalDateTime, animalID and vitalID.
	 * 		Also, "delete" is passed in at the action.
	 * 4. Add POST - the user wants to add a new row.  This is the default action of the form.
	 */
	 
	// Pull in the main includes file
	include 'includes/utils.php';
	include 'includes/html_macros.php';

	date_default_timezone_set("America/Los_Angeles");
	// Get the current user, if not logged in redirect to the login page.

        [$userName,$isAdmin] = getLoggedinUser();
	if ($userName == "") header("location:login.php");

	// Init the error string
	$errString = "";
	
	// If there is no animal ID, then redirect to the findAnimal page.
	// NOTE: This should never happen.
	if (isset($_GET['animalID'])) {
		$animalID =  intval($_GET['animalID']);
	} else header('Location: ' . "findAnimal.php", true, 302);
	
	// Pull possible GET variables
	$vitalDateTime = isset($_GET['vitalDateTime'])?date('Y-m-d H:i:s', strtotime($_GET['vitalDateTime'])):date('Y-m-d H:i');
	$vitalSignTypeID = isset($_GET['vitalSignTypeID'])?intval($_GET['vitalSignTypeID']):"";
	$action = isset($_GET['action'])?validateAction($_GET['action']):"";
	$retPage = isset($_GET['retPage'])?validateRetpage($_GET['retPage']):"viewVitals";

	$mysqli = DBConnect();
	
	
	// Get information about the current animal
	$sql =  "SELECT * FROM Animal where animalID = $animalID";
	$result = $mysqli->query($sql);
	if (!$result) errorPage($mysqli->errno, $mysqli->error, $sql);
	else {
		// we should have just one row, since we are selecting by PK.
		$row = $result->fetch_array();
		$animalName = $row['animalName'];
		$gender = $row['gender'];
		$species = $row['species'];
		$estBirthdate = $row['estBirthdate'];
		$age = prettyAge( $row['estBirthdate'], date("Y-m-d"));
		$url = $row['url'];
	}
	$result->close();

	/* POST */
	
	// Set up for POST, grabbing the variables
	$isPost = ($_SERVER['REQUEST_METHOD'] == 'POST');
	$p_vitalSignTypeID = $isPost?$_POST['vitalSignTypeID']:"";	
	$vitalValue = $isPost?$_POST['vitalValue']:"";
	if ($p_vitalSignTypeID == 7) $vitalValue = $vitalValue * $_POST['weightUnit'];
	$p_vitalDateTime = $isPost?DateTime2MySQL($_POST['vitalDate']." ".$_POST['vitalTime']):date('Y-m-d H:i:s');
	$note = $isPost?$_POST['note']:"";
	$p_action = $isPost?$_POST['action']:"";
	
	// For POST, we are either updating or deleting.  Check what was posted through "action" (a hidden input field.)
	if ($isPost) {
		
		// Do some error checking
		if ($vitalValue == "") $errString .= "Value is required!<br>";
		if ($p_vitalDateTime == "") $errString .= "Date is required!<br>";
		if ($p_vitalDateTime > date('Y-m-d H:i:s')) $errString .= "<b>Date</b> can't be greater then today.<br>";

		if ($errString == "")  {

			$qVitalVal=lbt($vitalValue);
			$qNote=lbt($note);

			$nextDose = (isset($nextDose)?"'$nextDose'":"NULL");
			$insertSQL = "insert into VitalSign VALUES 
				('$p_vitalDateTime', '$qVitalVal', '$qNote', $p_vitalSignTypeID, $animalID);";
			$updateSQL = "update VitalSign set 
						vitalDateTime='$p_vitalDateTime', vitalValue = '$qVitalVal', 
						note='$qNote',  vitalSignTypeID=$p_vitalSignTypeID 
						WHERE vitalSignTypeID=$vitalSignTypeID and animalID=$animalID and vitalDateTime='$vitalDateTime';";

			$sql = "CALL VitalUpsert($animalID, $p_vitalSignTypeID, '$p_vitalDateTime', $qVitalVal, '$qNote');";
//			errorPage(0, '', $sql);
			$mysqli->query($sql);
			if ($mysqli->errno) errorPage($mysqli->errno, $mysqli->error, $p_action=="edit"?$updateSQL:$insertSQL);

			// If a return page was given, navigate back to it after the update/delete.
			if ($retPage) header("location:$retPage.php?animalID=$animalID");

			// reset... 
			$action = $note = $nextDose = $medicationName = $vitalSignTypeID = "";
			$vitalDateTime = date('Y-m-d H:i');
		}
	}

	// Otherwise, this is a GET request-- prepare to either delete or edit
	else {
		if ($action == "delete") {
			$sql = "delete from VitalSign WHERE animalID=$animalID and vitalSignTypeID=$vitalSignTypeID and vitalDateTime = '$vitalDateTime';";
			$result = $mysqli->query($sql);
			if ($mysqli->errno) errorPage($mysqli->errno, $mysqli->error, $sql);
			header("Location: $retPage.php?animalID=$animalID");
		}
		
		// For an edit, we want to pull the information on the row that we want to edit to show 
		// to the user
		else if ($action == "edit") {
			$sql = "select * FROM VitalSign WHERE animalID=$animalID and vitalSignTypeID=$vitalSignTypeID AND vitalDateTime = '$vitalDateTime';";
			$result = $mysqli->query($sql);
			if ($mysqli->errno)   errorPage($mysqli->errno, $mysqli->error, $sql);
			else {		// should just have one row as we are adding by PK
				$row = $result->fetch_array();
				$vitalDateTime = date("Y-m-d H:i", strtotime($row['vitalDateTime']));
				$vitalValue = $row['vitalValue'];
				$note = $row['note'];
				$result->close();
			}
		}
	}
	$vitalList = array();
	
	pixie_header("View Vitals: $animalName", $userName, "", $isAdmin);

?>

<font color=red><?=$errString?></font>

<!-- Add Vitals Form -->
<form  action="" method="POST">
	<table id=criteria width=100% >
		<tr>
			<td>
				<table>
					<tr>
						<td><b>Vital Sign Name:</b></td>
						<td>
							<select name=vitalSignTypeID id="vitalSignType">                  
								<?php
									
									$sql = "select * FROM VitalSignType WHERE species='' or species='$species';";
									$result = $mysqli->query($sql);
									if ($mysqli->errno)   errorPage($mysqli->errno, $mysqli->error, $sql);
									while ($row=$result->fetch_array()) {
										$vitalList[] = array (
											'vitalSignTypeID' 	=> $row['vitalSignTypeID'],
											'vitalSignTypeName'	=> $row['vitalSignTypeName'],
											'range'				=> ($row['low']>0?"(".$row['low']."-".$row['hi'].")":""),
											'units'				=> $row['units'],
											'hi'				=> $row['hi'],
											'low'				=> $row['low']
										);
								?>
										<option value="<?= $row['vitalSignTypeID'] ?>"<?= ($vitalSignTypeID==$row['vitalSignTypeID']?"selected":"") ?>><?= $row['vitalSignTypeName'] ?></option>
								<?php
									}
									$result->close();
								?> 
							</select>
							<a href="editTables.php?tableName=VitalSignType&retPage=viewVitals&animalID=<?=$animalID?>">Edit List</a>   
						</td>
					</tr>
					<tr>
						<td id="leftHand"><b>Value*</b></td><td id="rightHand"><input size="15" type="txt" name="vitalValue"  id="vitalValue" value="<?=$vitalValue?>""/><select id="weightUnit" name="weightUnit" hidden><option value=1>lbs</option><option value=2.205>kgs</option></select></td>
					</tr>
					<?= trd_labelData("Date", $vitalDateTime?date("Y-m-d",  strtotime($vitalDateTime)):date("Y-m-d"), "vitalDate", true, "date") ?>
					<?= trd_labelData("Time", $vitalDateTime?date("H:i:s",    strtotime($vitalDateTime)):date("H:i:s"), "vitalTime", true, "time") ?>
					<tr>
						<td  style="text-align: right;" >Note: </td><td><textarea type="memo" name="note" cols="30"><?= $note ?></textarea></td>
					</tr>
					<tr>
						<td colspan="2"> 
							<input hidden type="txt" name="action" value="<?= $action ?>"/>
							<input type="submit" value="<?= ($action=="edit"?"Edit":"Add") ?> Vital Sign" /> 
							<TODOinput type="submit" value="Cancel (not working)" formaction="<?="viewVaccination.php?animalID=$animalID"?>" /> 
							<a href="viewVitals.php?animalID=<?= $animalID ?>"><?= ($action=="edit"?"Add New":"Cancel") ?></a>
						</td>
					</tr>
				</table>
			</td>
			<td>
				<table> <!-- first column of demographic information -->
					<tr><td colspan=2></td><b>Vital Sign Information for:</b></td></tr>
					<?= trd_labelData("Name", $animalName) ?>
					<?= trd_labelData("Birthdate", MySQL2Date($estBirthdate)) ?>
					<?= trd_labelData("Current age", $age) ?>
					<tr><td style="text-align: left;" colspan="2"><a href="viewAnimal.php?animalID=<?= $animalID ?>">Back to <?= $animalName ?></a></td></tr>
					<tr><td style="text-align: left;" colspan="2">Edit: <a href="viewTests.php?animalID=<?= $animalID ?>">Tests</a> <a href="viewVaccination.php?animalID=<?= $animalID ?>">Vaccincations</a> <a href="addTransfer.php?animalID=<?= $animalID ?>">Transfers</a>
					</td></tr>
				</table>			
			</td>
		</tr>
	</table>
</form>


<table id=tabular>
<?php 
	foreach ($vitalList as $vital) {
?>
	<tr >
		<td colspan="5">
			<font color="purple"><b><a href="viewVitals.php?animalID=<?= $animalID ?>&vitalSignTypeID=<?= $vital['vitalSignTypeID'] ?>"><?= $vital['vitalSignTypeName'] ?></b></font>
		</td>
	</tr>
	<tr>
	  <th width="150px">Date</th>
	  <th width="150px">Value</th>
	  <th width="100px">Range</th>
	  <th>Note</th>
	  <th width="100px">&nbsp;</th>
	</tr>
	<?php
		$sql = "select * FROM VitalSign WHERE animalID=$animalID and vitalSignTypeID=".$vital['vitalSignTypeID'] . " ORDER BY vitalDateTime DESC";
		$result = $mysqli->query($sql);
		if ($mysqli->errno)   errorPage($mysqli->errno, $mysqli->error, $sql);
		while ($row=$result->fetch_array()) {
	?>
	<tr>
		<td><?= MySQL2Date($row['vitalDateTime']) ?> <?= MySQL2Time($row['vitalDateTime']) ?>&nbsp;</td>
		<td><font color="<?php
			$modifier='';
			if (($vital['low']+$vital['low'])>0) {
				if ($row['vitalValue'] < $vital['low']) {
					echo "blue";
					$modifier=" L";
				} else if ($row['vitalValue'] > $vital['hi']) {
					echo "red";
					$modifier=" H";
				} else echo "black";
			}
		?>"><?= $row['vitalValue']." ".$vital['units'].$modifier ?>&nbsp;</font></td>
		<td><?= $vital['range'] ?>&nbsp;</td>
		<td style="white-space: pre-line;"><?= $row['note'] ?>&nbsp;</td>
		<td>
			<a href="<?= "viewVitals.php?animalID=$animalID&vitalSignTypeID=".$vital['vitalSignTypeID']."&vitalDateTime=".$row['vitalDateTime']."&action=edit" ?>">Edit</a> / 
			<a href="<?= "viewVitals.php?animalID=$animalID&vitalSignTypeID=".$vital['vitalSignTypeID']."&vitalDateTime=".$row['vitalDateTime']."&action=delete" ?>"
				onclick="return confirm('Are you sure you want to delete this record?  This action can not be undone.');">Delete</a>
		</td>
	</tr>
	<?php
		}
	?>
	<tr><td colspan="5">&nbsp;</td></tr> <!--  Blank row -->
<?php
	}
?>
</table>

<table>
<tr><td><b>Key</b></td></tr>
<tr><td><font color="blue">Blue:</font> Too Low</td></tr>
<tr><td><font color="red">Red: </font> Too High</td></tr>
<tr><td><font color="black">Black: </font> Within Range</td></tr>
</table>

<script>
        const vst = document.getElementById("vitalSignType");
        vst.addEventListener("change", (evt) => vitalSignChange(vst));
	vitalSignChange(vst);

        function vitalSignChange(vst) {
	        const wu = document.getElementById("weightUnit");
                vst_id = vst.value;
		if (vst_id == 7) {
			wu.hidden = false;
		} else  {
			wu.hidden = true;
		}
        }
</script>



<?php pixie_footer(); ?>
