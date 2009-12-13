<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Walid Nouh
// Purpose of file:
// ----------------------------------------------------------------------
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// OCS Rules class
class RuleOcs extends Rule {

   // From Rule
   public $sub_type = RULE_OCS_AFFECT_COMPUTER;
   public $right='rule_ocs';
   public $can_sort=true;

   static function canCreate() {
      return haveRight('rule_ocs', 'w');
   }

   static function canView() {
      return haveRight('rule_ocs', 'r');
   }

   function getTitle() {
      global $LANG;

      return $LANG['rulesengine'][18];
   }

   function maxActionsCount() {
      // Unlimited
      return 1;
   }

   /**
    * Display form to add rules
    * @param $target
    * @param $ID
    */
   function showAndAddRuleForm($target, $ID) {
      global $LANG, $CFG_GLPI;

      $canedit = haveRight($this->right, "w");

      echo "<form name='entityaffectation_form' id='entityaffectation_form' method='post' ".
            "action=\"$target\">";

      if ($canedit) {
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='2'>" . $LANG['rulesengine'][18] . "</th></tr>\n";
         echo "<tr class='tab_bg_1'>";
         echo "<td>".$LANG['common'][16] . "&nbsp;:&nbsp;";
         autocompletionTextField("name", "glpi_rules", "name", "", 33);
         echo "&nbsp;&nbsp;&nbsp;".$LANG['joblist'][6] . "&nbsp;:&nbsp;";
         autocompletionTextField("description", "glpi_rules", "description", "", 33);
         echo "&nbsp;&nbsp;&nbsp;".$LANG['rulesengine'][9] . "&nbsp;:&nbsp;";
         $this->dropdownRulesMatch("match", "AND");
         echo "</td><td class='tab_bg_2 center'>";
         echo "<input type=hidden name='sub_type' value='" . $this->sub_type . "'>";
         echo "<input type=hidden name='entities_id' value='-1'>";
         echo "<input type=hidden name='affectentity' value='$ID'>";
         echo "<input type='submit' name='add_rule' value=\"" . $LANG['buttons'][8] .
                "\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table><br>";
      }

      //Get all rules and actions
      $rules = $this->getRulesForEntity( $ID, 0, 1);
      if (empty ($rules)) {
         echo "<table class='tab_cadre_fixehov'>";
         echo "<tr><th>" . $LANG['entity'][5] . " - " . $LANG['search'][15] . "</th></tr>\n";
         echo "</table><br>\n";
      } else {
         echo "<table class='tab_cadre_fixehov'>";
         echo "<tr><th colspan='3'>" . $LANG['entity'][5] . "</th></tr>\n";
         initNavigateListItems(RULE_TYPE, $LANG['entity'][0]."=".Dropdown::getDropdownName("glpi_entities",$ID),
                               $this->sub_type);

         foreach ($rules as $rule) {
            addToNavigateListItems(RULE_TYPE,$rule->fields["id"],$this->sub_type);
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
               echo "<td width='10'>";
               $sel = "";
               if (isset ($_GET["select"]) && $_GET["select"] == "all") {
                  $sel = "checked";
               }
               echo "<input type='checkbox' name='item[" . $rule->fields["id"] . "]' value='1' $sel>";
               echo "</td>";
            }
            if ($canedit) {
               echo "<td><a href=\"" . $CFG_GLPI["root_doc"] . "/front/ruleocs.form.php?id=" .
                      $rule->fields["id"] . "&amp;onglet=1\">" . $rule->fields["name"] . "</a></td>";
            } else {
               echo "<td>" . $rule->fields["name"] . "</td>";
            }
            echo "<td>" . $rule->fields["description"] . "</td>";
            echo "</tr>\n";
         }
         echo "</table><br>\n";
         if ($canedit) {
            openArrowMassive("entityaffectation_form", true);
            closeArrowMassive('delete_computer_rule', $LANG['buttons'][6]);
         }
      }

      echo "</form>";
   }

   /**
    * Return all rules from database
    * @param $ID of rules => @param $ID of entity
    * @param withcriterias import rules criterias too
    * @param withactions import rules actions too
    */
   function getRulesForEntity($ID, $withcriterias, $withactions) {
      global $DB;

      $ocs_affect_computer_rules = array ();

      //Get all the rules whose sub_type is $sub_type and entity is $ID
      $sql = "SELECT `glpi_rules`.`id`
              FROM `glpi_ruleactions`, `glpi_rules`
              WHERE `glpi_ruleactions`.`rules_id` = `glpi_rules`.`id`
                    AND `glpi_ruleactions`.`field` = 'entities_id'
                    AND `glpi_rules`.`sub_type` = '".$this->sub_type."'
                    AND `glpi_ruleactions`.`value` = '$ID'";

      $result = $DB->query($sql);
      while ($rule = $DB->fetch_array($result)) {
         $affect_rule = new Rule;
         $affect_rule->getRuleWithCriteriasAndActions($rule["id"], 0, 1);
         $ocs_affect_computer_rules[] = $affect_rule;
      }
      return $ocs_affect_computer_rules;
   }

   function preProcessPreviewResults($output) {
      return $output;
   }

   function executeActions($output,$params,$regex_results) {

      if (count($this->actions)) {
         foreach ($this->actions as $action) {
            switch ($action->fields["action_type"]) {
               case "assign" :
                  $output[$action->fields["field"]] = $action->fields["value"];
                  break;

               case "regex_result" :
                  //Assign entity using the regex's result
                  if ($action->fields["field"] == "_affect_entity_by_tag") {
                     //Get the TAG from the regex's results
                     $res = getRegexResultById($action->fields["value"],$regex_results);
                     if ($res != null) {
                        //Get the entity associated with the TAG
                        $target_entity = EntityData::getEntityIDByTag($res);
                        if ($target_entity != '') {
                           $output["entities_id"]=$target_entity;
                        }
                     }
                  }
                  break;
            }
         }
      }
      return $output;
   }

}

?>
