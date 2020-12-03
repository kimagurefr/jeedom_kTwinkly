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
require_once __DIR__  . '/kTwinkly_utils.php';

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

    $tempdir = jeedom::getTmpFolder('kTwinkly');

	try {
		$t = new Twinkly($ip, $mac, TRUE);

		if($action == "on") {
			log::add('kTwinkly','debug',"Appel commande movie ip=$ip mac=$mac");

			$t->set_mode("movie");
            $newstate = $t->get_mode();
            $newbrightness = $t->get_brightness();

            $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
            $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
            if($changed) {
                $eqLogic->refreshWidget();
            }
		} else if($action == "off") {
			log::add('kTwinkly','debug',"Appel commande off ip=$ip mac=$mac");

			$t->set_mode("off");
            $newstate = $t->get_mode();
            $newbrightness = $t->get_brightness();

            $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
            $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
            if($changed) {
                $eqLogic->refreshWidget();
            }
		} else if($action == "brightness") {
            if ($eqLogic->getConfiguration("hwgen") != "0") {
                $value = intval($_options["slider"]);
                log::add('kTwinkly','debug',"Appel commande set_brightness slider=$value ip=$ip mac=$mac");
    
                $t->set_brightness($value);
                $newbrightness = $t->get_brightness();

                $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
                if($changed) {
                    $eqLogic->refreshWidget();
                }
            } else {
                log::add('kTwinkly','debug',"Commande set_brightness ignorée parce que l'équipement ".$eqLogic->getId()." ne la supporte pas.");
            }
		} else if($action == "movie") {
			$value = $_options["select"];
			if($value != "") {
                log::add('kTwinkly','debug',"Appel commande movie avec $value");
                $filepath = __DIR__ . '/../../data/' . $value;
                if(file_exists($filepath)) {
                    $zip = new ZipArchive();
                    if($zip->open($filepath) === TRUE) {
                        for ($i=0; $i<$zip->numFiles; $i++) {
                            $zfilename = $zip->statIndex($i)["name"];
                            if (preg_match('/bin$/',strtolower($zfilename))) {
                                $bin_data = $zip->getFromIndex($i);
                            }
                            if (preg_match('/json$/',strtolower($zfilename))) {
                                $jsonstring = $zip->getFromIndex($i);
                                $json = json_decode($jsonstring, TRUE);
                            }
                        }
                        if ($eqLogic->getConfiguration("hwgen") == "1") {
                            // GEN 1
                            $tempfile = $tempdir . '/' . $value . '.bin';
                            file_put_contents($tempfile, $bin_data);
                            $leds = intval($json["leds_number"]);
                            $frames = intval($json["frames_number"]);
                            $delay = intval($json["frame_delay"]);
                            log::add('kTwinkly','debug',"Envoi du fichier $tempfile (leds=$leds frames=$frames delay=$delay)");
                            $t->upload_movie($tempfile, $leds, $frames, $delay);
                            unlink($tempfile);
                        } else {
                            // GEN 2
                            $tempfile = $tempdir . '/' . $value . '.bin';
                            file_put_contents($tempfile, $bin_data);
                            log::add('kTwinkly','debug',"Envoi du fichier $tempfile (GEN2)");
                            $t->upload_movie2($tempfile, $jsonstring);
                            //unlink($tempfile);
                        }
                    } else {
                        log::add('kTwinkly','error','Impossible d\'ouvrir le fichier zip de l\'animation');
                    }
                    $zip->close();
                } else {
                    log::add('kTwinkly','error','Fichier introuvable : ' . $filepath);
                }
			}
		} else if($action == "refresh") {
           log::add('kTwinkly','debug',"Appel commande refresh");

            $newstate = $t->get_mode();
            $newbrightness = $t->get_brightness();

            $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
            $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
            if($changed) {
                $eqLogic->refreshWidget();
            }
        }
	} catch (Exception $e) {
		//log::add('kTwinkly','error', __('Impossible d\'exécuter la commande sur le contrôleur Twinkly : ' . $e->getMessage(), __FILE__));
		throw new Exception(__('Impossible d\'exécuter la commande sur le contrôleur Twinkly : ' . $e->getMessage(), __FILE__));
	}
     }

    /*     * **********************Getteur Setteur*************************** */
}


