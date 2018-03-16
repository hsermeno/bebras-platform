<?php
/* Copyright (c) 2012 Association France-ioi, MIT License http://opensource.org/licenses/MIT */

require_once '../shared/tinyORM.php';

$allCategories = array("blanche", "jaune", "orange", "verte", "bleue", "cm1cm2", "6e5e", "4e3e", "2depro", "2de", "1reTalepro", "1reTale", "all");


$backend_hints = array();
$failure_backend_hints = array();

function exitWithJson($json) {
   global $backend_hints;
   if (!empty($backend_hints)) {
      header('X-Backend-Hints: ' . join(' ', $backend_hints));
   }
   header("Content-Type: application/json");
   header("Connection: close");
   echo json_encode($json);
   exit;
}

function exitWithJsonFailure($message, $extras = null) {
   $result = array("success" => false, "message" => $message);
   if ($extras != null) {
      array_replace($result, $extras);
   }
   global $backend_hints;
   global $failure_backend_hints;
   $backend_hints = $failure_backend_hints;
   exitWithJson($result);
}

function addBackendHint ($hint) {
   global $backend_hints;
   array_push($backend_hints, '"' . $hint . '"');
}

function addFailureBackendHint ($hint) {
   global $failure_backend_hints;
   array_push($failure_backend_hints, '"' . $hint . '"');
}

function escapeHttpValue($value) {
   if (preg_match("/^[0-9A-Za-z_-]*$/", $value)) {
      return $value;
   }
   return '#' . base64_encode($value);
}

function createTeamFromUserCode($db, $password) {
   // Use a custom function to fetch code from algorea_registration or anywhere else. You can
   // create it in config_local.php.
   // The function can set two $_SESSION variables: userCode and userCodeGroupID.
   //   - userCode can be set to $password and will be used as team.password for the created team
   //   - userCodeGroupID is the group.ID in which the teams will be created. It's best to
   //       use openGroup() in your function for that, it will set $_SESSION correctly. Note
   //       that you must set $_SESSION['userCode'] after calling openGroup()
   if (function_exists('customCreateTeamFromUserCode')) {
      return customCreateTeamFromUserCode($db, $password);
   } else {
      return (object)array("success" => false, "message" => "Mot de passe invalide");
   }
}

function updateSessionWithContestInfos($row) {
   $_SESSION["contestID"] = $row->contestID;
   $_SESSION["contestName"] = $row->contestName;
   $_SESSION["contestFolder"] = $row->folder;
   $_SESSION["contestOpen"] = $row->open;
   $_SESSION["contestShowSolutions"] = intval($row->showSolutions);
   $_SESSION["contestVisibility"] = $row->visibility;
   $_SESSION["bonusScore"] = intval($row->bonusScore);
   $_SESSION["allowTeamsOfTwo"] = intval($row->allowTeamsOfTwo);
   $_SESSION["askParticipationCode"] = intval($row->askParticipationCode);
   $_SESSION["newInterface"] = intval($row->newInterface);
   $_SESSION["customIntro"] = $row->customIntro;
   $_SESSION["fullFeedback"] = intval($row->fullFeedback);
   $_SESSION["nbUnlockedTasksInitial"] = intval($row->nbUnlockedTasksInitial);
   $_SESSION["subsetsSize"] = intval($row->subsetsSize);
   $_SESSION["nextQuestionAuto"] = intval($row->nextQuestionAuto);
   $_SESSION["allowPauses"] = intval($row->allowPauses);
}

