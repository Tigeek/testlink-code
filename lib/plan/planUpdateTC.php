<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * @version $Id: planUpdateTC.php,v 1.8 2005/10/10 06:55:52 franciscom Exp $
 * @author Martin Havlat
 * 
 * Update Test Cases within Test Case Suite 
 * 
 * @author Francisco Mancardi - 20051009
 * BUGID 0000162: Moving a Testcase to another category
 *
 * @author Francisco Mancardi - 20050821
 * corrected autogol exresults KO, exresult OK
 *
 */         
require('../../config.inc.php');
require("../functions/common.php");
require_once("../../lib/functions/lang_api.php");
testlinkInitPage();

$resultString = null;
$arrData = array(); 
$tpID = $_SESSION['testPlanId'];


if(isset($_POST['updateSelected']))
{
	tLog('$_POST: ' . implode(',', $_POST));
	next($_POST); // pass the submit button
	
	while ($idUpdate = current($_POST)) 
	{
	
		// 20050730 - fm
		// Grab Test Case Spec ID from Test Plan Test Case ID
		$tcID = key($_POST);
		tLog('Update TC id: ' . $tcID);
 
    // 20050929 - fm - refactoring name 
    // 20050730 - fm - BUGID: SF1242462
		$sql = " SELECT testcase.*, " .
		       " cat.id as cat_id, mgt.name as cat_name,cat.mgtcatid as cat_mgtcatid " . 
		       " FROM testcase, category cat, mgtcategory mgt" .
		       " WHERE cat.mgtcatid = mgt.id" .
		       " AND cat.id = testcase.catid " .
		       " AND testcase.id=" . $tcID;
		       
		$result = do_mysql_query($sql);
		$tctp_data = mysql_fetch_assoc($result);
  
		$specId = $tctp_data['mgttcid'];

    // 
    // 20050730 - fm
    // BUGID: SF1242462
		// Grab the relevant data from tc specs (mgt* Tables)
		// mgtTestCase table
		$tc_specs = get_tc_specs($specId);

    $mgtRow = $tc_specs;
		$mgtTitle=mysql_escape_string($mgtRow['title']);
		$mgtSteps=mysql_escape_string($mgtRow['steps']);
		$mgtExresult=mysql_escape_string($mgtRow['exresult']);
		$mgtKeywords=$mgtRow['keywords'];
		$mgtCatid=$mgtRow['catid'];
		$mgtVersion=$mgtRow['version'];
		$mgtSummary=mysql_escape_string($mgtRow['summary']);
		$mgtTCorder = $mgtRow['TCorder'];
			
		if($mgtVersion == "")
		{
			del_tc_from_tp($tcID);
			$resultString .= "<p>".lang_get('planupdate_tc_deleted1')." [" . $specId . "] ".
			                       lang_get('planupdate_tc_deleted2')."</p>";
		}
		else
		{
			// --------------------------------------------------------------------------
		  // 20050730 - fm
      // BUGID: SF1242462
    	// Build the query to Update the testcase with the new data
	    $updateSQL =  'update testcase ' .
	                  'set TCorder=' . $mgtTCorder . 
	                  ' , title="'      . $mgtTitle . 
			              '", steps="'    . $mgtSteps . 
			              '", exresult="' . $mgtExresult . 
			              '", keywords="' . $mgtKeywords . 
			              '", version="'  . $mgtVersion . 
			              '", summary="'  . $mgtSummary . '" '; 

			// --------------------------------------------------------------------------
			if( $mgtCatid != $tctp_data['cat_mgtcatid'])
		  {	
			  // Category Has Changed !!!
        $cat_id=process_tc_cat_change($tcID, $tc_specs, $tpID);		

	      $updateSQL .= ', catid=' . $cat_id;

      } 
			// --------------------------------------------------------------------------
			$updateSQL .= ' where id=' . $tcID;

		  $updateResult = do_mysql_query($updateSQL);
			
			$resultString .= "<p>".lang_get('planupdate_tc_updated1')." [" . $specId . "]: ".$mgtTitle . 
			                       lang_get('planupdate_tc_updated2')."</p>";


		}

   		next($_POST);
	}

	if (count($_POST) == 0) // only submit button 
	{
		$resultString = "<p>".lang_get('plan_update_no_tc_updated')."</p>\n";
	}
}

// walk through the project test cases
$sqlTC = " SELECT testcase.id from testcase, category, component " .
		     " WHERE testcase.catid = category.id AND category.compid = component.id " .
		     " AND component.projid = " . $tpID;
