<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

require_once __DIR__  . '/Twinkly.class.php';

class kTwinkly extends eqLogic {
    /*     * *************************Attributs****************************** */
    private static $_eqLogics = null;

  /*
   * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
   * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
	public static $_widgetPossibility = array();
   */
    
    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {
      }
     */

    /*
     * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
      public static function cron5() {
      }
     */

    /*
     * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
      public static function cron10() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
      public static function cron15() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
      public static function cron30() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {
      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
     */
      public static function cronDaily() {
        try {
            if(date('i') == 0 && date('s') < 10) {
                sleep(10);
            }
            $plugin = plugin::byId(__CLASS__);
            $plugin->deamon_start(true);
        } catch (\Exception $e) {
            log::add('kTwinkly','debug','error in cronDaily : ' . $e->getMessage());
        }
      }



    /*     * *********************Méthodes d'instance************************* */
    
 // Fonction exécutée automatiquement avant la création de l'équipement 
    public function preInsert() {
        
    }

 // Fonction exécutée automatiquement après la création de l'équipement 
    public function postInsert() {
        
    }

 // Fonction exécutée automatiquement avant la mise à jour de l'équipement 
    public function preUpdate() {
	    $ip = $this->getConfiguration('ipaddress');
	    $mac = $this->getConfiguration('macaddress');

	    if ($ip == ''){
		   throw new Exception(__('L\'adresse IP ne peut être vide', __FILE__)); 
	    }

	    if(!filter_var($ip, FILTER_VALIDATE_IP)) {
		   throw new Exception(__('Le format de l\'adresse IP est incorrect', __FILE__)); 
	    }

	    if ($mac == ''){
		   throw new Exception(__('L\'adresse MAC ne peut être vide', __FILE__)); 
	    }

	    if(!filter_var($mac, FILTER_VALIDATE_MAC)) {
		   throw new Exception(__('Le format de l\'adresse MAC est incorrect', __FILE__)); 
	    }

	    try {
            	$t = new Twinkly($ip, $mac, FALSE);

            	$info = $t->get_details();
                $this->setConfiguration('product',$info["product_name"]);
                $this->setConfiguration('devicename',$info["device_name"]);
                $this->setConfiguration('numberleds',$info["number_of_led"]);
                $this->setConfiguration('ledtype',$info["led_profile"]);
                $this->setConfiguration('productversion',$info["product_version"]);
                $this->setConfiguration('hardwareversion',$info["hardware_version"]);
                $this->setConfiguration('productcode',$info["product_code"]);
                $this->setConfiguration('hardwareid',$info["hw_id"]);
		$version = $t->firmware_version();
                $this->setConfiguration('firmware',$version);

	    } catch (Exception $e) {
		    throw new Exception(__('Impossible de contacter le contrôleur Twinkly. Vérifiez les paramètres : ' . $e->getMessage(), __FILE__));
	    }

	    $this->setLogicalId("Twinkly-" . $ip);
    }

 // Fonction exécutée automatiquement après la mise à jour de l'équipement 
    public function postUpdate() {

    }

 // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement 
    public function preSave() {

    }

 // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement 
    public function postSave() {
	    $onCmd = $this->getCmd(null, "on");
	    if(!is_object($onCmd)) {
            $onCmd = new kTwinklyCmd();
            $onCmd->setName(__('On', __FILE__));
            $onCmd->setEqLogic_id($this->getId());
            $onCmd->setLogicalId('on');
            $onCmd->setType('action');
            $onCmd->setSubType('other');
            $onCmd->setIsVisible(1);
            $onCmd->setValue('on');
            $onCmd->setDisplay('icon','<i class="icon jeedom-lumiere-on"></i>');
            $onCmd->setOrder(0);
            $onCmd->save();
        }

        $offCmd = $this->getCmd(null, "off");
        if(!is_object($offCmd)) {
            $offCmd = new kTwinklyCmd();
        	$offCmd->setName(__('Off', __FILE__));
        	$offCmd->setEqLogic_id($this->getId());
        	$offCmd->setLogicalId('off');
        	$offCmd->setType('action');
        	$offCmd->setSubType('other');
        	$offCmd->setIsVisible(1);
        	$offCmd->setValue('off');
            $offCmd->setDisplay('icon','<i class="icon jeedom-lumiere-off"></i>');
        	$offCmd->setOrder(1);
        	$offCmd->save();
        }

        $brightnessCmd = $this->getCmd(null, "brightness");
        if(!is_object($brightnessCmd)) {
            $brightnessCmd = new kTwinklyCmd();
        	$brightnessCmd->setName(__('Luminosité', __FILE__));
        	$brightnessCmd->setEqLogic_id($this->getId());
        	$brightnessCmd->setLogicalId('brightness');
        	$brightnessCmd->setType('action');
        	$brightnessCmd->setSubType('slider');
            $brightnessCmd->setConfiguration('minValue','0');
            $brightnessCmd->setConfiguration('maxValue','100');
            $brightnessCmd->setConfiguration('lastCmdValue','100');
            $brightnessCmd->setIsVisible(1);
        	$brightnessCmd->setOrder(2);
        	$brightnessCmd->save();
        }

        $movieCmd = $this->getCmd(null, "movie");
        if(!is_object($movieCmd)) {
            $movieCmd = new kTwinklyCmd();
        	$movieCmd->setName(__('Animation', __FILE__));
        	$movieCmd->setEqLogic_id($this->getId());
        	$movieCmd->setLogicalId('movie');
        	$movieCmd->setType('action');
        	$movieCmd->setSubType('select');
            //$movieCmd->setConfiguration("listValue","");
        	$movieCmd->setIsVisible(1);
        	$movieCmd->setOrder(3);
        	$movieCmd->save();
        }

        $stateCmd = $this->getCmd(null, "state");
        if(!is_object($stateCmd)) {
            $stateCmd = new kTwinklyCmd();
            $stateCmd->setName(__('Etat', __FILE__));
            $stateCmd->setEqLogic_id($this->getId());
            $stateCmd->setLogicalId('state');
            $stateCmd->setType('info');
            $stateCmd->setSubType('string');
            $stateCmd->setIsVisible(1);
            $stateCmd->setOrder(4);
            $stateCmd->save();
        }

        if($this->getChanged()){
            self::deamon_start();
        }
    }

 // Fonction exécutée automatiquement avant la suppression de l'équipement 
    public function preRemove() {
	// Suppression des animations liées à cet équipement
	$animpath = __DIR__ . '/../../data/twinkly_' . $this->getId() . '_*';
	log::add('kTwinkly','debug','Suppression des animations liées à l\'équipement : ' . $animpath);
	array_map( "unlink", glob( $animpath ) );
    }

 // Fonction exécutée automatiquement après la suppression de l'équipement 
    public function postRemove() {

    }

    /*
     * Non obligatoire : permet de modifier l'affichage du widget (également utilisable par les commandes)
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire : permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire : permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    public static function discover()
    {
	    log::add('kTwinkly','debug','Démarrage de la recherche d\'équipements');
	    $devices = Twinkly::discover();
	    log::add('kTwinkly','debug','Equipements trouvés : ' . print_r($devices,TRUE));
	    foreach($devices as $d) {
		    $lID = "Twinkly-" . $d["ip"];
		    $eqLogic = self::byLogicalId($lID, 'kTwinkly');
		    if (!is_object($eqLogic)) {
			    log::add('kTwinkly','debug','Nouvel équipement trouvé : name=' . $d["name"] . '(' . $d["details"]["device_name"] . ') ip=' . $d["ip"] . ' mac=' . $d["mac"]); 
			    $eqLogic = new self();
			    $eqLogic->setLogicalId($lID);
			    $eqLogic->setName($d["details"]["device_name"]);
			    $eqLogic->setEqType_name('kTwinkly');
			    $eqLogic->setIsVisible(1);
			    $eqLogic->setIsEnable(1);
		    } else {
			    log::add('kTwinkly','debug','Equipement déjà existant : ' . $lID);
		    }
		    $eqLogic->setConfiguration('ipaddress', $d["ip"]);
		    $eqLogic->setConfiguration('macaddress', $d["mac"]);
            $eqLogic->setConfiguration('product',$d["details"]["product_name"]);
            $eqLogic->setConfiguration('devicename',$d["details"]["device_name"]);
            $eqLogic->setConfiguration('numberleds',$d["details"]["number_of_led"]);
            $eqLogic->setConfiguration('ledtype',$d["details"]["led_profile"]);
            $eqLogic->setConfiguration('productversion',$d["details"]["product_version"]);
            $eqLogic->setConfiguration('hardwareversion',$d["details"]["hardware_version"]);
            $eqLogic->setConfiguration('productcode',$d["details"]["product_code"]);
            $eqLogic->setConfiguration('hardwareid',$d["details"]["hw_id"]);
            $eqLogic->setConfiguration('firmware',$d["details"]["firmware_version"]);
		    $eqLogic->save();
	    }
    }

    public static function deamon_info() {
        $return = array("log" => "", "state" => "nok");
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (is_object($cron) && $cron->running()) {
            $return['state'] = 'ok';
        }
        $return['launchable'] = 'ok';
        log::add('kTwinkly','debug','kTwinkly deamon_info : ' . print_r($return, TRUE));
        return $return;
    }

    public static function deamon_stop() {
        log::add('kTwinkly','debug','kTwinkly deamon_stop');
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (!is_object($cron)) {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->halt();
    }

    public static function deamon_start() {
        log::add('kTwinkly','debug','kTwinkly deamon_start');
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (!is_object($cron)) {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->run();
    }

    public static function deamon_changeAutoMode($_mode) {
        log::add('kTwinkly','debug','kTwinkly deamon_changeAutoMode');
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (!is_object($cron)) {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->setEnable($_mode);
        $cron->save();
    }

    public static function refreshstate($_eqLogic_id = null) {
        if (self::$_eqLogics == null) {
            self::$_eqLogics = self::byType('kTwinkly');
        }
        foreach (self::$_eqLogics as &$eqLogic) {
            if ($_eqLogic_id != null && $_eqLogic_id != $eqLogic->getId()) {
                continue;
            }
            if($eqLogic->getIsEnable() == 0){
                $eqLogic->refresh();
            }
            if ($eqLogic->getLogicalId() == '' || $eqLogic->getIsEnable() == 0) {
                continue;
            }
            log::add('kTwinkly','debug','refreshstate = refresh ' . $eqLogic->getLogicalId());
            try {
                $changed = false;

                $ip = $eqLogic->getConfiguration('ipaddress');
                $mac = $eqLogic->getConfiguration('macaddress');

                $t = new Twinkly($ip, $mac, FALSE);

                $state = $t->get_mode();
                $brightess = $t->get_brightness();

                $changed = $eqLogic->checkAndUpdateCmd('state', $state, false) || $changed;
                $changed = $eqLogic->checkAndUpdateCmd('brightness', $brightness, false) || $changed;

                if($changed) {
                    $eqLogic->refreshWidget();
                }
            } catch (Exception $e) {
                if ($_eqLogic_id != null) {
                    log::add('kTwinkly', 'error', $e->getMessage());
                }  else {
					$eqLogic->refresh();
					if ($eqLogic->getIsEnable() == 0) {
						continue;
					}
				}
            }
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}

class kTwinklyCmd extends cmd {
    /*     * *************************Attributs****************************** */
    
