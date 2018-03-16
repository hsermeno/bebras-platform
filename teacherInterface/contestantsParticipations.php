<html>
<meta charset='utf-8'>
<body><style>
.results tr:first-child td {
   font-weight: bold;
   color-background:lightgray;
}

td {
   background-color: #F0F0F0;
}

.results tr td {
   border: solid black 1px;
   padding: 5px;
}
.orange {
   background-color: orange;
}
.blanche {
   background-color: white;
}
.grise {
   background-color: #C0C0C0;
   font-weight: bold;
}
.jaune {
   background-color: yellow;
}
.verte {
   background-color: lightgreen;
}
.bleue {
   background-color: #8080FF;
}

.rank {
   font-size: 10px;
}
</style>
<?php

require_once("../shared/common.php");
require_once("commonAdmin.php");

if (!isset($_SESSION["userID"])) {
   echo "Votre session a expiré, veuillez vous reconnecter.";
   exit;
}

$showCodes = 0;
if (isset($_GET["showCodes"])) {
   $showCodes = $_GET["showCodes"];
}

echo "<h1>Synthèse des résultats Castor et Algoréa</h1>";

if ($showCodes) {
   echo "<a href='contestantsParticipations.php?showCodes=0'>Masquer les codes de participants</a>";
} else {
   echo "<a href='contestantsParticipations.php?showCodes=1'>Afficher les codes de participants</a>";
}

echo "<p>Dans les résultats ci-dessous, des élèves peuvent apparaître en double s'ils n'ont pas utilisé leur code de participant pour participer à Algoréa. Nous réunirons bientôt leurs participations sur la base de leurs noms, prénoms et classe.</p>";

$grades = array(-1 => "Profs", -4 => "Autres", 4 => "CM1", 5 => "CM2", 6 => "6e", 7 => "5e", 8 => "4e", 9 => "3e", 10 => "2de", 11 => "1ère", 12 => "Tale", 13 => "2de<br/>pro", 14 => "1ère<br/>pro", 15 => "Tale<br/>pro", 16 => "6e Segpa", 17 => "5e Segpa", 18 => "4e Segpa", 19 => "3e Segpa", 20 => "Post-Bac");


$query = "
   SELECT
      IFNULL(contestant.registrationID, contestant.ID) as ID,
      contestant.firstName,
      contestant.lastName,
      contestant.grade,
      team.score,
      team.nbContestants,
      contestant.rank,
      contestant.schoolRank,
      team.participationType,
      team.groupID,
      `group`.name as groupName,
      team.password,
      algorea_registration.code,
      algorea_registration.firstName as regFirstName,
      algorea_registration.lastName as regLastName,
      algorea_registration.grade as regGrade,
      algorea_registration.category,
      algorea_registration.validatedCategory,
      `group`.contestID,
      contest.parentContestID,
      contest.name as contestName,
      parentContest.name as parentContestName,
      contest.language,
      contest.categoryColor,
      school.name as schoolName,
      school.ID as schoolID
   FROM `group`
      JOIN `school` ON `school`.ID = `group`.schoolID
      JOIN `contest` ON `group`.contestID = contest.ID
      JOIN team ON team.groupID = `group`.ID
      JOIN contestant ON contestant.teamID = team.ID
      LEFT JOIN algorea_registration ON contestant.registrationID = algorea_registration.ID
      LEFT JOIN `contest` parentContest ON contest.parentContestID = parentContest.ID
   WHERE (`contest`.ID IN (884044050337033997,118456124984202960) OR `contest`.parentContestID IN (884044050337033997,118456124984202960))
   AND `group`.userID = :userID
   ORDER BY schoolID, contestant.ID, team.score DESC";

$stmt = $db->prepare($query);
$stmt->execute(array("userID" => $_SESSION['userID']));

//echo $query." userID : ".$_SESSION['userID'];