$resultTC = do_mysql_query($sqlTC);
//tLog(mysql_errno() . ": " . mysql_error());

if ($resultTC)
{
	while($rowTC = mysql_fetch_array($resultTC))
	{
		// pass $tcCounter so the proper headers are added if there is at least one tc...
		displayTC($rowTC[0]); 
	}
}

if (count($arrData) == 0)
	$changesRequired = 'no'; 
else
	$changesRequired = 'yes'; 

$smarty = new TLSmarty;
$smarty->assign('changesRequired', $changesRequired);
$smarty->assign('testPlanName', $_SESSION['testPlanName']);
$smarty->assign('resultString', $resultString);
$smarty->assign('arrData', $arrData);
$smarty->display('planUpdateTC.tpl');




//
//
// Rev : 20050730 - fm
//       BUGID: SF1242462
//
function displayTC($id)
{
	global $arrData;

  // 20050730 - fm
  // BUGID: SF1242462
  // added category.mgtcatid, testcase.catid
  //
		$sql = " SELECT MGTCAT.name as TPTC_category, MGTCOMP.name as TPTC_component, " .
	       " TC.id, TC.title, version, mgttcid, CAT.mgtcatid, TC.catid " .
	       " FROM testcase TC, component COMP, category CAT, mgtcategory MGTCAT, mgtcomponent MGTCOMP " .
	       " WHERE CAT.mgtcatid = MGTCAT.id " .
	       " AND COMP.mgtcompid = MGTCOMP.id " .
	       " AND COMP.id=CAT.compid " .
	       " AND CAT.id=TC.catid " . 
	       " AND TC.id=" . $id . 
	       " ORDER BY TC.TCorder";
       
	       
	$result = @do_mysql_query($sql);


	while($row = mysql_fetch_array($result)){

		//Assign values from the test case query
		$id = $row['id'];
		$title = $row['title'];
		$version = $row['version'];
		$mgtID = $row['mgttcid'];
		$containerName = $row['TPTC_component'] . '/' . $row['TPTC_category'];

    // 20050730 - fm
    // BUGID: SF1242462
    // added , catid
		$sqlMgt = "SELECT version, catid FROM mgttestcase WHERE mgttestcase.id=" . $mgtID;
		$mgtResult = do_mysql_query($sqlMgt);
    $mgtRow = mysql_fetch_array($mgtResult);



		if (mysql_num_rows($mgtResult) == 0) 
		{
			//if it is deleted set status to deleted 
			$mgtVersion = "---";
			$status = "deleted";
		} else {
			$mgtVersion = mysql_result($mgtResult,0);
			$status = "updated";
		}
		
		// paste data if versions differs

		// -------------------------------------------------------
    // 20050730 - fm
    // BUGID: SF1242462
		$load_data = 0;
		$reason_to_update = "";
		if ($version != $mgtVersion )
		{
			$reason_to_update .= lang_get('different_versions'); "Diff. Versions"; 
			$load_data = 1;
		} 
		
		if( $row['mgtcatid'] != $mgtRow['catid']) 
		{
			if ($load_data )
		  {	
			  $reason_to_update .= " / ";
		  }	
			$reason_to_update .= lang_get('category_has_changed'); "Category has changed"; 
			$load_data = 1;
		}
		// -------------------------------------------------------

		if ($load_data) {
			$arrData[] = array("container" => $containerName, "specId" => $mgtID, 
					               "planId" => $id, "name" => $title, "status" => $status, 
					               "specVersion" => $mgtVersion, "planVersion" => $version, 
					               "reason" => $reason_to_update);
		}

	}//end while
}


// ----------------------------------------------------------------------------
// get_tc_specs() - GET TestCase SPECifications 
// get data from MGT tables
//
// args:
//
// returns: assoc. array with tc data
//
// rev :
//       20050731 - fm
//       creation
//       Added to solve 
//       BUGID: SF1242462 - test plan not updated when test case category changed
//
// ----------------------------------------------------------------------------
function get_tc_specs($tc_id)
{
$sql = " SELECT MGTTC.id as tcid,  catid, compid,  MGTTC.title , " .
       " MGTCAT.name as cat_name, MGTCOMP.name as comp_name, " .
       " steps, exresult, keywords, version, summary, TCorder " . 
       " FROM mgttestcase  MGTTC , mgtcategory MGTCAT, mgtcomponent MGTCOMP " .
       " WHERE MGTCOMP.id = MGTCAT.compid " .
       " AND   MGTCAT.id = MGTTC.catid " .
       " AND   MGTTC.id=" . $tc_id ;
      
$result = do_mysql_query($sql);
$row = mysql_fetch_array($result);
          
return ($row);
}

