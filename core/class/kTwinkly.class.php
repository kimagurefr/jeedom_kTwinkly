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
require_once __DIR__  . '/TwinklyMusic.class.php';
require_once __DIR__  . '/../php/kTwinkly_utils.php';

include_file('core', 'kTwinklyCmd', 'class', 'kTwinkly');

class kTwinkly extends eqLogic {
    /* Attributs et constantes */
    private static $_eqLogics = null;
    const MITM_DEFAULT_PORT = 14233;
    const MITM_START_WAIT = 3;

    /*
     * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
     * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
     public static $_widgetPossibility = array();
    */
    
    /* ---------------------------------------------------------------------------- */
    /* Methodes statiques */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
     */
    public static function cronDaily()
    {
        try
        {
            if (intval(config::byKey('refreshFrequency','kTwinkly')) > 0)
            {
                if (date('i') == 0 && date('s') < 10)
                {
                    sleep(10);
                }
                $plugin = plugin::byId(__CLASS__);
                $plugin->deamon_start(true);
            }
        }
        catch (\Exception $e)
        {
            log::add('kTwinkly','debug','error in cronDaily : ' . $e->getMessage());
        }
    }


    /* ---------------------------------------------------------------------------- */
    /* Methodes d'instance */

    // Fonction exécutée automatiquement avant la mise à jour de l'équipement 
    public function preUpdate()
    {
        // Vérification des paramètres de l'équipement
        log::add('kTwinkly','debug','kTwinkly::preUpdate');

	    $ip = $this->getConfiguration('ipaddress');
	    $mac = $this->getConfiguration('macaddress');
        $clearMemory = $this->getConfiguration('clearmemory');

	    if ($ip == '')
        {
		   throw new Exception(__('L\'adresse IP ne peut être vide', __FILE__)); 
	    }

	    if (!filter_var($ip, FILTER_VALIDATE_IP))
        {
		   throw new Exception(__('Le format de l\'adresse IP est incorrect', __FILE__)); 
	    }

	    if ($mac == '')
        {
		   throw new Exception(__('L\'adresse MAC ne peut être vide', __FILE__)); 
	    }

	    if (!filter_var($mac, FILTER_VALIDATE_MAC))
        {
		   throw new Exception(__('Le format de l\'adresse MAC est incorrect', __FILE__)); 
	    }

        if ($this->getIsEnable() == 1)
        {
            try
            {
                $debug = FALSE;
                $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
                if (config::byKey('additionalDebugLogs','kTwinkly') == "1")
                {
                    $debug = TRUE;
                }                                

                if($this->getConfiguration('devicetype') == "" || $this->getConfiguration('devicetype') == NULL)
                {
                    // Find device type
                    log::add('kTwinkly','debug','kTwinkly::preUpdate - identification du type de matériel');
                    try {
                        $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
                        $info = $t->get_details();   
                        $this->setConfiguration('devicetype','leds');
                    }
                    catch (Exception $dum)
                    {
                    }

                    if($info == $null)
                    {
                        try {
                            $t = new TwinklyMusic($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
                            $info = $t->get_details();
                            $this->setConfiguration('devicetype','music');
                        }
                        catch (Exception $dum)
                        {
                            throw new Exception(__('Le type de périphérique est inconnu', __FILE__));
                        }
                    }                    
                }

                if($this->getConfiguration('devicetype') == "leds")
                {
                    // Récupérations des informations sur l'équipement via l'API
                    $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
        
                    $info = $t->get_details();
                    $this->setConfiguration('productcode',$info["product_code"]);
                    $this->setConfiguration('productname',get_product_info($info["product_code"])["commercial_name"]);
                    //$this->setConfiguration('productimage',get_product_info($info["product_code"])["pack_preview"]);
                    $this->setConfiguration('productimage',get_product_image($info["product_code"]));
                    $this->setConfiguration('product',$info["product_name"]);
                    $this->setConfiguration('devicename',$info["device_name"]);
                    $this->setConfiguration('numberleds',$info["number_of_led"]);
                    $this->setConfiguration('ledtype',$info["led_profile"]);
                    $this->setConfiguration('hardwareid',$info["hw_id"]);
                    $this->setConfiguration('firmwarefamily',get_product_info($info["product_code"])["firmware_family"]);
        
                    $fwversion = $t->firmware_version();
                    $this->setConfiguration('firmware',$fwversion);
                    if (versionToInt($fwversion) >= versionToInt("2.5.5"))
                    {
                        $this->setConfiguration('hwgen', '2');
                    }
                    else
                    {
                        if (versionToInt($fwversion) >= versiontoInt("2.3.0"))
                        {
                            $this->setConfiguration('hwgen', '1');
                        }
                        else
                        {
                            $this->setConfiguration('hwgen', '0');
                        }
                    }
                }
                else
                {
                    // Twinkly Music Récupérations des informations sur l'équipement via l'API
                    $t = new TwinklyMusic($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
                    $info = $t->get_details();
                    $this->setConfiguration('productcode',$info["product_code"]);
                    $this->setConfiguration('productname',get_product_info($info["product_code"])["commercial_name"]);
                    //$this->setConfiguration('productimage',get_product_info($info["product_code"])["pack_preview"]);
                    $this->setConfiguration('productimage',get_product_image($info["product_code"]));
                    $this->setConfiguration('product',$info["product_name"]);
                    $this->setConfiguration('devicename',$info["device_name"]);
                    $this->setConfiguration('hardwareid',$info["hw_id"]);
                    $this->setConfiguration('firmwarefamily',get_product_info($info["product_code"])["firmware_family"]);

                    $fwversion = $t->firmware_version();
                    $this->setConfiguration('firmware',$fwversion);
                }
            }
            catch (Exception $e)
            {
                throw new Exception(__('Impossible de contacter le contrôleur Twinkly. Vérifiez les paramètres.', __FILE__));
            }
        }
	    $this->setLogicalId(generate_device_id($mac));        
    }

    // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement 
    public function postSave()
    {
        // Création des commandes
        log::add('kTwinkly','debug','kTwinkly::postSave');

        $cmdIndex = -1;
        if($this->getConfiguration("devicetype") == "leds")
        {
            $cmdIndex++;
            $onCmd = $this->getCmd(null, "on");
            if (!is_object($onCmd))
            {
                $onCmd = new kTwinklyCmd();
                $onCmd->setName(__('On', __FILE__));
                $onCmd->setEqLogic_id($this->getId());
                $onCmd->setLogicalId('on');
                $onCmd->setType('action');
                $onCmd->setSubType('other');
                $onCmd->setGeneric_type('LIGHT_ON');
                $onCmd->setIsVisible(1);
                $onCmd->setValue('on');
                $onCmd->setDisplay('icon','<i class="icon jeedom-lumiere-on"></i>');
                $onCmd->setOrder($cmdIndex);
                $onCmd->save();
            }

            $cmdIndex++;
            $offCmd = $this->getCmd(null, "off");
            if (!is_object($offCmd))
            {
                $offCmd = new kTwinklyCmd();
                $offCmd->setName(__('Off', __FILE__));
                $offCmd->setEqLogic_id($this->getId());
                $offCmd->setLogicalId('off');
                $offCmd->setType('action');
                $offCmd->setSubType('other');
                $offCmd->setGeneric_type('LIGHT_OFF');
                $offCmd->setIsVisible(1);
                $offCmd->setValue('off');
                $offCmd->setDisplay('icon','<i class="icon jeedom-lumiere-off"></i>');
                $offCmd->setOrder($cmdIndex);
                $offCmd->save();
            }

            if(version_supports_brightness($this->getConfiguration("firmwarefamily"),$this->getConfiguration("firmware")))
            {
                $cmdIndex++;
                $brightnessCmd = $this->getCmd(null, "brightness");
                if (!is_object($brightnessCmd))
                {
                    $brightnessCmd = new kTwinklyCmd();
                    $brightnessCmd->setName(__('Luminosité', __FILE__));
                    $brightnessCmd->setEqLogic_id($this->getId());
                    $brightnessCmd->setLogicalId('brightness');
                    $brightnessCmd->setType('action');
                    $brightnessCmd->setSubType('slider');
                    $brightnessCmd->setGeneric_type('LIGHT_SLIDER');
                    $brightnessCmd->setConfiguration('minValue','0');
                    $brightnessCmd->setConfiguration('maxValue','100');
                    $brightnessCmd->setConfiguration('lastCmdValue','100');
                    $brightnessCmd->setIsVisible(1);
                    $brightnessCmd->setOrder($cmdIndex);
                    $brightnessCmd->save();
                }
                
                $cmdIndex++;
                $brightnessStateCmd = $this->getCmd(null, "brightness_state");
                if (!is_object($brightnessStateCmd))
                {
                    $brightnessStateCmd = new kTwinklyCmd();
                    $brightnessStateCmd->setName(__('Etat Luminosité', __FILE__));
                    $brightnessStateCmd->setEqLogic_id($this->getId());
                    $brightnessStateCmd->setLogicalId('brightness_state');
                    $brightnessStateCmd->setType('info');
                    $brightnessStateCmd->setSubType('numeric');
                    $brightnessStateCmd->setGeneric_type('LIGHT_STATE');
                    $brightnessStateCmd->setIsVisible(1);
                    $brightnessStateCmd->setOrder($cmdIndex);
                    $brightnessStateCmd->save();
                } 
    
                // Liens entre les commandes
                $onCmd->setValue($brightnessStateCmd->getId());
                $onCmd->save();
                $offCmd->setValue($brightnessStateCmd->getId());
                $offCmd->save();
                $brightnessCmd->setValue($brightnessStateCmd->getId());
                $brightnessCmd->save();
            }

            $cmdIndex++;
            $movieCmd = $this->getCmd(null, "movie");
            if (!is_object($movieCmd))
            {
                $movieCmd = new kTwinklyCmd();
                $movieCmd->setName(__('Animation', __FILE__));
                $movieCmd->setEqLogic_id($this->getId());
                $movieCmd->setLogicalId('movie');
                $movieCmd->setType('action');
                $movieCmd->setSubType('select');
                $movieCmd->setIsVisible(1);
                $movieCmd->setOrder($cmdIndex);
                $movieCmd->save();
            }
            
            if(version_supports_getmovies($this->getConfiguration("firmware_family"), $this->getConfiguration("firmware"))) {
                $cmdIndex++;
                $currentMovieCmd = $this->getCmd(null, "currentmovie");
                if (!is_object($currentMovieCmd))
                {
                    $currentMovieCmd = new kTwinklyCmd();
                    $currentMovieCmd->setName(__('Animation Courante', __FILE__));
                    $currentMovieCmd->setEqLogic_id($this->getId());
                    $currentMovieCmd->setLogicalId('currentmovie');
                    $currentMovieCmd->setType('info');
                    $currentMovieCmd->setSubType('string');
                    $currentMovieCmd->setIsVisible(1);
                    $currentMovieCmd->setOrder($cmdIndex);
                    $currentMovieCmd->save();
                }    
                $movieCmd->setValue($currentMovieCmd->getId());
                $movieCmd->save();            
            }

            $cmdIndex++;
            $stateCmd = $this->getCmd(null, "state");
            if (!is_object($stateCmd))
            {
                $stateCmd = new kTwinklyCmd();
                $stateCmd->setName(__('Etat', __FILE__));
                $stateCmd->setEqLogic_id($this->getId());
                $stateCmd->setLogicalId('state');
                $stateCmd->setType('info');
                $stateCmd->setSubType('string');
                $stateCmd->setIsVisible(1);
                $stateCmd->setOrder($cmdIndex);
                $stateCmd->save();
            }

            $cmdIndex++;
            $currentModeCmd = $this->getCmd(null, "currentmode");
            if (!is_object($currentModeCmd))
            {
                $currentModeCmd = new kTwinklyCmd();
                $currentModeCmd->setName(__('Mode courant', __FILE__));
                $currentModeCmd->setEqLogic_id($this->getId());
                $currentModeCmd->setLogicalId('currentmode');
                $currentModeCmd->setType('info');
                $currentModeCmd->setSubType('string');
                $currentModeCmd->setIsVisible(1);
                $currentModeCmd->setOrder($cmdIndex);
                $currentModeCmd->save();
            }            

            if ($this->getConfiguration("hwgen") == "2")
            {
                $cmdIndex++;
                $playlistCmd = $this->getCmd(null, "playlist");
                if (!is_object($playlistCmd))
                {
                    $playlistCmd = new kTwinklyCmd();
                    $playlistCmd->setName(__('Playlist', __FILE__));
                    $playlistCmd->setEqLogic_id($this->getId());
                    $playlistCmd->setLogicalId('playlist');
                    $playlistCmd->setType('action');
                    $playlistCmd->setSubType('other');
                    $playlistCmd->setIsvisible(1);
                    $playlistCmd->setOrder($cmdIndex);
                    $playlistCmd->save();
                }
            }

            if(version_supports_color($this->getConfiguration("firmwarefamily"), $this->getConfiguration("firmware")))
            {
                $cmdIndex++;
                $currentColorCmd = $this->getCmd(null, "color_state");
                if (!is_object($currentColorCmd))
                {
                    $currentColorCmd = new kTwinklyCmd();
                    $currentColorCmd->setName(__('Couleur courante', __FILE__));
                    $currentColorCmd->setEqLogic_id($this->getId());
                    $currentColorCmd->setLogicalId('color_state');
                    $currentColorCmd->setType('info');
                    $currentColorCmd->setSubType('string');
                    $currentColorCmd->setGeneric_type('LIGHT_COLOR');
                    $currentColorCmd->setIsVisible(0);
                    $currentColorCmd->setOrder($cmdIndex);
                    $currentColorCmd->save();
                } 

                $cmdIndex++;
                $colorCmd = $this->getCmd(null, "color");
                if (!is_object($colorCmd))
                {
                    $colorCmd = new kTwinklyCmd();
                    $colorCmd->setName(__('Couleur', __FILE__));
                    $colorCmd->setEqLogic_id($this->getId());
                    $colorCmd->setLogicalId('color');
                    $colorCmd->setType('action');
                    $colorCmd->setSubType('color');
                    $colorCmd->setIsVisible(1);
                    $colorCmd->setOrder($cmdIndex);
                    $colorCmd->save();
                }

                $colorCmd->setValue($currentColorCmd->getId());
                $colorCmd->save();
            }

            if(version_supports_getmovies($this->getConfiguration("firmwarefamily"), $this->getConfiguration("firmware")))
            {
                $cmdIndex++;
                $memoryFreeCmd = $this->getCmd(null, "memoryfree");
                if (!is_object($memoryFreeCmd))
                {
                    $memoryFreeCmd = new kTwinklyCmd();
                    $memoryFreeCmd->setName(__('Mémoire libre', __FILE__));
                    $memoryFreeCmd->setEqLogic_id($this->getId());
                    $memoryFreeCmd->setLogicalId('memoryfree');
                    $memoryFreeCmd->setType('info');
                    $memoryFreeCmd->setSubType('numeric');
                    $memoryFreeCmd->setUnite("%");
                    $memoryFreeCmd->setIsVisible(0);
                    $memoryFreeCmd->setIsHistorized(0);
                    $memoryFreeCmd->setOrder($cmdIndex);
                    $memoryFreeCmd->save();
                } 

                $cmdIndex++;
                $clearMemCmd = $this->getCmd(null, "clearmem");
                if (!is_object($clearMemCmd))
                {
                    $clearMemCmd = new kTwinklyCmd();
                    $clearMemCmd->setName(__('Efface mémoire', __FILE__));
                    $clearMemCmd->setEqLogic_id($this->getId());
                    $clearMemCmd->setLogicalId('clearmem');
                    $clearMemCmd->setType('action');
                    $clearMemCmd->setSubType('other');
                    $clearMemCmd->setIsVisible(0);
                    $clearMemCmd->setValue('clearmem');
                    $clearMemCmd->setOrder($cmdIndex);
                    $clearMemCmd->save();
                }
            }

            $cmdIndex++;
            $refreshCmd = $this->getCmd(null, "refresh");
            if (!is_object($refreshCmd))
            {
                $refreshCmd = new kTwinklyCmd();
                $refreshCmd->setName(__('Refresh', __FILE__));
                $refreshCmd->setEqLogic_id($this->getId());
                $refreshCmd->setLogicalId('refresh');
                $refreshCmd->setType('action');
                $refreshCmd->setSubType('other');
                $refreshCmd->setIsVisible(0);
                $refreshCmd->setValue('refresh');
                $refreshCmd->setOrder($cmdIndex);
                $refreshCmd->save();
            }
            self::populate_movies_list($this->getID());
        }
        elseif($this->getConfiguration("devicetype") == "music")
        {
            // Twinkly Music
            $onCmd = $this->getCmd(null, "on");
            if (!is_object($onCmd))
            {
                $onCmd = new kTwinklyCmd();
                $onCmd->setName(__('On', __FILE__));
                $onCmd->setEqLogic_id($this->getId());
                $onCmd->setLogicalId('on');
                $onCmd->setType('action');
                $onCmd->setSubType('other');
                $onCmd->setGeneric_type('LIGHT_ON');
                $onCmd->setIsVisible(1);
                $onCmd->setValue('on');
                $onCmd->setDisplay('icon','<i class="icon jeedom-on"></i>');
                //$onCmd->setOrder(0);
                $onCmd->save();
            }

            $offCmd = $this->getCmd(null, "off");
            if (!is_object($offCmd))
            {
                $offCmd = new kTwinklyCmd();
                $offCmd->setName(__('Off', __FILE__));
                $offCmd->setEqLogic_id($this->getId());
                $offCmd->setLogicalId('off');
                $offCmd->setType('action');
                $offCmd->setSubType('other');
                $offCmd->setGeneric_type('LIGHT_OFF');
                $offCmd->setIsVisible(1);
                $offCmd->setValue('off');
                $offCmd->setDisplay('icon','<i class="icon jeedom-off"></i>');
                //$offCmd->setOrder(1);
                $offCmd->save();
            }

            $stateCmd = $this->getCmd(null, "state");
            if (!is_object($stateCmd))
            {
                $stateCmd = new kTwinklyCmd();
                $stateCmd->setName(__('Etat', __FILE__));
                $stateCmd->setEqLogic_id($this->getId());
                $stateCmd->setLogicalId('state');
                $stateCmd->setType('info');
                $stateCmd->setSubType('string');
                $stateCmd->setIsVisible(1);
                //$stateCmd->setOrder(2);
                $stateCmd->save();
            }           
            /*
            $micOnCmd = $this->getCmd(null, "micon");
            if (!is_object($micOnCmd))
            {
                $micOnCmd = new kTwinklyCmd();
                $micOnCmd->setName(__('Microphone On', __FILE__));
                $micOnCmd->setEqLogic_id($this->getId());
                $micOnCmd->setLogicalId('micon');
                $micOnCmd->setType('action');
                $micOnCmd->setSubType('other');
                $micOnCmd->setGeneric_type('LIGHT_ON');
                $micOnCmd->setIsVisible(1);
                $micOnCmd->setValue('on');
                //$micOnCmd->setDisplay('icon','<i class="icon jeedom-lumiere-on"></i>');
                //$micOnCmd->setOrder(3);
                $micOnCmd->save();
            }

            $micOffCmd = $this->getCmd(null, "micoff");
            if (!is_object($micOffCmd))
            {
                $micOffCmd = new kTwinklyCmd();
                $micOffCmd->setName(__('Microphone Off', __FILE__));
                $micOffCmd->setEqLogic_id($this->getId());
                $micOffCmd->setLogicalId('micoff');
                $micOffCmd->setType('action');
                $micOffCmd->setSubType('other');
                $micOffCmd->setGeneric_type('LIGHT_OFF');
                $micOffCmd->setIsVisible(1);
                $micOffCmd->setValue('off');
                //$micOffCmd->setDisplay('icon','<i class="icon jeedom-lumiere-off"></i>');
                //$micOffCmd->setOrder(4);
                $micOffCmd->save();
            }

            $micStateCmd = $this->getCmd(null, "micstate");
            if (!is_object($micStateCmd))
            {
                $micStateCmd = new kTwinklyCmd();
                $micStateCmd->setName(__('Etat Microphone', __FILE__));
                $micStateCmd->setEqLogic_id($this->getId());
                $micStateCmd->setLogicalId('micstate');
                $micStateCmd->setType('info');
                $micStateCmd->setSubType('string');
                $micStateCmd->setIsVisible(1);
                //$micStateCmd->setOrder(5);
                $micStateCmd->save();
            }   
            */
            $refreshCmd = $this->getCmd(null, "refresh");
            if (!is_object($refreshCmd))
            {
                $refreshCmd = new kTwinklyCmd();
                $refreshCmd->setName(__('Refresh', __FILE__));
                $refreshCmd->setEqLogic_id($this->getId());
                $refreshCmd->setLogicalId('refresh');
                $refreshCmd->setType('action');
                $refreshCmd->setSubType('other');
                $refreshCmd->setIsVisible(1);
                $refreshCmd->setValue('refresh');
                //$refreshCmd->setOrder(6);
                $refreshCmd->save();
            }            
        }

        log::add('kTwinkly','debug','kTwinkly::postUpdate');
        
        if ($this->getChanged())
        {
            self::deamon_start();
        }
    }

    // Fonction exécutée automatiquement avant la suppression de l'équipement 
    public function preRemove()
    {
        log::add('kTwinkly','debug','Suppression des fichiers liées à l\'équipement');

        // Suppression des animations liées à cet équipement
        $animpath = __DIR__ . '/../../data/movie_' . $this->getId() . '_*.zip';
        try {
            array_map( "unlink", glob( $animpath ) );    
        } catch (\Exception $e) {}

        // Suppression des playlists
        $playlistpath = __DIR__ . '/../../data/playlist_' . $this->getId() . '_*.json';
        try {
            array_map( "unlink", glob( $playlistpath ) );
        } catch (\Exception $e) {}

        // Suppression des exports de cet équipement
        $exportpath = __DIR__ . '/../../data/kTwinkly_export_' .  $this->getId() . '_*.zip';
        try {
            array_map( "unlink", glob( $exportpath ) );
        } catch (\Exception $e) {}

        // Suppression du cache des animations pour cet équipement
        $cachepath = __DIR__ . '/../../data/moviecache_' . $this->getId() . '.json';
        if(is_file($cachepath)) {
            unlink($cachepath);
        }
    }

    // Découverte automatique des équipements sur le réseau
    public static function discover()
    {
	    log::add('kTwinkly','debug','Démarrage de la recherche d\'équipements Twinkly Leds');
	    $devices = TwinklyString::discover();
	    log::add('kTwinkly','debug','Equipements leds trouvés : ' . print_r($devices,TRUE));

	    foreach($devices as $d)
        {
            // Logical id = Twinkly- suivi de l'adresse IP
		    //$lID = "Twinkly-" . $d["ip"];
            $lID = generate_device_id($d["mac"]);

		    $eqLogic = self::byLogicalId($lID, 'kTwinkly');
		    if (!is_object($eqLogic))
            {
			    log::add('kTwinkly','debug','Nouvel équipement trouvé : name=' . $d["name"] . '(' . $d["details"]["device_name"] . ') ip=' . $d["ip"] . ' mac=' . $d["mac"]); 
			    $eqLogic = new self();
			    $eqLogic->setLogicalId($lID);
			    $eqLogic->setName($d["details"]["device_name"]);
			    $eqLogic->setEqType_name('kTwinkly');
			    $eqLogic->setIsVisible(1);
			    $eqLogic->setIsEnable(1);
		    }
            else
            {
			    log::add('kTwinkly','debug','Equipement déjà existant : ' . $lID);
		    }
            $eqLogic->setConfiguration('devicetype','leds');
		    $eqLogic->setConfiguration('ipaddress', $d["ip"]);
		    $eqLogic->setConfiguration('macaddress', $d["mac"]);
            if (intval(config::byKey('refreshFrequency','kTwinkly')) > 0)
            {
                $eqLogic->setConfiguration('autorefresh', 1);
            }
            else
            {
                $eqLogic->setConfiguration('autorefresh', 0);
            }
            $eqLogic->setConfiguration('productcode',$d["details"]["product_code"]);
            $eqLogic->setConfiguration('productname',get_product_info($d["details"]["product_code"])["commercial_name"]);
            //$eqLogic->setConfiguration('productimage',get_product_info($d["details"]["product_code"])["pack_preview"]);
            $eqLogic->setConfiguration('productimage',get_product_image($d["details"]["product_code"]));
            $eqLogic->setConfiguration('product',$d["details"]["product_name"]);
            $eqLogic->setConfiguration('devicename',$d["details"]["device_name"]);
            $eqLogic->setConfiguration('numberleds',$d["details"]["number_of_led"]);
            $eqLogic->setConfiguration('ledtype',$d["details"]["led_profile"]);
            $eqLogic->setConfiguration('hardwareid',$d["details"]["hw_id"]);
            $eqLogic->setConfiguration('firmwarefamily',get_product_info($d["details"]["product_code"])["firmware_family"]);

            $fwversion = $d["details"]["firmware_version"];
            $eqLogic->setConfiguration('firmware',$fwversion);
            if (versionToInt($fwversion) >= versionToInt("2.5.5"))
            {
                $eqLogic->setConfiguration('hwgen', '2');
            }
            else
            {
                if (versionToInt($fwversion) >= versiontoInt("2.3.0"))
                {
                    $eqLogic->setConfiguration('hwgen', '1');
                }
                else
                {
                    $eqLogic->setConfiguration('hwgen', '0');
                }
            }

		    $eqLogic->save();
	    }

	    log::add('kTwinkly','debug','Démarrage de la recherche d\'équipements Twinkly Music');
	    $devices = TwinklyMusic::discover();
	    log::add('kTwinkly','debug','Equipements Music trouvés : ' . print_r($devices,TRUE));

	    foreach($devices as $d)
        {
            // Logical id = Twinkly- suivi de l'adresse IP
            $lID = generate_device_id($d["mac"]);

		    $eqLogic = self::byLogicalId($lID, 'kTwinkly');
		    if (!is_object($eqLogic))
            {
			    log::add('kTwinkly','debug','Nouvel équipement Music trouvé : name=' . $d["name"] . '(' . $d["details"]["device_name"] . ') ip=' . $d["ip"] . ' mac=' . $d["mac"]); 
			    $eqLogic = new self();
			    $eqLogic->setLogicalId($lID);
			    $eqLogic->setName($d["details"]["device_name"]);
			    $eqLogic->setEqType_name('kTwinkly');
			    $eqLogic->setIsVisible(1);
			    $eqLogic->setIsEnable(1);
		    }
            else
            {
			    log::add('kTwinkly','debug','Equipement déjà existant : ' . $lID);
		    }
            $eqLogic->setConfiguration('devicetype','music');
		    $eqLogic->setConfiguration('ipaddress', $d["ip"]);
		    $eqLogic->setConfiguration('macaddress', $d["mac"]);
            $eqLogic->setConfiguration('productcode',$d["details"]["product_code"]);
            $eqLogic->setConfiguration('productname',get_product_info($d["details"]["product_code"])["commercial_name"]);
            //$eqLogic->setConfiguration('productimage',get_product_info($d["details"]["product_code"])["pack_preview"]);
            $eqLogic->setConfiguration('productimage',get_product_image($d["details"]["product_code"]));
            $eqLogic->setConfiguration('product',$d["details"]["product_name"]);
            $eqLogic->setConfiguration('devicename',$d["details"]["device_name"]);
            $eqLogic->setConfiguration('hardwareid',$d["details"]["hw_id"]);
            $eqLogic->setConfiguration('firmwarefamily',get_product_info($d["details"]["product_code"])["firmware_family"]);

            $fwversion = $d["details"]["firmware_version"];
            $eqLogic->setConfiguration('firmware',$fwversion);

		    $eqLogic->save();
	    }
    }

    // Vérifications de l'état du cron
    public static function deamon_info()
    {
        $return = array("log" => "", "state" => "nok");
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (is_object($cron) && $cron->running())
        {
            $return['state'] = 'ok';
        }
        $return['launchable'] = 'ok';
        log::add('kTwinkly','debug','kTwinkly deamon_info : ' . print_r($return, TRUE));
        return $return;
    }

    // Arrêt du cron
    public static function deamon_stop()
    {
        log::add('kTwinkly','debug','kTwinkly deamon_stop');
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (!is_object($cron))
        {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->halt();
    }

    // Démarrage du cron
    public static function deamon_start()
    {
        log::add('kTwinkly','debug','kTwinkly deamon_start');
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok')
        {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');

        if (!is_object($cron))
        {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->run();
    }

    public static function deamon_changeAutoMode($_mode)
    {
        log::add('kTwinkly','debug','kTwinkly deamon_changeAutoMode');
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (!is_object($cron))
        {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->setEnable($_mode);
        $cron->save();
    }

    // Rafraîchissement des valeurs par appel à l'API
    public static function refreshstate($_eqLogic_id = null, $manual=FALSE)
    {
        log::add('kTwinkly','debug','kTwinkly refreshsate id=' . $_eqLogic_id . ' manual=' . $manual);

        if (self::$_eqLogics == null)
        {
            self::$_eqLogics = self::byType('kTwinkly');
        }

        $refreshFrequency = config::byKey('refreshFrequency','kTwinkly');

        foreach (self::$_eqLogics as &$eqLogic)
        {
            if ($_eqLogic_id != null && $_eqLogic_id != $eqLogic->getId())
            {
                continue;
            }
            if ($eqLogic->getIsEnable() == 0)
            {
                $eqLogic->refresh();
            }
            if ($eqLogic->getLogicalId() == '' || $eqLogic->getIsEnable() == 0)
            {
                continue;
            }

            // On vérifie si l'autorefresh est actif
            if ((intval($refreshFrequency) > 0 && $eqLogic->getConfiguration('autorefresh')==1) || ($manual == TRUE))
            {
                try
                {
                    $changed = false;
    
                    $ip = $eqLogic->getConfiguration('ipaddress');
                    $mac = $eqLogic->getConfiguration('macaddress');
    
                    $debug = FALSE;
                    $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
                    if (config::byKey('additionalDebugLogs','kTwinkly') == "1")
                    {
                        $debug = TRUE;
                    }

                    if($eqLogic->getConfiguration('devicetype') == 'leds')
                    {
                        /*
                        $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
    
                        $currentMode = $t->get_mode();                        
                        $brightness = $t->get_brightness();
                        $state = ($currentMode=="off"?"off":"on");
                        log::add('kTwinkly','debug','kTwinkly refreshstate - current state = ' . $state . ' / ' . $currentMode);
                        $changed = $eqLogic->checkAndUpdateCmd('currentmode', $currentMode, false) || $changed;
                        $changed = $eqLogic->checkAndUpdateCmd('state', $state, false) || $changed;
                        $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $brightness, false) || $changed;
                        */
                        $refreshCmd = $eqLogic->getCmd(null, "refresh");
                        if (!is_object($refreshCmd)) {
                            $refreshCmd->execute();
                        }
                    }
                    else if($eqLogic->getConfiguration('devicetype') == 'music')
                    {
                        /*
                        $t = new TwinklyMusic($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
    
                        $current_mode = $t->get_mode();
                        $state = ($current_mode["mode"] != "off"?"on":"off");                       
                        $changed = $eqLogic->checkAndUpdateCmd('state', $state, false) || $changed;
                        
                        //$microphone_state = ($t->get_mic_enabled()?"on":"off");
                        //$changed = $eqLogic->checkAndUpdateCmd('micstate', $microphone_state, false) || $changed;
                        */
                        $refreshCmd = $eqLogic->getCmd(null, "refresh");
                        if (!is_object($refreshCmd)) {
                            $refreshCmd->execute();
                        }
                    }
                    /*
                    if ($changed)
                    {
                        $eqLogic->refreshWidget();
                    }
                    */
                }
                catch (Exception $e)
                {
                    if ($_eqLogic_id != null)
                    {
                        log::add('kTwinkly', 'error', $e->getMessage());
                    }
                    else
                    {
                        $eqLogic->refresh();
                        if ($eqLogic->getIsEnable() == 0)
                        {
                            continue;
                        }
                    }
                }
            }
        }
    }

    public static function get_playlist($_id)
    {
        $eqLogic = eqLogic::byId($_id);
        $ip = $eqLogic->getConfiguration('ipaddress');
        $mac = $eqLogic->getConfiguration('macaddress');
        $debug = FALSE;
        $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
        if (config::byKey('additionalDebugLogs','kTwinkly') == "1")
        {
            $debug = TRUE;
        }
        $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
        $playlist = $t->get_current_playlist();
        return $playlist;
    }

    // Démarre le proxy de capture des animations
    public static function start_mitmproxy($_id)
    {
        log::add('kTwinkly','debug','Démarre mitmproxy pour eqId='.$_id);
        if (!kTwinkly::is_mitm_running())
        {
            $eqLogic = eqLogic::byId($_id);

            $confdir = jeedom::getTmpFolder('kTwinkly');
            $tempfile = $confdir . '/tmovie_' . $_id;
            $pidfile = $confdir . '/mitmproxy.pid';
            $ipaddress = $eqLogic->getConfiguration('ipaddress');
            $hwgen = $eqLogic->getConfiguration("hwgen");

            if (config::byKey('additionalDebugLogs','kTwinkly') == "1")
            {
                $destlog = __DIR__ . '/../../../../log/kTwinkly_mitm';
            }
            else
            {
                $destlog = '/dev/null';
            }

            $mitmport = kTWinkly::get_mitm_port();
            
            if ($eqLogic->getConfiguration("hwgen")=="1")
            {
                $command = 'mitmdump -p ' . $mitmport . ' -s ' . __DIR__ . '/../../resources/mitmdump/twinkly_v1.py --set filename='.$tempfile.' --set ipaddress='.$ipaddress.' --set confdir="' . $confdir . '"';
            }
            else
            {
                $command = 'mitmdump -p ' . $mitmport . ' -s ' . __DIR__ . '/../../resources/mitmdump/twinkly_v2.py --set filename='.$tempfile.' --set ipaddress='.$ipaddress.' --set confdir="'.$confdir.'"';
            }
            log::add('kTwinkly','debug','Start MITM command = ' . $command);
            $pid = shell_exec(sprintf('%s > '.$destlog.' 2>&1 & echo $!', $command));
            sleep(kTwinkly::MITM_START_WAIT);

            if ($pid !== "" && kTwinkly::is_mitm_running($pid))
            {
                file_put_contents($pidfile, $pid);
                log::add('kTwinkly','debug','mitmproxy démarré avec PID='.$pid);
                return true;
            }
            else
            {
                log::add('kTwinkly','error','Impossible de démarrer mitmproxy. Vérifiez l\'installation des dépendances ou un éventuel mesage d\'erreur : ' . file_get_contents('/tmp/kTwinkly_mitm.log'));
                //throw new Exception(__('Impossible de démarrer mitmproxy', __FILE__));
                return false;
            }
        }
        else
        {
            log::add('kTwinkly','debug','start_mitmproxy : mitmproxy est déjà démarré');
            return true;
        }
    }

    // Destruction d'un process
    private static function kill_process($_pid)
    {
        try
        {
            $result = shell_exec(sprintf('kill %d 2>&1', $_pid));
            if (!preg_match('/No such process/', $result))
            {
                return true;
            }
        }
        catch (Exception $e)
        {
            return false;
        }
    }

    // Arrêt du proxy de capture
    public static function stop_mitmproxy($_pid = "")
    {
        if ($_pid == "")
        {
            $pidfile = jeedom::getTmpFolder('kTwinkly') . '/mitmproxy.pid';
            if (file_exists($pidfile))
            {
                $_pid = file_get_contents($pidfile);
            }
        }

        if ($_pid != "" and kTwinkly::is_mitm_running($_pid))
        {
            // On essaye de tuer le process via le PID enregistré lors du démarrage
            log::add('kTwinkly','debug','Arret de mitmproxy en cours d\'exécution (pid=' . $_pid . ')');
            if (kTwinkly::kill_process($_pid))
            {
                log::add('kTwinkly','debug','Process mitmproxy terminé');
                unlink($pidfile);
                return true;
            }
            else
            {
                log::add('kTwinkly','error','stop_mitmproxy : ' . $e->getMessage());
                return false;
            }
        }
        else
        {
            // Le PID n'es pas trouvé (cas d'un plantage). On essaye de retrouver le process par son nom
            log::add('kTwinkly','debug','Impossible de trouver le process mitm avec le PID enregistré. On recherche le process par son nom');
            $_pid = kTwinkly::find_mitm_proc();
            log::add('kTwinkly','debug','Process trouvé PID='.$_pid);

            if ($_pid != "")
            {
                if (kTwinkly::kill_process($_pid))
                {
                    log::add('kTwinkly','debug','Process mitmproxy terminé');
                    return true;
                }
                else
                {
                    log::add('kTwinkly','error','Impossible de détruire le process mitmproxy PID='.$_pid);
                    return false;
                }
            }
            else
            {
                log::add('kTwinkly','debug','Process mitmproxy inexistant');
                return true;
            }
        }
    }

    // Vérification de l'état du proxy
    public static function is_mitm_running($_pid = NULL)
    {
        if ($_pid !== NULL)
        {
            log::add('kTwinkly','debug','is_mitm_running appelé avec PID='.$_pid);
            try
            {
                $result = shell_exec(sprintf('ps %d', $_pid));
                if (count(preg_split("/\n/", $result)) > 2)
                {
                    return true;
                }
            }
            catch(Exception $e)
            {

            }
        }
        else
        {
            log::add('kTwinkly','debug','is_mitm_running appelé sans PID');
            $_pid = kTwinkly::find_mitm_proc();
            if ($_pid != "")
            {
                return true;
            }
        }
        return false;
    }

    // Recherche du process par son nom
    public static function find_mitm_proc()
    {
        //$mitmcommand = preg_quote(kTwinkly::get_mitm_command(),'/');
        $mitmcommand = 'mitmdump';
        $shellcmd="ps hf -opid,cmd -C mitmdump | grep '" . $mitmcommand . "' | awk '$2 !~ /^[|\\\\]/ { print $1 }'";
        return trim(shell_exec($shellcmd));
    }

    // Récupère le port paramétré dans la config, ou utilise un valeur par défaut
    public static function get_mitm_port()
    {
        $mitmport = config::byKey('mitmPort','kTwinkly');
        if ($mitmport == '')
        {
            $mitmport = kTwinkly::MITM_DEFAULT_PORT;
        }
        return $mitmport;
    }

    // Renvoie l'image de l'équipement
    // La table de mapping et les images sont celles fournies par Twinkly dans l'application Android
    public function getImage()
    {
        $plugin = plugin::byId($this->getEqType_name());
        $defaultImage = $plugin->getPathImgIcon();
        $deviceImage = $this->getConfiguration("productimage");
        if ($deviceImage && file_exists(__DIR__ . '/../config/images/'.$deviceImage))
        {
            return 'plugins/kTwinkly/core/config/images/'.$deviceImage;
        }
        else
        {
            return 'plugins/kTwinkly/core/config/images/default.png';
        }
    }

    // Charge la liste des animations dans la liste déroulante à partir des fichiers sur le disque
    public static function populate_movies_list($_id)
    {
        log::add('kTwinkly','debug','populate_movies_list - id=' . $_id);
        $eqLogic = eqLogic::byId($_id);

        $dataDir = __DIR__ . '/../../data/';
        $movieMask = $dataDir . 'movie_' . $_id . '_*.zip';
        $allMovies = glob($movieMask);
        $movieList = "";
        $movieTable = array();

        if (count($allMovies) != 0) {
            log::add('kTwinkly','debug','populate_movies_list - found ' . count($allMovies) . ' movies');
            foreach($allMovies as $filePath) {
                $filename = substr($filePath, strlen($dataDir));
                $zip = new ZipArchive();
                if ($zip->open($filePath) === TRUE)
                {
                    for ($i=0; $i<$zip->numFiles; $i++)
                    {
                        $zfilename = $zip->statIndex($i)["name"];
                        if (preg_match('/json$/',strtolower($zfilename)))
                        {
                            $jsonstring = $zip->getFromIndex($i);
                            $json = json_decode($jsonstring, TRUE);
                            $movieName = $json["name"] ?? substr($filename, 0, -4);
                            $uuid = $json["unique_id"] ?? $filename;

                            $movieList .= ';' . $filename . '|' . $movieName;
                            array_push($movieTable, array("unique_id" => $uuid, "name" => $movieName, "file" => $filename));
                        }
                    }
                }
            }
            $movieList = substr($movieList, 1); // Supprime le ";" initial
        }
        
        $movieCmd = $eqLogic->getCmd(null, "movie");
        if(is_object($movieCmd)) {
            $movieCmd->setConfiguration('listValue', $movieList);
            $movieCmd->save();
        }

        $movieCache = $dataDir . 'moviecache_' . $_id . '.json';
        file_put_contents($movieCache, json_encode($movieTable));
        
        $eqLogic->refreshWidget();
    }

    // Met à jour le titre des animations dans le JSON inclus dans le zip
    public static function update_titles($_id, $changed) 
    {
        $eqLogic = eqLogic::byId($_id);
        $dataDir = __DIR__ . '/../../data/';
        
        foreach($changed as $c)
        {
            $filePath = $dataDir . $c["file"];
            $zip = new ZipArchive();
            if ($zip->open($filePath) === TRUE)
            {
                for ($i=0; $i<$zip->numFiles; $i++)
                {
                    $zfilename = $zip->statIndex($i)["name"];
                    if (preg_match('/json$/',strtolower($zfilename)))
                    {
                        $jsonstring = $zip->getFromIndex($i);
                        $json = json_decode($jsonstring, TRUE);
                        $json["name"] = $c["new"]; // On change le nom (ou on l'ajoute la premiere fois pour les GEN1)
                        $zip->deleteIndex($i); // On supprime l'ancien fichier JSON
                        $zip->addFromString($zfilename, json_encode($json)); // On ajoute le JSON modifié
                        $zip->close();
                    }
                }                
            }
        }
    }
}