$contests = array();
$mainContestsNames = array();
$curSchoolID = 0;
$schools = array();
$contestants = array();
$count = 0;
while ($row = $stmt->fetchObject()) {
   if ($row->schoolID != $curSchoolID) {
      if ($curSchoolID != 0) {
         $schools[$curSchoolID]["contestants"] = $contestants;
         $contestants = array();
      }
      $curSchoolID = $row->schoolID;
      $schools[$row->schoolID] = array("name"  => $row->schoolName);
   }
   $contestKey = $row->parentContestID;
   $mainContestKey = $row->parentContestID;
   $mainContestName = $row->parentContestName;
   if ($contestKey == null) {
      $contestKey = $row->contestID;
      $mainContestKey = $row->contestID;
      $mainContestName = $row->contestName;
   } else {
      $contestKey .= "_".$row->categoryColor;
   }
   if (!isset($contests[$mainContestKey])) {
      $mainContestsNames[$mainContestKey] = $mainContestName;
      $contests[$mainContestKey] = array();
   }
   if (!isset($contests[$mainContestKey][$row->categoryColor])) {
      $contests[$mainContestKey][$row->categoryColor] = $contestKey;
   }
   if (!isset($contestants[$row->ID])) {
      if ($row->regFirstName != null) {
         $infos = array(
             "firstName" => $row->regFirstName,
             "lastName" => $row->regLastName,
             "grade" => $row->regGrade,
             "code" => $row->code,
             "qualifiedCategory" => $row->category,
             "validatedCategory" => $row->validatedCategory,
             "bebrasGroup" => "-"
          );
      } else {
         $infos = array(
             "firstName" => $row->firstName,
             "lastName" => $row->lastName,
             "grade" => $row->grade,
             "code" => "-",
             "qualifiedCategory" => "-",
             "validatedCategory" => "-",
             "bebrasGroup" => "-"
          );
      }
      $contestants[$row->ID] = array(
          "infos" => $infos,
          "results" => array()
      );
   }
   if ($row->contestID == "118456124984202960") {
      $contestants[$row->ID]["infos"]["bebrasGroup"] = $row->groupName;
   }
   if (!isset($contestants[$row->ID]["results"][$contestKey])) {
      $contestants[$row->ID]["results"][$contestKey] = array(
         "score" => $row->score,
         "rank" => $row->rank,
         "schoolRank" => $row->schoolRank,
         "nbContestants" => $row->nbContestants,
         "participationType" => $row->participationType,
         "language" => $row->language
      );
   }
}
if ($curSchoolID != 0) {
   $schools[$curSchoolID]["contestants"] = $contestants;
}

foreach ($schools as $schoolID => $school) {
   echo "<h2>".$school["name"]."</h2>";
   $contestants = $school["contestants"];

   echo "<table class='results' cellspacing=0><tr><td rowspan=2>Groupe Castor</td><td rowspan=2>Prénom</td><td rowspan=2>Nom</td><td rowspan=2>Classe</td><td rowspan=2>Qualifié en<br/>catégorie</td>";
   if ($showCodes) {
      echo "<td rowspan=2>Code de participant</td>";
   }
   foreach ($contests as $mainContestKey => $categoryContests) {
      echo "<td colspan='".count($categoryContests)."'";
      if (count($categoryContests) == 1) {
         echo " rowspan=2 ";
      }
      echo ">".$mainContestsNames[$mainContestKey]."</td>";
   }
   echo "</tr><tr>";
   foreach ($contests as $mainContestKey => $categoryContests) {
      foreach ($categoryContests as $category => $contest) {
         if (count($categoryContests) > 1) {
            echo "<td>".$category."</td>";
         }
      }
   }
   echo "</tr>";

   usort($contestants, function ($a, $b) {
       $cmpGroups = strcmp($a["infos"]['bebrasGroup'], $b["infos"]['bebrasGroup']);
       if ($cmpGroups != 0) {
          return $cmpGroups;
       }
       $cmpNames = strcmp($a["infos"]['lastName'], $b["infos"]['lastName']);
       if ($cmpNames != 0) {
          return $cmpNames;
       };
       return strcmp($a["infos"]['firstName'], $b["infos"]['firstName']);
   });

   foreach ($contestants as $contestant) {
      echo "<tr>".
         "<td>".$contestant["infos"]["bebrasGroup"]."</td>".
         "<td>".$contestant["infos"]["firstName"]."</td>".
         "<td>".$contestant["infos"]["lastName"]."</td>".
         "<td>".$grades[$contestant["infos"]["grade"]]."</td>".
         "<td class='".$contestant["infos"]["qualifiedCategory"]."'>".$contestant["infos"]["qualifiedCategory"]."</td>";
      if ($showCodes) {
         echo "<td>".$contestant["infos"]["code"]."</td>";
      }
      foreach ($contests as $mainContestKey => $categoryContests) {
         foreach ($categoryContests as $category => $contestKey) {
            if (isset($contestant["results"][$contestKey])) {
               $result = $contestant["results"][$contestKey];
               $rankInfos = "classement en attente";
               if ($result["rank"] != '') {
                  $rankGroup = $grades[$contestant["infos"]["grade"]]." ";
                  if ($result["nbContestants"] == "1") {
                     $rankGroup .= "individuels";
                  } else {
                     $rankGroup .= "binômes";
                  }
                  $rankInfos = $result["rank"]."e des ".$rankGroup;
               } else if ($result["participationType"] == "Unofficial") {
                  $rankInfos = "Hors concours";
               }
               echo "<td class='".$category."'>".
                  $result["score"]."<br/>".
                  "<span class='rank'>".$rankInfos."</span>".
                  "</td>";
            } else {
               echo "<td class='grise'>-</td>";
            }
         }
      }
      echo "</tr>";
   }

   echo "</table>";
}

?>
</body>
</html>