function commonLoginTeam($db, $password) {
   global $tinyOrm, $config;
   $password = trim($password);
   $stmt = $db->prepare("SELECT `team`.`ID` as `teamID`, `group`.`ID` as `groupID`, IFNULL(`team`.`contestID`, `group`.`contestID`) as `contestID`, `group`.`isPublic`, `group`.`name`, `team`.`nbMinutes`, `contest`.`bonusScore`, `contest`.`allowTeamsOfTwo`, `contest`.`askParticipationCode`, `contest`.`newInterface`, `contest`.`customIntro`, `contest`.`fullFeedback`, `contest`.`nextQuestionAuto`, `contest`.`nbUnlockedTasksInitial`, `contest`.`subsetsSize`, IFNULL(subContest.folder, `contest`.`folder`) as `folder`, `contest`.`name` as `contestName`, `contest`.`open`, `contest`.`showSolutions`, `contest`.`allowPauses`, `contest`.`visibility`, `group`.`schoolID`, `team`.`endTime` FROM `team` JOIN `group` ON (`team`.`groupID` = `group`.`ID`) JOIN `contest` ON (`group`.`contestID` = `contest`.`ID`) LEFT JOIN `contest` subContest ON subContest.ID = team.contestID WHERE `team`.`password` = ?");
   $stmt->execute(array($password));
   $row = $stmt->fetchObject();
   if (!$row) {
      return createTeamFromUserCode($db, $password);
   }
   if ($config->db->use == 'dynamoDB') {
      try {
         $teamDynamoDB = $tinyOrm->select('team', array('ID', 'groupID'), array('password' => $password));
      } catch (\Aws\DynamoDb\Exception $e) {
         error_log($e->getMessage . " - " . $e->getCode());
         error_log('DynamoDB error finding team with password: '.$password);
      }
      if (!isset($teamDynamoDB[0]) || $row->teamID != $teamDynamoDB[0]['ID'] || $row->groupID != $teamDynamoDB[0]['groupID']) {
         //error_log('enregistrement différent entre MySQL et DynamoDB! SQL: teamID='.$row->teamID.', groupID='.$row->groupID.(isset($teamDynamoDB[0]) ? ' DDB: ID='.$teamDynamoDB[0]['ID'].', groupID='.$teamDynamoDB[0]['groupID'] : ' pas d\'enregistrement DynamoDB'));
         //return (object)array("success" => false, "message" => "enregistrement différent entre MySQL et DynamoDB!");
         $_SESSION['mysqlOnly'] = true;
      } elseif (isset($_SESSION['mysqlOnly'])) {
         unset($_SESSION['mysqlOnly']);
      }
   }
   if ($row->open == "Closed") {
      return (object)array("success" => false, "message" => "Le concours lié à votre participation est actuellement fermé. Il réouvrira bientôt.");
   }
   updateSessionWithContestInfos($row);
   $_SESSION["teamID"] = $row->teamID;
   $_SESSION["name"] = $row->name;
   $_SESSION["nbMinutes"] = intval($row->nbMinutes);
   $_SESSION["teamPassword"] = $password;
   $_SESSION["groupID"] = $row->groupID;
   $_SESSION["schoolID"] = $row->schoolID;
   $_SESSION["isPublic"] = intval($row->isPublic);

   return (object)array(
      "success" => true,
      "name" => $_SESSION["name"],
      "teamID" => $_SESSION["teamID"],
      "nbMinutes" => $_SESSION["nbMinutes"],

      "contestID" => $_SESSION["contestID"],
      "contestName" => $_SESSION["contestName"],
      "contestFolder" => $_SESSION["contestFolder"],
      "contestOpen" => $_SESSION["contestOpen"],
      "contestShowSolutions" => $_SESSION["contestShowSolutions"],
      "contestVisibility" => $_SESSION["contestVisibility"],
      "bonusScore" => $_SESSION["bonusScore"],
      "allowTeamsOfTwo" => $_SESSION["allowTeamsOfTwo"],
      "askParticipationCode" => $_SESSION["askParticipationCode"],
      "newInterface" => $_SESSION["newInterface"],
      "nextQuestionAuto" => $_SESSION["nextQuestionAuto"],
      "customIntro" => $_SESSION["customIntro"],
      "fullFeedback" => $_SESSION["fullFeedback"],
      "nbUnlockedTasksInitial" => $_SESSION["nbUnlockedTasksInitial"],
      "subsetsSize" => $_SESSION["subsetsSize"],
      );
}

function reconnectSession($db) {
   if (!isset($_POST["teamPassword"])) {
      echo json_encode(array("success" => false, "message" => "Session invalide"));
      error_log("invalid session : ".json_encode($_SESSION));
      error_log(json_encode($_REQUEST));
      return;
   }
   $res = commonLoginTeam($db, $_POST["teamPassword"]);
   if (!$res->success) {
      echo json_encode($res);
      error_log("invalid session and bad password : ".json_encode($_SESSION));
      error_log(json_encode($_REQUEST));
      return;
   }
   $teamID = $_SESSION["teamID"];
   error_log("reconnexion de session acceptée ".json_encode($_REQUEST));
   // TODO: factoriser ce qui suit (copier-collé issu de data.php)
   $stmt = $db->prepare("SELECT TIME_TO_SEC(TIMEDIFF(UTC_TIMESTAMP(), `team`.`startTime`)) as `timeUsed`, `endTime`, UNIX_TIMESTAMP() as `timeNow` FROM `team` WHERE `ID` = ?");
   $stmt->execute(array($teamID));
   $row = $stmt->fetchObject();
   $_SESSION["startTime"] = $row->timeNow - intval($row->timeUsed);
   if ($row->endTime != null) {
      $_SESSION["closed"] = true;
   }
   return true;
}

