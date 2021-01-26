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

require_once __DIR__  . '/TwinklyString.class.php';
require_once __DIR__  . '/kTwinkly_utils.php';

class kTwinklyCmd extends cmd {
      public static $_widgetPossibility = array();

    // Exécution d'une commande  
    public function execute($_options = array()) {
        if ($this->getType() != 'action') {
            return;
        }

        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic)){
            return;
        }

        $ip = $eqLogic->getConfiguration('ipaddress');
        $mac = $eqLogic->getConfiguration('macaddress');
        $hwgen = $eqLogic->getConfiguration('hwgen');

        $autorefresh = $eqLogic->getConfiguration('autorefresh');
        $eqLogic->setConfiguration('autorefresh', 0);

        $action = $this->getLogicalId();

        $tempdir = jeedom::getTmpFolder('kTwinkly');

        $debug = FALSE;
        $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
        if (config::byKey('additionalDebugLogs','kTwinkly') == "1") {
            $debug = TRUE;
        }


        try {
            $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));

            if ($action == "on") {
                // Allumer la guirlande. On active le mode "movie".

                if ($hwgen == "1") {
                    log::add('kTwinkly','debug',"Commande 'on' GEN1 -> appel commande movie ip=$ip mac=$mac");
                    $t->set_mode("movie");
                } else {
                    try {
                        log::add('kTwinkly','debug',"Commande 'on' GEN2 -> changement mode : playlist ip=$ip mac=$mac");
                        $t->set_mode("playlist");
                    } catch (Exception $e1) {
                        try {
                            log::add('kTwinkly','debug',"Commande 'on' GEN2 -> Aucune playlist. Changement mode : movie ip=$ip mac=$mac");
                            $t->set_mode("movie");
                        } catch (Exception $e2) {
                            log::add('kTwinkly','debug',"Commande 'on' GEN2 -> Echec mode movie. Changement mode : effect ip=$ip mac=$mac");
                            $t->set_mode("effect");
                        }
                    }
                }

                $newstate = $t->get_mode();
                $newbrightness = $t->get_brightness();

                $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
                $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
                if ($changed) {
                    $eqLogic->refreshWidget();
                }
            } else if ($action == "off") {
                // Extinction de la guirlande

                log::add('kTwinkly','debug',"Commande 'off' ip=$ip mac=$mac");

                $t->set_mode("off");
                $newstate = $t->get_mode();
                $newbrightness = $t->get_brightness();

                $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
                $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
                if ($changed) {
                    $eqLogic->refreshWidget();
                }
            } else if ($action == "brightness") {
                // Changement de la luminosité si la fonctionnalité est supportée par le firmware

                if ($eqLogic->getConfiguration("hwgen") != "0") {
                    $value = intval($_options["slider"]);
                    log::add('kTwinkly','debug',"Commande 'brightness' slider=$value ip=$ip mac=$mac");
    
                    $t->set_brightness($value);
                    $newbrightness = $t->get_brightness();

                    $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
                    if ($changed) {
                        $eqLogic->refreshWidget();
                    }
                } else {
                    log::add('kTwinkly','debug',"Commande 'brightness' ignorée parce que l'équipement ".$eqLogic->getId()." ne la supporte pas.");
                }
            } else if ($action == "movie") {
                // Chargement d'une animation sur la guirlande

                $value = $_options["select"];
                if ($value != "") {
                    log::add('kTwinkly','debug',"Commande 'movie' avec value=$value ip=$ip mac=$mac");

                    // On récupère le flux binaire et les informations JSON de l'animation choisie depuis le zip
                    $filepath = __DIR__ . '/../../data/' . $value;
                    if (file_exists($filepath)) {
                        $zip = new ZipArchive();
                        if ($zip->open($filepath) === TRUE) {
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
                                // Upload en mode GEN1
                                $tempfile = $tempdir . '/' . $value . '.bin';
                                file_put_contents($tempfile, $bin_data);
                                $leds = intval($json["leds_number"]);
                                $frames = intval($json["frames_number"]);
                                $delay = intval($json["frame_delay"]);

                                log::add('kTwinkly','debug',"Envoi de l'animation GEN1 fichier=$tempfile (leds=$leds frames=$frames delay=$delay)");
                                $t->upload_movie($tempfile, $leds, $frames, $delay);

                                unlink($tempfile);
                            } else {
                                // Upload en mode GEN2
                                $tempfile = $tempdir . '/' . $value . '.bin';
                                file_put_contents($tempfile, $bin_data);

                                log::add('kTwinkly','debug',"Envoi de l'animation GEN2 fichier=$tempfile");
                                $t->upload_movie2($tempfile, $jsonstring);

                                unlink($tempfile);
                            }
                        } else {
                            log::add('kTwinkly','error',"Commande 'movie' : impossible d'ouvrir le fichier zip de l'animation");
                        }
                        $zip->close();
                    } else {
                        log::add('kTwinkly','error',"Commande 'movie' : fichier introuvable : $filepath");
                    }
                }
            } else if ($action == "refresh") {
                // Rafraichissement manuel des valeurs
                log::add('kTwinkly','debug',"Commande 'refresh'");

                $newstate = $t->get_mode();
                $newbrightness = $t->get_brightness();

                $changed = $eqLogic->checkAndUpdateCmd('state', $newstate, false) || $changed;
                $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $newbrightness, false) || $changed;
                if ($changed) {
                    $eqLogic->refreshWidget();
                }
            } else if ($action == "playlist") {
                try {
                    log::add('kTwinkly','debug',"Commande 'playlist' : changement mode : playlist ip=$ip mac=$mac");
                    $t->set_mode("playlist");
                    $newstate = $t->get_mode();
                    if ($eqLogic->checkAndUpdateCmd('state', $newstate, false)) {
                        $eqLogic->refreshWidget();
                    }
                } catch (Exception $e1) {
                    log::add('kTwinkly','error',__("Commande 'playlist' : impossible d'activer le mode playlist : ", __FILE__) . $e1->getMessage());
                }
            }
        } catch (Exception $e) {
            throw new Exception(__('Impossible d\'exécuter la commande sur le contrôleur Twinkly : ', __FILE__) . $e->getMessage());
        } finally {
            $eqLogic->setConfiguration('autorefresh', $autorefresh);
        }
    }
}

