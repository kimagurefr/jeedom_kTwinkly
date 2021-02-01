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
    public static function cronDaily() {
        try {
            if (intval(config::byKey('refreshFrequency','kTwinkly')) > 0) {
                if (date('i') == 0 && date('s') < 10) {
                    sleep(10);
                }
                $plugin = plugin::byId(__CLASS__);
                $plugin->deamon_start(true);
            }
        } catch (\Exception $e) {
            log::add('kTwinkly','debug','error in cronDaily : ' . $e->getMessage());
        }
    }


    /* ---------------------------------------------------------------------------- */
    /* Methodes d'instance */

    // Fonction exécutée automatiquement avant la mise à jour de l'équipement 
    public function preUpdate() {
        // Vérification des paramètres de l'équipement

	    $ip = $this->getConfiguration('ipaddress');
	    $mac = $this->getConfiguration('macaddress');
        $clearMemory = $this->getConfiguration('clearmemory');

	    if ($ip == ''){
		   throw new Exception(__('L\'adresse IP ne peut être vide', __FILE__)); 
	    }

	    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
		   throw new Exception(__('Le format de l\'adresse IP est incorrect', __FILE__)); 
	    }

	    if ($mac == ''){
		   throw new Exception(__('L\'adresse MAC ne peut être vide', __FILE__)); 
	    }

	    if (!filter_var($mac, FILTER_VALIDATE_MAC)) {
		   throw new Exception(__('Le format de l\'adresse MAC est incorrect', __FILE__)); 
	    }

        if ($this->getIsEnable() == 1) {
            try {
                $debug = FALSE;
                $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
                if (config::byKey('additionalDebugLogs','kTwinkly') == "1") {
                    $debug = TRUE;
                }
                // Récupérations des informations sur l'équipement via l'API
                $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
    
                $info = $t->get_details();
                $this->setConfiguration('productcode',$info["product_code"]);
                $this->setConfiguration('productname',get_product_info($info["product_code"])["commercial_name"]);
                $this->setConfiguration('productimage',get_product_info($info["product_code"])["pack_preview"]);
                $this->setConfiguration('product',$info["product_name"]);
                $this->setConfiguration('devicename',$info["device_name"]);
                $this->setConfiguration('numberleds',$info["number_of_led"]);
                $this->setConfiguration('ledtype',$info["led_profile"]);
                $this->setConfiguration('hardwareid',$info["hw_id"]);
    
                $fwversion = $t->firmware_version();
                $this->setConfiguration('firmware',$fwversion);
                if (versionToInt($fwversion) >= versionToInt("2.5.5")) {
                    $this->setConfiguration('hwgen', '2');
                } else {
                    if (versionToInt($fwversion) >= versiontoInt("2.3.0")) {
                        $this->setConfiguration('hwgen', '1');
                    } else {
                        $this->setConfiguration('hwgen', '0');
                    }
                }
            } catch (Exception $e) {
                throw new Exception(__('Impossible de contacter le contrôleur Twinkly. Vérifiez les paramètres.', __FILE__));
            }
        }

	    $this->setLogicalId("Twinkly-" . $ip);
    }

    // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement 
    public function postSave() {
        // Création des commandes

	    $onCmd = $this->getCmd(null, "on");
	    if (!is_object($onCmd)) {
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
            $onCmd->setOrder(0);
            $onCmd->save();
        }

        $offCmd = $this->getCmd(null, "off");
        if (!is_object($offCmd)) {
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
        	$offCmd->setOrder(1);
        	$offCmd->save();
        }

        $brightnessCmd = $this->getCmd(null, "brightness");
        if (!is_object($brightnessCmd)) {
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
        	$brightnessCmd->setOrder(2);
        	$brightnessCmd->save();
        }
        
        $brightnessStateCmd = $this->getCmd(null, "brightness_state");
        if (!is_object($brightnessStateCmd)) {
            $brightnessStateCmd = new kTwinklyCmd();
        	$brightnessStateCmd->setName(__('Etat Luminosité', __FILE__));
        	$brightnessStateCmd->setEqLogic_id($this->getId());
        	$brightnessStateCmd->setLogicalId('brightness_state');
        	$brightnessStateCmd->setType('info');
        	$brightnessStateCmd->setSubType('numeric');
            $brightnessStateCmd->setGeneric_type('LIGHT_STATE');
            $brightnessStateCmd->setIsVisible(1);
        	$brightnessStateCmd->setOrder(3);
            $brightnessStateCmd->save();
        } 

        // Liens entre les commandes
        $onCmd->setValue($brightnessStateCmd->getId());
        $onCmd->save();
        $offCmd->setValue($brightnessStateCmd->getId());
        $offCmd->save();
        $brightnessCmd->setValue($brightnessStateCmd->getId());
        $brightnessCmd->save();

        $movieCmd = $this->getCmd(null, "movie");
        if (!is_object($movieCmd)) {
            $movieCmd = new kTwinklyCmd();
        	$movieCmd->setName(__('Animation', __FILE__));
        	$movieCmd->setEqLogic_id($this->getId());
        	$movieCmd->setLogicalId('movie');
        	$movieCmd->setType('action');
        	$movieCmd->setSubType('select');
        	$movieCmd->setIsVisible(1);
        	$movieCmd->setOrder(4);
        	$movieCmd->save();
        }

        $stateCmd = $this->getCmd(null, "state");
        if (!is_object($stateCmd)) {
            $stateCmd = new kTwinklyCmd();
            $stateCmd->setName(__('Etat', __FILE__));
            $stateCmd->setEqLogic_id($this->getId());
            $stateCmd->setLogicalId('state');
            $stateCmd->setType('info');
            $stateCmd->setSubType('string');
            $stateCmd->setIsVisible(1);
            $stateCmd->setOrder(5);
            $stateCmd->save();
        }

        if ($this->getConfiguration("hwgen") == "2") {
            $playlistCmd = $this->getCmd(null, "playlist");
            if (!is_object($playlistCmd)) {
                $playlistCmd = new kTwinklyCmd();
                $playlistCmd->setName(__('Playlist', __FILE__));
                $playlistCmd->setEqLogic_id($this->getId());
                $playlistCmd->setLogicalId('playlist');
                $playlistCmd->setType('action');
                $playlistCmd->setSubType('other');
                $playlistCmd->setIsvisible(1);
                $playlistCmd->setOrder(7);
                $playlistCmd->save();
            }
        }

        $refreshCmd = $this->getCmd(null, "refresh");
        if (!is_object($refreshCmd)) {
            $refreshCmd = new kTwinklyCmd();
        	$refreshCmd->setName(__('Refresh', __FILE__));
        	$refreshCmd->setEqLogic_id($this->getId());
        	$refreshCmd->setLogicalId('refresh');
        	$refreshCmd->setType('action');
        	$refreshCmd->setSubType('other');
        	$refreshCmd->setIsVisible(0);
        	$refreshCmd->setValue('refresh');
        	$refreshCmd->setOrder(6);
        	$refreshCmd->save();
        }
        if ($this->getChanged()){
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

    // Découverte automatique des équipements sur le réseau
    public static function discover()
    {
	    log::add('kTwinkly','debug','Démarrage de la recherche d\'équipements');
	    $devices = TwinklyString::discover();
	    log::add('kTwinkly','debug','Equipements trouvés : ' . print_r($devices,TRUE));

	    foreach($devices as $d) {
            // Logical id = Twinkly- suivi de l'adresse IP
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
            if (intval(config::byKey('refreshFrequency','kTwinkly')) > 0) {
                $eqLogic->setConfiguration('autorefresh', 1);
            } else {
                $eqLogic->setConfiguration('autorefresh', 0);
            }
            $eqLogic->setConfiguration('productcode',$d["details"]["product_code"]);
            $eqLogic->setConfiguration('productname',get_product_info($d["details"]["product_code"])["commercial_name"]);
            $eqLogic->setConfiguration('productimage',get_product_info($d["details"]["product_code"])["pack_preview"]);
            $eqLogic->setConfiguration('product',$d["details"]["product_name"]);
            $eqLogic->setConfiguration('devicename',$d["details"]["device_name"]);
            $eqLogic->setConfiguration('numberleds',$d["details"]["number_of_led"]);
            $eqLogic->setConfiguration('ledtype',$d["details"]["led_profile"]);
            $eqLogic->setConfiguration('hardwareid',$d["details"]["hw_id"]);

            $fwversion = $d["details"]["firmware_version"];
            $eqLogic->setConfiguration('firmware',$fwversion);
            if (versionToInt($fwversion) >= versionToInt("2.5.5")) {
                $eqLogic->setConfiguration('hwgen', '2');
            } else {
                if (versionToInt($fwversion) >= versiontoInt("2.3.0")) {
                    $eqLogic->setConfiguration('hwgen', '1');
                } else {
                    $eqLogic->setConfiguration('hwgen', '0');
                }
            }

		    $eqLogic->save();
	    }
    }

    // Vérifications de l'état du cron
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

    // Arrêt du cron
    public static function deamon_stop() {
        log::add('kTwinkly','debug','kTwinkly deamon_stop');
        $cron = cron::byClassAndFunction('kTwinkly','refreshstate');
        if (!is_object($cron)) {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->halt();
    }

    // Démarrage du cron
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

    // Rafraîchissement des valeurs par appel à l'API
    public static function refreshstate($_eqLogic_id = null) {
        if (self::$_eqLogics == null) {
            self::$_eqLogics = self::byType('kTwinkly');
        }

        $refreshFrequency = config::byKey('refreshFrequency','kTwinkly');

        foreach (self::$_eqLogics as &$eqLogic) {
            if ($_eqLogic_id != null && $_eqLogic_id != $eqLogic->getId()) {
                continue;
            }
            if ($eqLogic->getIsEnable() == 0){
                $eqLogic->refresh();
            }
            if ($eqLogic->getLogicalId() == '' || $eqLogic->getIsEnable() == 0) {
                continue;
            }

            // On vérifie si l'autorefresh est actif
            if (intval($refreshFrequency) > 0 && $eqLogic->getConfiguration('autorefresh')==1) {
                try {
                    $changed = false;
    
                    $ip = $eqLogic->getConfiguration('ipaddress');
                    $mac = $eqLogic->getConfiguration('macaddress');
    
                    $debug = FALSE;
                    $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
                    if (config::byKey('additionalDebugLogs','kTwinkly') == "1") {
                        $debug = TRUE;
                    }
                    $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
    
                    $state = $t->get_mode();
                    $brightness = $t->get_brightness();
    
                    $changed = $eqLogic->checkAndUpdateCmd('state', $state, false) || $changed;
                    $changed = $eqLogic->checkAndUpdateCmd('brightness_state', $brightness, false) || $changed;
    
                    if ($changed) {
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
    }

    public static function get_playlist($_id)
    {
        $eqLogic = eqLogic::byId($_id);
        $ip = $eqLogic->getConfiguration('ipaddress');
        $mac = $eqLogic->getConfiguration('macaddress');
        $debug = FALSE;
        $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
        if (config::byKey('additionalDebugLogs','kTwinkly') == "1") {
            $debug = TRUE;
        }
        $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
        $playlist = $t->get_current_playlist();
        return $playlist;
    }

    // Démarre le proxy de capture des animations
    public static function start_mitmproxy($_id) {
        log::add('kTwinkly','debug','Démarre mitmproxy pour eqId='.$_id);
        if (!kTwinkly::is_mitm_running()) {
            $eqLogic = eqLogic::byId($_id);

            $confdir = jeedom::getTmpFolder('kTwinkly');
            $tempfile = $confdir . '/tmovie_' . $_id;
            $pidfile = $confdir . '/mitmproxy.pid';
            $ipaddress = $eqLogic->getConfiguration('ipaddress');
            $hwgen = $eqLogic->getConfiguration("hwgen");

            if (config::byKey('additionalDebugLogs','kTwinkly') == "1") {
                $destlog = __DIR__ . '/../../../../log/kTwinkly_mitm';
            } else {
                $destlog = '/dev/null';
            }

            $mitmport = kTWinkly::get_mitm_port();
            
            if ($eqLogic->getConfiguration("hwgen")=="1") {
                $command = 'mitmdump -p ' . $mitmport . ' -s ' . __DIR__ . '/../../resources/mitmdump/twinkly_v1.py --set filename='.$tempfile.' --set ipaddress='.$ipaddress.' --set confdir="' . $confdir . '"';
            } else {
                $command = 'mitmdump -p ' . $mitmport . ' -s ' . __DIR__ . '/../../resources/mitmdump/twinkly_v2.py --set filename='.$tempfile.' --set ipaddress='.$ipaddress.' --set confdir="'.$confdir.'"';
            }
            log::add('kTwinkly','debug','Start MITM command = ' . $command);
            $pid = shell_exec(sprintf('%s > '.$destlog.' 2>&1 & echo $!', $command));
            sleep(kTwinkly::MITM_START_WAIT);

            if ($pid !== "" && kTwinkly::is_mitm_running($pid)) {
                file_put_contents($pidfile, $pid);
                log::add('kTwinkly','debug','mitmproxy démarré avec PID='.$pid);
                return true;
            } else {
                log::add('kTwinkly','error','Impossible de démarrer mitmproxy. Vérifiez l\'installation des dépendances ou un éventuel mesage d\'erreur : ' . file_get_contents('/tmp/kTwinkly_mitm.log'));
                //throw new Exception(__('Impossible de démarrer mitmproxy', __FILE__));
                return false;
            }
        } else {
            log::add('kTwinkly','debug','start_mitmproxy : mitmproxy est déjà démarré');
            return true;
        }
    }

    // Destruction d'un process
    private static function kill_process($_pid) {
        try {
            $result = shell_exec(sprintf('kill %d 2>&1', $_pid));
            if (!preg_match('/No such process/', $result)) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    // Arrêt du proxy de capture
    public static function stop_mitmproxy($_pid = "") {
        if ($_pid == "") {
            $pidfile = jeedom::getTmpFolder('kTwinkly') . '/mitmproxy.pid';
            if (file_exists($pidfile)) {
                $_pid = file_get_contents($pidfile);
            }
        }

        if ($_pid != "" and kTwinkly::is_mitm_running($_pid)) {
            // On essaye de tuer le process via le PID enregistré lors du démarrage
            log::add('kTwinkly','debug','Arret de mitmproxy en cours d\'exécution (pid=' . $_pid . ')');
            if (kTwinkly::kill_process($_pid)) {
                log::add('kTwinkly','debug','Process mitmproxy terminé');
                unlink($pidfile);
                return true;
            } else {
                log::add('kTwinkly','error','stop_mitmproxy : ' . $e->getMessage());
                return false;
            }
        } else {
            // Le PID n'es pas trouvé (cas d'un plantage). On essaye de retrouver le process par son nom
            log::add('kTwinkly','debug','Impossible de trouver le process mitm avec le PID enregistré. On recherche le process par son nom');
            $_pid = kTwinkly::find_mitm_proc();
            log::add('kTwinkly','debug','Process trouvé PID='.$_pid);

            if ($_pid != "") {
                if (kTwinkly::kill_process($_pid)) {
                    log::add('kTwinkly','debug','Process mitmproxy terminé');
                    return true;
                } else {
                    log::add('kTwinkly','error','Impossible de détruire le process mitmproxy PID='.$_pid);
                    return false;
                }
            } else {
                log::add('kTwinkly','debug','Process mitmproxy inexistant');
                return true;
            }
        }
    }

    // Vérification de l'état du proxy
    public static function is_mitm_running($_pid = NULL) {
        if ($_pid !== NULL) {
            log::add('kTwinkly','debug','is_mitm_running appelé avec PID='.$_pid);
            try {
                $result = shell_exec(sprintf('ps %d', $_pid));
                if (count(preg_split("/\n/", $result)) > 2) {
                    return true;
                }
            } catch(Exception $e) {}
        } else {
            log::add('kTwinkly','debug','is_mitm_running appelé sans PID');
            $_pid = kTwinkly::find_mitm_proc();
            if ($_pid != "") {
                return true;
            }
        }
        return false;
    }

    // Recherche du process par son nom
    public static function find_mitm_proc() {
        //$mitmcommand = preg_quote(kTwinkly::get_mitm_command(),'/');
        $mitmcommand = 'mitmdump';
        $shellcmd="ps hf -opid,cmd -C mitmdump | grep '" . $mitmcommand . "' | awk '$2 !~ /^[|\\\\]/ { print $1 }'";
        return trim(shell_exec($shellcmd));
    }

    // Récupère le port paramétré dans la config, ou utilise un valeur par défaut
    public static function get_mitm_port() {
        $mitmport = config::byKey('mitmPort','kTwinkly');
        if ($mitmport == '') {
            $mitmport = kTwinkly::MITM_DEFAULT_PORT;
        }
        return $mitmport;
    }

    // Renvoie l'image de l'équipement
    // La table de mapping et les images sont celles fournies par Twinkly dans l'application Android
    public function getImage() {
        $plugin = plugin::byId($this->getEqType_name());
        $defaultImage = $plugin->getPathImgIcon();
        $deviceImage = $this->getConfiguration("productimage");
        if ($deviceImage && file_exists(__DIR__ . '/../config/images/'.$deviceImage)) {
            return 'plugins/kTwinkly/core/config/images/'.$deviceImage;
        } else {
            return 'plugins/kTwinkly/core/config/images/default.jpg';
        }
    }

    // Installation des dépendances (via shell)
    public static function dependancy_install() {
        log::remove(__CLASS__.'_update');
        return array(
            'script' => __DIR__ . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder(__CLASS__) . '/dependencies',
            'log' => log::getPathToLog(__CLASS__.'_update')
        );
    }

    // Informations sur l'avancement de l'installation des dépendances
    public static function dependancy_info() {
        $return = array();
        $return['log'] = log::getPathToLog(__CLASS__.'_update');
        $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependencies';
        if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependencies')) {
            $return['state'] = 'in_progress';
        } else {
            if (exec(system::getCmdSudo() . ' python3.7 -m pip list | grep -Ec "mitmproxy"') < 1) {
                $return['state'] = 'nok';
            } else {
                $return['state'] = 'ok';
            }
        }
        return $return;
    }
}