// ----------------------------------------------------------------------------
// del_tc_from_tp() - DELete TestCase data FROM (some) Test Plan tables
//
// args: TestCase ID
//
// returns: -
//
// rev :
//      20050731 - fm
//      creation
//      Added for refactoring while trying to solve:
//      BUGID: SF1242462 - test plan not updated when test case category changed
// ----------------------------------------------------------------------------
function del_tc_from_tp($tc_id)
{

$sql = "DELETE FROM testcase WHERE id=" . $tc_id;
$dummy = do_mysql_query($sql);

$sql = "DELETE FROM results WHERE tcid=" . $tc_id;
$dummy = do_mysql_query($sql); 

$sql = "DELETE FROM bugs WHERE tcid=" . $tc_id;
$dummy = do_mysql_query($sql); 

}



// ----------------------------------------------------------------------------
//
// Manages the process of updating Test Plan data when a TC has changed
// it's category
//
// 
// args: Test Case ID
//       Test Case Specifications data (assoc Array )
//
// returns:
//         the category id for Test Case ID, in the Test Plan
//
// rev:
//      20051009 - fm
//      BUGID 0000162: Moving a Testcase to another category
//      interface changes added $tpID
//
//      20050731 - fm
//      creation
//      Added to solve 
//      BUGID: SF1242462 - test plan not updated when test case category changed
//
function process_tc_cat_change($tc_id, $tc_specs, $tpID)
{

//
// If testcase spec has changed category (mgtcat), 
// we need to verify if this mgtcat is present in the Test Plan
// If yes -> we need only to do an update
// If not -> we need to add the category to the testplan, and maybe
//           the component.(*)
//           (*) Attention: 
//               versions 1.5.x ALLOW only test case
//               moving between CATEGORIES of the SAME COMPONENT

// 20051009 - fm - my bug
$sql = " SELECT CAT.* " .
       " FROM category CAT, component COMP " .
       " WHERE CAT.compid = COMP.id " .
       " AND COMP.projid = " .  $tpID .
       " AND CAT.mgtcatid=" . $tc_specs['catid'];
$result = do_mysql_query($sql);

if (mysql_num_rows($result) == 0) 
{
  // remember: 
  // mgtcat belongs to a mgtcomp, then we need to check is mgtcomp
  // is part of the test plan.
  //
  $sql = " SELECT * FROM component " .
         " WHERE component.projid = " . $tpID .
         " AND mgtcompid=" . $tc_specs['compid'];
  $result = do_mysql_query($sql);

  if (mysql_num_rows($result) == 0) 
  {
    echo "MGT Comp and Cat have to be added to Test Plan";    
  }
  else
  {
  	// fm - my bug - missing JOIN condition with tpID
    // get mgtcategory data
    // 
    $sql = " SELECT MGTCAT.id as mgtcat_id, MGTCAT.name mgtcat_name, " .
           " MGTCAT.compid as mgtcat_compid, MGTCAT.CATorder as mgtcat_CATorder, " .
           " COMP.id compid " .
           " FROM mgtcategory MGTCAT, component COMP" .
           " WHERE MGTCAT.compid = COMP.mgtcompid" .
           " AND COMP.projid = " . $tpID .
           " AND MGTCAT.id=" . $tc_specs['catid'] ;

    $result = do_mysql_query($sql);
    $mgtcatRow = mysql_fetch_assoc($result);

    // 20051009 - fm
    // BUGID 0000162: Moving a Testcase to another category
    // problem name field removed from category table.
    // excerpt from planAddTC.php
		$sqlAddCAT = " INSERT INTO category(mgtcatid,compid,CATorder) " .
		             " VALUES (" . $mgtcatRow['mgtcat_id'] . "," . 
		                           $mgtcatRow['compid'] . "," . 
		                           $mgtcatRow['mgtcat_CATorder'] . ")";
		$resultAddCAT = do_mysql_query($sqlAddCAT); 
		$cat_id =  mysql_insert_id(); //Grab the id of the category just entered
    
  }    
}      
else   
{
  $cat_row = mysql_fetch_assoc($result);
  $cat_id = $cat_row['id'];
}   


return ($cat_id);
}  // function end


?>