function reloginTeam($db, $password, $teamID) {
   global $tinyOrm, $config;
   $stmt = $db->prepare("SELECT `group`.`password`, `contest`.`status`, `group`.`isPublic` FROM `group` JOIN `contest` ON (`group`.`contestID` = `contest`.`ID`) WHERE `group`.`ID` = ?");
   $stmt->execute(array($_SESSION["groupID"]));
   $row = $stmt->fetchObject();
   if (!$row) {
      exitWithJsonFailure("Groupe invalide");
   }
   if ($row->password !== $password) {
      exitWithJsonFailure("Mot de passe invalide");
   }
   if ($row->status == "Closed" || $row->status == "PreRanking") {
      exitWithJsonFailure("Concours fermé");
   }
   $stmt = $db->prepare("SELECT `password`, `nbMinutes` FROM `team` WHERE `ID` = ? AND `groupID` = ?");
   $stmt->execute(array($teamID, $_SESSION["groupID"]));
   $row = $stmt->fetchObject();
   if (!$row) {
      exitWithJsonFailure("Équipe invalide pour ce groupe");
   }
   if ($config->db->use == 'dynamoDB') {
      try {
         $teamDynamoDB = $tinyOrm->get('team', array('ID', 'groupID', 'nbMinutes'), array('ID' => $teamID));
      } catch (Aws\DynamoDb\Exception\DynamoDbException $e) {
         error_log($e->getAwsErrorCode() . " - " . $e->getAwsErrorType());
         error_log('DynamoDB error retrieving: '.$teamID);
      }
      if (!count($teamDynamoDB) || $teamDynamoDB['groupID'] != $_SESSION["groupID"]) {
         //error_log('team.groupID différent entre MySQL et DynamoDB! nb résultats DynamoDB: '.count($teamDynamoDB).(count($teamDynamoDB) ? ', $teamDynamoDB[groupID]'.$teamDynamoDB['groupID'].', $_SESSION[groupID]'.$_SESSION["groupID"] : ''));
         $_SESSION["mysqlOnly"] = true;
      } elseif (isset($_SESSION['mysqlOnly'])) {
         unset($_SESSION['mysqlOnly']);
      }
   }
   $_SESSION["teamID"] = $teamID;
   $_SESSION["teamPassword"] = $row->password;
   $_SESSION["nbMinutes"] = intval($row->nbMinutes);
}

function updateTeamCategories($db, $teamID) {
   $query = "SELECT `algorea_registration`.`ID`, `algorea_registration`.`category` as `qualifiedCategory`, `algorea_registration`.`validatedCategory` ".
      "FROM `contestant` ".
      "JOIN `algorea_registration` ON `algorea_registration`.`ID` = `contestant`.`registrationID` ".
      "WHERE `contestant`.`teamID` = :teamID";
   $stmt = $db->prepare($query);
   $stmt->execute(array("teamID" => $teamID));
   $qualifiedCategories = array();
   while ($row = $stmt->fetchObject()) {
      updateRegisteredUserCategory($db, $row->ID, $row->qualifiedCategory, $row->validatedCategory); 
   }
}

function updateRegisteredUserCategory($db, $ID, $prevQualifiedCategory, $prevValidatedCategory) {
   global $allCategories;
   $query = "SELECT `qualificationCategory`, `validationCategory`".
      "FROM (SELECT `contest`.`qualificationCategory`, `contest`.`validationCategory`, `contest`.`qualificationScore`, SUM(team_question.ffScore) as sumScores, team.score ".
      "FROM `algorea_registration` ".
      "JOIN `contestant` ON `contestant`.`registrationID` = `algorea_registration`.`ID` ".
      "JOIN `team` ON `contestant`.`teamID` = `team`.`ID` ".
      "JOIN `team_question` ON `team_question`.`teamID` = `team`.`ID` ".
      "JOIN `group` ON `group`.`ID` = `team`.`groupID` ".
      "JOIN `contest` ON `contest`.`ID` = `group`.`contestID` ".
      "WHERE `algorea_registration`.`ID` = :ID ".
      "GROUP BY `team`.`ID`) results ".
      "WHERE (`results`.`score` >= `results`.`qualificationScore` OR `results`.`sumScores` >= `results`.`qualificationScore`)".
      "GROUP BY `qualificationCategory`";
   $stmt = $db->prepare($query);
   $stmt->execute(array("ID" => $ID));
   $qualifiedCategories = array();
   $validatedCategories = array();
   while ($row = $stmt->fetchObject()) {
      $qualifiedCategories[$row->qualificationCategory] = true;
      $validatedCategories[$row->validationCategory] = true;
   }
   $maxQualifiedCategory = "";
   $maxValidatedCategory = "";
   foreach($allCategories as $category) {
      if (($category == $prevQualifiedCategory) || (isset($qualifiedCategories[$category]))) {
         $maxQualifiedCategory = $category;
      }
      if (($category == $prevValidatedCategory) || (isset($validatedCategories[$category]))) {
         $maxValidatedCategory = $category;
      }
   }
   if (($maxQualifiedCategory == "") && ($prevQualifiedCategory != "")) {
      $maxQualifiedCategory = $prevQualifiedCategory;
   }
   if (($maxQualifiedCategory != $prevQualifiedCategory) || ($maxValidatedCategory != $prevValidatedCategory)) {
      $query = "UPDATE `algorea_registration` SET `category` = :qualifiedCategory, `validatedCategory`= :validatedCategory WHERE ID = :ID";
      $stmt = $db->prepare($query);
      $stmt->execute(array("ID" => $ID,
         "qualifiedCategory" => $maxQualifiedCategory,
         "validatedCategory" => $maxValidatedCategory
      ));
   }
   return array("qualifiedCategory" => $maxQualifiedCategory, "validatedCategory" => $maxValidatedCategory);
}