    /*
      public static $_widgetPossibility = array();
    */
    
    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

  // Exécution d'une commande  
     public function execute($_options = array()) {
	if ($this->getType() != 'action') {
		return;
	}

	$eqLogic = $this->getEqLogic();
	if(!is_object($eqLogic)){
		return;
	}

	$ip = $eqLogic->getConfiguration('ipaddress');
	$mac = $eqLogic->getConfiguration('macaddress');

	$action = $this->getLogicalId();

	try {
		$t = new Twinkly($ip, $mac, FALSE);

		if($action == "on") {
			log::add('kTwinkly','debug',"Appel commande movie ip=$ip mac=$mac");

			$t->set_mode("movie");
            $newstate = $t->get_mode();

            $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
            if($changed) {
                $eqLogic->refreshWidget();
            }
		} else if($action == "off") {
			log::add('kTwinkly','debug',"Appel commande off ip=$ip mac=$mac");

			$t->set_mode("off");
            $newstate = $t->get_mode();

            $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
            if($changed) {
                $eqLogic->refreshWidget();
            }
		} else if($action == "brightness") {
			$value = intval($_options["slider"]);
			log::add('kTwinkly','debug',"Appel commande set_brightness slider=$value ip=$ip mac=$mac");
			$t->set_brightness($value);
		} else if($action == "movie") {
			$value = $_options["select"];
			if($value != "") {
                log::add('kTwinkly','debug',"Appel commande movie avec $value");
                $filepath = __DIR__ . '/../../data/' . $value;
                if(file_exists($filepath)) {
                    preg_match("/.*_(\d+)_(\d+)_(\d+)\.bin$/", $value, $matches);
                    if(sizeof($matches) == 4) {
                        $leds = intval($matches[1]);
                        $frames = intval($matches[2]);
                        $delay = intval($matches[3]);
                        $t->upload_movie($filepath, $leds, $frames, $delay);
                    } else {
                        log::add('kTwinkly','error','Format du nom de fichier d\'animation incorrect : ' . $value);
                    }
                } else {
                    log::add('kTwinkly','error','Fichier introuvable : ' . $filepath);
                }
			}
		}
	} catch (Exception $e) {
		//log::add('kTwinkly','error', __('Impossible d\'exécuter la commande sur le contrôleur Twinkly : ' . $e->getMessage(), __FILE__));
		throw new Exception(__('Impossible d\'exécuter la commande sur le contrôleur Twinkly : ' . $e->getMessage(), __FILE__));
	}
     }

    /*     * **********************Getteur Setteur*************************** */
}


