<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Mreporting plugin for GLPI
 Copyright (C) 2003-2011 by the mreporting Development Team.

 https://forge.indepnet.net/projects/mreporting
 -------------------------------------------------------------------------

 LICENSE

 This file is part of mreporting.

 mreporting is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 mreporting is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with mreporting. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */
 
class PluginMreportingCommon extends CommonDBTM {
   
   /**
    * Parsing all classes
    * Search all class into inc folder
    * @params
   */
   
   static function parseAllClasses($inc_dir) {
      
      $classes = array();
      
      $matches = array();

      if ($handle = opendir($inc_dir)) {
         while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
               $fcontent = file_get_contents($inc_dir."/".$entry);
               if (preg_match("/class\s(.+)Extends PluginMreportingBaseclass/i", $fcontent, $matches)) {
                  $classes[] = trim($matches[1]);
               }
            }
         }
      }
      
      return $classes;
   }
      
   /**
    * Get all reports from parsing class
    *
    * @params
   */
   
   function getAllReports($with_url = true, $params=array()) {
      global $LANG;

      $reports = array();
      
      $inc_dir = GLPI_ROOT."/plugins/mreporting/inc";
      $front_dir = GLPI_ROOT."/plugins/mreporting/front";
      $pics_dir = GLPI_ROOT."/plugins/mreporting/pics";
      
      //parse inc dir to search report classes
      $classes = self::parseAllClasses($inc_dir);
      
      
      if (isset($params['classname']) 
            && !empty($params['classname'])) {
         $classes = array();
         $classes[] = $params['classname'];
         
      }

      //construct array to list classes and functions
      foreach($classes as $classname) {
         $i = 0;
         $short_classname = str_replace('PluginMreporting', '', $classname);
         if (isset($LANG['plugin_mreporting'][$short_classname]['title'])) {
            $title = $LANG['plugin_mreporting'][$short_classname]['title'];
            
            $functions = get_class_methods($classname);

            foreach($functions as $f_name) {
               $ex_func = preg_split('/(?<=\\w)(?=[A-Z])/', $f_name);
               if ($ex_func[0] != 'report') continue;

               $gtype      = strtolower($ex_func[1]);
               $title_func = $LANG['plugin_mreporting'][$short_classname][$f_name]['title'];
               $category_func = '';
               if (isset($LANG['plugin_mreporting'][$short_classname][$f_name]['category']))
                  $category_func = $LANG['plugin_mreporting'][$short_classname][$f_name]['category'];
               $_SESSION['glpi_plugin_mreporting_rand'][$short_classname][$f_name]=$classname.$i;
         
               $rand = $_SESSION['glpi_plugin_mreporting_rand'][$short_classname][$f_name];
               
               $url_graph  = $front_dir."/graph.php?short_classname=$short_classname&amp;f_name=$f_name&amp;gtype=$gtype&amp;rand=$rand";
               $min_url_graph  = "front/graph.php?short_classname=$short_classname&amp;f_name=$f_name&amp;gtype=$gtype&amp;rand=$rand";
               
               $reports[$classname]['title'] = $title;
               $reports[$classname]['functions'][$i]['function'] = $f_name;
               $reports[$classname]['functions'][$i]['title'] = $title_func;
               $reports[$classname]['functions'][$i]['category_func'] = $category_func;
               $reports[$classname]['functions'][$i]['pic'] = $pics_dir."/chart-$gtype.png";
               $reports[$classname]['functions'][$i]['gtype'] = $gtype;
               $reports[$classname]['functions'][$i]['short_classname'] = $short_classname;
               $reports[$classname]['functions'][$i]['rand'] = $rand;
               $reports[$classname]['functions'][$i]['is_active'] = false;
               $config = new PluginMreportingConfig();
               if ($config->getFromDBByRand($rand)) {
                  if ($config->fields['is_active'] == 1) {
                     $reports[$classname]['functions'][$i]['is_active'] = true;
                  }
               }
               if ($with_url) {
                  $reports[$classname]['functions'][$i]['url_graph'] = $url_graph;
                  $reports[$classname]['functions'][$i]['min_url_graph'] = $min_url_graph;
               }

               $i++;
            }
         }
      }

      return $reports;
   }
   
   /**
    * Show list of activated reports
    *
    * @param array $opt : short_classname,f_name,gtype,rand
   */
    
   static function title($opt) {
      global $LANG;
      
      $self = new self();
      
      $params['classname'] = 'PluginMreporting'.$opt['short_classname'];
      $reports = $self->getAllReports(true,$params);
      
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2'>".$LANG['stats'][0]."&nbsp;:</th></tr>";
      echo "<tr class='tab_bg_1'><td class='center'>";
      echo "<select name='graphmenu' onchange='window.location.href=this.options
    [this.selectedIndex].value'>";
      echo "<option value='-1' selected>".Dropdown::EMPTY_VALUE."</option>";
      
      $i = 0;
      
      foreach($reports as $classname => $report) {
         
         foreach($report['functions'] as $function) {
            if ($function['is_active']) {
               $graphs[$function['category_func']][] = $function;
            }
         }
         
         echo "<optgroup label=\"". $report['title'] ."\">";
         
         foreach($graphs as $cat => $graph) {
            
            echo "<optgroup label=\"". $cat ."\">";
            
            foreach($graph as $k => $v) {
               
               if ($v['is_active']) {
                  $comment = "";
                  if (isset($v["desc"]))
                     $comment = $v["desc"];
                  
                  echo "<option value='".$v["url_graph"]."' title=\"".
                                    Html::cleanInputText($comment)."\">".$v["title"]."</option>";
                  $i++;
               }
            }
            echo "</optgroup>";

         }
         echo "</optgroup>";

      }

      echo "</select>";
      echo "</td>";
      echo "</tr>";
      echo "</table>";
   }
   
   /**
    * parse All class for list active reports
    * and display list
   */
   
   function showCentral($params)  {
      global $LANG, $CFG_GLPI;

      $reports = $this->getAllReports(true, $params);
      if ($reports === false) {
         echo "<div class='center'>".$LANG['plugin_mreporting']["error"][0]."</div>";
         return false;
      }

      echo "<table class='tab_cadre_fixe' id='mreporting_functions'>";

      foreach($reports as $classname => $report) {

         $i = 0;
         $nb_per_line = 2;
         $graphs = array();
         foreach($report['functions'] as $function) {
            
            if ($function['is_active']) {
               $graphs[$classname][$function['category_func']][] = $function;
            }
         }
         
         if (isset($graphs[$classname])) {
            foreach($graphs[$classname] as $cat => $graph) {
               
               echo "<tr class='tab_bg_1'><th colspan='4'>".$cat."</th></tr>";
               
               foreach($graph as $k => $v) {
                  
                  if ($v['is_active']) {
                     if ($i%$nb_per_line == 0) {
                        if ($i != 0) {
                           echo "</tr>";
                        }
                        echo "<tr class='tab_bg_1' valign='top'>";
                     }

                     echo "<td>";
                     echo "<a href='".$v['url_graph']."'>";
                     echo "<img src='".$v['pic']."' />&nbsp;";
                     echo $v['title'];
                     echo "</a></td>";
                     $i++;
                  }
               }
               
               while ($i%$nb_per_line != 0) {
                  echo "<td>&nbsp;</td>";
                  $i++;
               }
            }
         }
         echo "</tr>";
         
         if (isset($graphs[$classname]) && count($graphs[$classname])>0) {
            $height = 200;
            $height += 50*count($graphs[$classname]);
            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='2'>";
            echo "<div class='right'>";
            echo $LANG['buttons'][31]." : ";
            echo "<a href='#' onClick=\"var w = window.open('".$CFG_GLPI["root_doc"].
                  "/plugins/mreporting/front/popup.php?classname=$classname' ,'glpipopup', ".
                  "'height=$height, width=1000, top=100, left=100, scrollbars=yes'); w.focus();\">";
            echo "ODT";
            echo "</a></div>";
            echo "</th>";
            echo "</tr>";
         } else {
            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='2'>";
            echo $LANG['plugin_mreporting']["error"][0];
            echo "</th>";
            echo "</tr>";
         }
      }
      

      echo "</table>";
      
      if (DEBUG_MREPORTING) $this->debugGraph();
   }
   
   /**
    * show Export Form from popup.php
    * for odt export
   */
   
   function showExportForm($opt) {
      global $LANG;
      
      $classname = $opt["classname"];
      if ($classname) {
         echo "<div align='center'>";

         echo "<form method='POST' action='export.php?switchto=odtall&classname=".$classname."' 
                     id='exportform' name='exportform'>\n";
         
         echo "<table class='tab_cadre_fixe'>";
         
         echo "<tr><th colspan='4'>";
         echo $LANG['plugin_mreporting']["export"][0];
         echo "</th></tr>";
         
         $reports = $this->getAllReports(false, $opt);
         
         foreach($reports as $class => $report) {

            $i = 0;
            $nb_per_line = 2;
            $graphs = array();
            foreach($report['functions'] as $function) {
               if ($function['is_active']) {
                  $graphs[$classname][$function['category_func']][] = $function;
               }
            }
            
            foreach($graphs[$classname] as $cat => $graph) {
            
               echo "<tr class='tab_bg_1'><th colspan='4'>".$cat."</th></tr>";
               
               foreach($graph as $k => $v) {
                  
                  if ($v['is_active']) {
                     if ($i%$nb_per_line == 0) {
                        if ($i != 0) {
                           echo "</tr>";
                        }
                        echo "<tr class='tab_bg_1'>";
                     }
                     
                     echo "<td>";
                     echo "<input type='checkbox' name='check[" . $v['rand'] . "]'";
                     if (isset($_POST['check']) && $_POST['check'] == 'all')
                        echo " checked ";
                     echo ">";
                     echo "</td>";
                     echo "<td>";
                     echo "<img src='".$v['pic']."' />&nbsp;";
                     echo $v['title'];
                     echo "</td>";
                     $i++;
                  }
               }
            
               while ($i%$nb_per_line != 0) {
                  echo "<td width='10'>&nbsp;</td>";
                  echo "<td>&nbsp;</td>";
                  $i++;
               }
            }
            echo "</tr>";
         }

         echo "<tr class='tab_bg_2'>";
         echo "<td colspan ='4' class='center'>";
         echo "<div align='center'>";
         echo "<table><tr class='tab_bg_2'>";
         echo "<td>";
         echo $LANG['search'][8];
         echo "</td>";
         echo "<td>";
         $date1 =  strftime("%Y-%m-%d", time() - (30 * 24 * 60 * 60));
         Html::showDateFormItem("date1",$date1,true);
         echo "</td>";
         echo "<td>";
         echo $LANG['search'][9];
         echo "</td>";
         echo "<td>";
         $date2 =  strftime("%Y-%m-%d");
         Html::showDateFormItem("date2",$date2,true);
         echo "</td>";
         echo "</tr>";
         echo "</table>";
         echo "</div>";
         
         echo "</td>";
         echo "</tr>";
         
         echo "</table>";
         Html::openArrowMassives("exportform", true);
         Html::closeArrowMassives(array('submit' => $LANG['buttons'][31]));
         echo "</form></div>";
      }
   }
   
   /**
    * exit from grpah if no @params detected
    *
    * @params
   */
   
   function initParams($params, $export = false) {
      if(!isset($params['classname'])) {
         if (!isset($params['short_classname'])) exit;
         if (!isset($params['f_name'])) exit;
         if (!isset($params['gtype'])) exit;
      }
      
      return $params;
   }
   
   /**
    * init Params for graph function
    *
    * @params
   */
   
   static function initGraphParams($params) {

      $crit        = array();
      
      // Default values of parameters
      $raw_datas   = array();
      $title       = "";
      $desc        = "";
      
      $export      = false;
      $opt         = array();

      foreach ($params as $key => $val) {
         $crit[$key]=$val;
      }

      return $crit;
   }
   
   /**
    * show Graph : Show graph
    *
    * @params $options ($opt, export)
    * @params $opt (classname, short_classname, f_name, gtype)
   */
   
   function showGraph($opt, $export = false) {
      global $LANG, $CFG_GLPI;
      
      PluginMreportingCommon::title($opt);
      
      //check the format display charts configured in glpi
      $opt = $this->initParams($opt, $export);
      
      if ($CFG_GLPI['default_graphtype'] == 'png') $graph = new PluginMreportingGraphpng();
      else $graph = new PluginMreportingGraph();

      //dynamic instanciation of class passed by 'short_classname' GET parameter
      $classname = 'PluginMreporting'.$opt['short_classname'];
      $obj = new $classname();

      //dynamic call of method passed by 'f_name' GET parameter with previously instancied class
      $datas = $obj->$opt['f_name']();
      
      //show graph (pgrah type determined by first entry of explode of camelcase of function name
      $title_func = $LANG['plugin_mreporting'][$opt['short_classname']][$opt['f_name']]['title'];
      $desc_func = "";
      if (isset($LANG['plugin_mreporting'][$opt['short_classname']][$opt['f_name']]['desc']))
        $desc_func = $LANG['plugin_mreporting'][$opt['short_classname']][$opt['f_name']]['desc'];
      
      $params = array("raw_datas"   => $datas,
                       "title"      => $title_func,
                       "desc"       => $desc_func,
                       "export"     => $export,
                       "opt"        => $opt);
                       
      $graph->{'show'.$opt['gtype']}($params);

   }
   
   /**
    * end Graph : Show graph datas array, setup link, export
    *
    * @params $options ($opt, export, datas, unit, labels2, flip_data)
   */

   static function endGraph($options) {
      global $LANG, $CFG_GLPI;
      
      $opt        = array();
      $export     = false;
      $datas      = array();
      $unit       = '';
      $labels2    =  array();
      $flip_data  = false;
      
      foreach ($options as $k => $v) {
         $$k=$v;
      }
      
      
      $rand = false;
      if (isset($opt['rand'])) {
			
			$rand = $opt['rand'];
			$_REQUEST['short_classname'] = $opt['short_classname'];
			$_REQUEST['f_name'] = $opt['f_name'];
			$_REQUEST['gtype'] = $opt['gtype'];
			$_REQUEST['rand'] = $opt['rand'];
			
			//End Script for graph display
			//if $rand exists
			if (!$export && $CFG_GLPI['default_graphtype'] == 'svg') {

				echo "}
					showGraph$rand();
				</script>";
				echo "</div>";

			}
      }
      $request_string = PluginMreportingMisc::getRequestString($_REQUEST);
      
		if ($rand !== false && !$export) {
			
			PluginMreportingCommon::showGraphDatas($datas, $unit, $labels2, $flip_data);
			
			if ($_REQUEST['f_name'] != "test") {
				echo "<div class='graph_bottom'>";
				echo "<span style='float:left'>";
				PluginMreportingMisc::showNavigation();
				echo "</span>";
				echo "<span style='float:right'>";
				echo "<b>".$LANG['plugin_mreporting']["config"][0]."</b> : ";
				echo "&nbsp;<a href='config.form.php?rand=".$rand."' target='_blank'>";
				echo "<img src='../pics/config.png' class='title_pics'/></a>- ";
				echo "<b>".$LANG['buttons'][31]."</b> : ";
				echo "&nbsp;<a target='_blank' href='export.php?switchto=csv&$request_string'>CSV</a> /";
				echo "&nbsp;<a target='_blank' href='export.php?switchto=png&$request_string'>PNG</a> /";
				echo "&nbsp;<a target='_blank' href='export.php?switchto=odt&$request_string'>ODT</a>";
				
				echo "</span>";
			}
			echo "<div style='clear:both;'></div>";
			echo "</div>";
			echo "</div></div>";
		}
		
		if ($rand == false) {
			echo "</div>";
		}
      //destroy specific palette
      unset($_SESSION['mreporting']['colors']);

   }
   
   /**
    * Compile datas for unit display
    *
    * @param $datas, ex : array( 'test1' => 15, 'test2' => 25)
    * @param $unit, ex : '%', 'Kg' (optionnal)
    * @if percent, return new datas
    */
    
   static function compileDatasForUnit($values, $unit = '') {
      
      if ($unit == '%') {
         //complie news datas with percent values
         $calcul = array();
         $datas = $values;
         foreach ($datas as $k=>$v) {
            //multiple array
            if (is_array($v)) {
               foreach($v as $key => $val) {
                  $total = array_sum($v);
                  $calcul[$k][$key]= Html::formatNumber(($val*100)/$total);
               }
            //simple array
            } else {
               $total = array_sum($values);
               $calcul[$k]= Html::formatNumber(($v*100)/$total);
               
            }
            $datas = $calcul;
         }
         
      }

      return $datas;
   }
   
   /**
    * show Graph datas
    *
    * @param $datas, ex : array( 'test1' => 15, 'test2' => 25)
    * @param $unit, ex : '%', 'Kg' (optionnal)
    * @param $labels2, ex : dates
    * @param $flip_data, flip array if necessary
   */
    
   static function showGraphDatas ($datas=array(), $unit = '', $labels2=array(), $flip_data = false) {
      global $LANG, $CFG_GLPI;
      
      $simpledatas = false;
        
      //simple array
      if (!$labels2) {
         $labels2 = array();
         $simpledatas = true;
      }
      
      if ($flip_data == true) {
         $labels2 = array_flip($labels2);
      }
         
      $types = array();
   
      foreach($datas as $k => $v) {
         
         if (is_array($v)) {
            foreach($v as $key => $val) {
               if (isset($labels2[$key]))
                  $types[$key][$k] = $val;
            }
         }
      }
      
      if ($flip_data != true) {
         $tmp = $datas;
         $datas = $types;
         $types = $tmp;
      }
      //simple array
      if ($simpledatas) {
         $datas = array($LANG['plugin_mreporting']["export"][1] => 0);
      }
      
      echo "<br><table class='tab_cadre' width='90%'>";
      echo "<tr class='tab_bg_1'><th>";
      echo "<a href=\"javascript:showHideDiv('view_datas','viewimg','".
      $CFG_GLPI["root_doc"]."/pics/deplier_down.png','".
      $CFG_GLPI["root_doc"]."/pics/deplier_up.png');\">";
      echo "<img alt='' name='viewimg' src=\"".
      $CFG_GLPI["root_doc"]."/pics/deplier_down.png\">&nbsp;";
      echo $LANG['plugin_mreporting']["export"][2];
      echo "</a>";
      echo "</th>";
      echo "</tr>";
      echo "</table>";
         
      echo "<div align='center' style='display:none;' id='view_datas'>";
      echo "<table class='tab_cadre' width='90%'>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<th></th>";
      
      foreach($datas as $label => $cols) {
         echo "<th>".$label."</th>";
      }
      echo "</tr>";
      foreach($types as $label2 => $cols) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".$label2."</td>";
         //simple array
         if ($simpledatas) {
            echo "<td class='center'>".$cols." ".$unit."</td>";
         //multiple array
         } else {
            foreach($cols as $date => $nb) {
               echo "<td class='center'>".$nb." ".$unit."</td>";
            }
         }
         echo "</tr>";
      }
      
      echo "</table>";
      echo "</div><br>";
   }
   
   /**
    * Launch export of datas
    *
    * @param $opt
   */
   
   function export($opt)  {
      global $LANG;
      
      switch ($opt['switchto']) {
         default:
         case 'png':
            $graph = new PluginMreportingGraphpng();
            //check the format display charts configured in glpi
            $opt = $this->initParams($opt, true);
            $opt['export'] = 'png';
            break;
         case 'csv':
            $graph = new PluginMreportingGraphcsv();
            $opt['export'] = 'csv';
            break;
         case 'odt':
            $graph = new PluginMreportingGraphpng();
            $opt = $this->initParams($opt, true);
            $opt['export'] = 'odt';
            break;
         case 'odtall':
            $graph = new PluginMreportingGraphpng();
            $opt = $this->initParams($opt, true);
            $opt['export'] = 'odtall';
            break;
      }
      
      //export all with odt
      if (isset($opt['classname'])) {
         
         if (isset($opt['check'])) {
         
            unset($_SESSION['glpi_plugin_mreporting_odtarray']);
    
            $reports = $this->getAllReports(false, $opt);

            foreach($reports as $classname => $report) {
               foreach($report['functions'] as $function) {
                  
                  foreach ($opt['check'] as $do=>$to) {
                     
                     if ($do == $function['rand']) {
                        //dynamic instanciation of class passed by 'short_classname' GET parameter
                        $class = 'PluginMreporting'.$function['short_classname'];
                        $obj = new $class();
                        
                        if (isset($opt['date1']) && isset($opt['date2'])) {
                           
                           $s = strtotime($opt['date2'])-strtotime($opt['date1']); 
                           
                           $_REQUEST['date1'.$function['rand']] = $opt['date1'];
                           $_REQUEST['date2'.$function['rand']] = $opt['date2'];
                        }
                        
                        
                        //dynamic call of method passed by 'f_name' GET parameter with previously instancied class
                        $datas = $obj->$function['function']();
                        
                        //show graph (pgrah type determined by first entry of explode of camelcase of function name
                        $title_func = $LANG['plugin_mreporting'][$function['short_classname']][$function['function']]['title'];

                        $desc_func = "";
                        if (isset($LANG['plugin_mreporting'][$function['short_classname']][$function['function']]['desc'])) {
                          $desc_func = $LANG['plugin_mreporting'][$function['short_classname']][$function['function']]['desc'];
                        } else if (isset($opt['date1']) && isset($opt['date2'])) {
                           $desc_func = Html::convdate($opt['date1'])." / ".Html::convdate($opt['date2']);
                        }
                        $options = array("short_classname" => $function['short_classname'],
                                    "f_name" => $function['function'],
                                    "gtype" => $function['gtype'],
                                    "rand" => $function['rand']); 
                        
                        $show_label = 'always';
               
                        $params = array("raw_datas"  => $datas,
                                         "title"      => $title_func,
                                         "desc"       => $desc_func,
                                         "export"     => $opt['export'],
                                         "opt"        => $options);
                                         
                        $graph->{'show'.$function['gtype']}($params);
                     }
                  }
               }
            }
            if (isset($_SESSION['glpi_plugin_mreporting_odtarray']) &&
                  !empty($_SESSION['glpi_plugin_mreporting_odtarray'])) {
               $this->generateOdt($_SESSION['glpi_plugin_mreporting_odtarray']);
            }
         //no selected data
         } else {
            Html::popHeader($LANG['plugin_mreporting']["export"][0], $_SERVER['PHP_SELF']);
            echo "<div class='center'><br>".$LANG['plugin_mreporting']["error"][3]."<br><br>";
            Html::displayBackLink();
            echo "</div>";
            Html::popFooter();
         }
         
      } else {
         //dynamic instanciation of class passed by 'short_classname' GET parameter
         $classname = 'PluginMreporting'.$opt['short_classname'];
         $obj = new $classname();

         //dynamic call of method passed by 'f_name' GET parameter with previously instancied class
         $datas = $obj->$opt['f_name']();

         //show graph (pgrah type determined by first entry of explode of camelcase of function name
         $title_func = $LANG['plugin_mreporting'][$opt['short_classname']][$opt['f_name']]['title'];
         $desc_func = "";
         if (isset($LANG['plugin_mreporting'][$opt['short_classname']][$opt['f_name']]['desc'])) {
           $desc_func = $LANG['plugin_mreporting'][$opt['short_classname']][$opt['f_name']]['desc'];
         } else if (isset($_REQUEST['date1'.$opt['rand']]) && isset($_REQUEST['date2'.$opt['rand']])) {
            $desc_func = Html::convdate($_REQUEST['date1'.$opt['rand']]).
                        " / ".Html::convdate($_REQUEST['date2'.$opt['rand']]);
         }
         
         $show_label = 'always';
               
         $params = array("raw_datas"  => $datas,
                          "title"      => $title_func,
                          "desc"       => $desc_func,
                          "export"     => $opt['export'],
                          "opt"        => $opt);
                
         $graph->{'show'.$opt['gtype']}($params);
      }
   }
   
   function generateOdt($params) {
      global $LANG;
      
      $config = array('PATH_TO_TMP' => GLPI_DOC_DIR . '/_tmp');
      $template = "../templates/label2.odt";
      
      $odf = new odf($template, $config);
      
      $reports = $this->getAllReports();
      foreach($reports as $classname => $report) {
         $titre = $report['title'];
      }
      
      $odf->setVars('titre', $titre, true, 'UTF-8');
      
      $newpage = $odf->setSegment('newpage');
      
      foreach ($params as $result => $page) {
         
         // Default values of parameters
         $title       = "";
         $f_name      = "";
         $raw_datas   = array();

         foreach ($page as $key => $val) {
            $$key=$val;
         }

         $datas = $raw_datas['datas'];
         
         $labels2 = array();
         if (isset($raw_datas['labels2'])) {
            $labels2 = $raw_datas['labels2'];
         }
         
         $configs = PluginMreportingConfig::initConfigParams($rand);
      
         foreach ($configs as $k => $v) {
            $$k=$v;
         }
         
         if ($unit == '%') {
         
            $datas = PluginMreportingCommon::compileDatasForUnit($datas, $unit);
         }

         $newpage->setVars('message', $title, true, 'UTF-8');
         
         $path = GLPI_PLUGIN_DOC_DIR."/mreporting/".$f_name.".png";
         
         $newpage->setImage('image', $path);
         
         $simpledatas = false;
         
         //simple array
         if (!$labels2) {
            $labels2 = array();
            $simpledatas = true;
         }
         
         if ($flip_data == true) {
            $labels2 = array_flip($labels2);
         }
            
         $types = array();
      
         foreach($datas as $k => $v) {
            
            if (is_array($v)) {
               foreach($v as $key => $val) {
                  if (isset($labels2[$key]))
                     $types[$key][$k] = $val;
               }
            }
         }
         
         if ($flip_data != true) {
            $tmp = $datas;
            $datas = $types;
            $types = $tmp;
         }
         //simple array       
         if ($simpledatas) {
            
            $label = $LANG['plugin_mreporting']["export"][1];
            $newpage->csvdata->data0->label_0(utf8_decode($label));
            $newpage->csvdata->data0->merge();
            
            foreach($types as $label2 => $cols) {

               $newpage->csvdata->label1->label_1(utf8_decode($label2));
               $newpage->csvdata->label1->merge();
               if (!empty($unit)) {
                  $cols = $cols." ".$unit;
               }
               $newpage->csvdata->data1->data_1($cols);
               $newpage->csvdata->merge();
            }
            
         } else {
            
            foreach($datas as $label => $val) {
               $newpage->csvdata->data0->label_0(utf8_decode($label));
               $newpage->csvdata->data0->merge();
            }
               
            foreach($types as $label2 => $cols) {

               $newpage->csvdata->label1->label_1(utf8_decode($label2));
               $newpage->csvdata->label1->merge();
               foreach($cols as $date => $nb) {
                  if (!empty($unit)) {
                     $nb = $nb." ".$unit;
                  }
                  $newpage->csvdata->data1->data_1($nb);
                  $newpage->csvdata->data1->merge();
               }
               
               $newpage->csvdata->merge();
            }
         }
         $newpage->merge();

      }
      $odf->mergeSegment($newpage);
      // We export the file
      $odf->exportAsAttachedFile();
      unset($_SESSION['glpi_plugin_mreporting_odtarray']);
   }
   
   function debugGraph() {

      echo "<h1 style='color:red;'>DEBUG</h1>";

      $params = array(
         'short_classname' => "test",
         'f_name' => "test",
         'gtype' => "test"
      );

      $params = $this->initParams($params);
      $graph = new PluginMreportingGraph();
      $graphpng = new PluginMreportingGraphpng();

      $datas1 = array(
         'datas' => array(
            "pommes" => 25,
            "poires" => 52,
            "fraises" => 23,
            "pêches" => 10
         ),
         'unit' => 'Kg'
      );

      $datas2 = array(
         "datas" => array(
            "Paris" => array(12, 84, 65, 31),
            "Bordeaux" => array(84, 72, 18, 23),
            "Lille" => array(54, 81, 25, 26)
         ),
         "labels2" => array("pommes", "poires", "fraises", "pêches")
      );

      $datas3 = array(
         "datas" => array(
            "Jan" => 15, "Fev" => 20, "Mar" => 21, "Avr" => 16,
            "Mai" => 8, "Jun" => 14, "Jui" => 3, "Aou" => 5,
            "Sep" => 9, "Oct" => 11, "Nov" => 21, "Dec" => 30/*,
            "Jan2" => 15, "Fev2" => 20, "Mar2" => 21, "Avr2" => 16,
            "Mai2" => 8, "Jun2" => 14, "Jui2" => 3, "Aou2" => 5,
            "Sep2" => 9, "Oct2" => 11, "Nov2" => 21, "Dec2" => 30,
            "Jan3" => 15, "Fev3" => 20, "Mar3" => 21, "Avr3" => 16,
            "Mai3" => 8, "Jun3" => 14, "Jui3" => 3, "Aou3" => 5,
            "Sep3" => 9, "Oct3" => 11, "Nov3" => 21, "Dec3" => 30,
            "Jan4" => 15, "Fev4" => 20, "Mar4" => 21, "Avr4" => 16,
            "Mai4" => 8, "Jun4" => 14, "Jui4" => 3, "Aou4" => 5,
            "Sep4" => 9, "Oct4" => 11, "Nov4" => 21, "Dec4" => 30*/
         ),
         'unit'   => 'ticket',
         'spline' => true
      );

      $datas4 = array(
         "datas" => array(
            "New"    => array(15, 20, 21, 16, 8, 14, 3, 5, 9, 11, 21, 30),
            "Attrib" => array(9, 21, 13, 13, 2, 5, 6, 15, 8, 10, 4, 21),
            "Solved" => array(15, 19, 18, 16, 5, 7, 8, 14, 6, 7, 14, 18),
            "Closed" => array(8, 16, 19, 15, 7, 9, 4, 9, 10, 15, 13, 15)
         ),
         "labels2"   => array("Jan", "Fev", "Mar", "Avr", "Mai", "Jun",
                            "Jui", "Aou","Sep", "Oct", "Nov", "Dec"),
         "spline"    => true
      );
      
      $rand = mt_rand();

      $opt = array("rand" => $rand);
      $opt = array_merge($params, $opt);
      
      $params1['raw_datas'] = $datas1;
      $params1['title'] = 'Exemple 1';
      $params1['desc'] = 'Graphique en barres horizontales';
      $params1['show_label'] = 'none';
      $params1['export'] = false;
      $params1['opt'] = $opt;
      
      $graph->showHbar($params1);
      
      $params2['raw_datas'] = $datas1;
      $params2['title'] = 'Exemple 2';
      $params2['desc'] = 'Graphique en camembert';
      $params2['show_label'] = 'none';
      $params2['export'] = false;
      $params2['opt'] = $opt;
      
      $graph->showPie($params2);
      
      $params3['raw_datas'] = $datas2;
      $params3['title'] = 'Exemple 3';
      $params3['desc'] = 'Graphique en barres groupées horizontales';
      $params3['show_label'] = 'none';
      $params3['export'] = false;
      $params3['opt'] = $opt;
      
      $graph->showHgbar($params3);
      
      $params4['raw_datas'] = $datas3;
      $params4['title'] = 'Exemple 4';
      $params4['desc'] = 'Graphique en aires';
      $params4['show_label'] = 'none';
      $params4['export'] = false;
      $params4['area'] = true;
      $params4['opt'] = $opt;
      
      $graph->showArea($params4);
      
      $params5['raw_datas'] = $datas4;
      $params5['title'] = 'Exemple 5';
      $params5['desc'] = 'Graphique en lignes (multiples)';
      $params5['show_label'] = 'none';
      $params5['export'] = false;
      $params5['area'] = false;
      $params5['opt'] = $opt;
      
      $graph->showGArea($params5);
   }
}

?>