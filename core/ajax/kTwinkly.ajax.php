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

function add_movie_to_listValue($eqId, $oldlist, $newitem)
{

	$displayname = substr($newitem, strlen($eqId) + 9);

	$newlist = "";
	if($oldlist) {
		$newlist = $oldlist . ";";
	}
	$newlist .= $newitem . '|' . $displayname;
	return $newlist;
}

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }
    require_once __DIR__ . '/../../core/class/Twinkly.class.php';
    
  /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
    En V3 : indiquer l'argument 'true' pour contrôler le token d'accès Jeedom
    En V4 : autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
  */  
    ajax::init(array('uploadMovie','saveMovie','deleteMovie','discoverDevices','updateMqtt'));

    if (init('action') == 'uploadMovie') {
	    $id = init(id);

	    $eqLogic = eqLogic::byId($id);

	    //log::add('kTwinkly','debug','Upload movie equipement ' . $id . ' ip=' . $eqLogic->getConfiguration('ipaddress'));

            if (!is_object($eqLogic)) {
              throw new Exception(__('EqLogic inconnu verifié l\'id', __FILE__));
            }
            if (!isset($_FILES['file'])) {
              throw new Exception(__('Aucun fichier trouvé. Vérifiez paramètre PHP (post size limit)', __FILE__));
            }

	    $filename = $_FILES['file']['name'];
            $extension = strtolower(strrchr($filename, '.'));

            if (!in_array($extension, array('.bin'))) {
              throw new Exception('Extension du fichier non valide (autorisé .bin) : ' . $extension);
            }

            preg_match("/.*_(\d+)_(\d+)_(\d+)\.bin$/", $filename, $matches);
            if(sizeof($matches) != 4) {
              throw new Exception('Format du nom du fichier incorrect. Utilisez xxxx_LEDS_FRAMES_DELAY.bin');
            }

	    $nbleds = $eqLogic->getConfiguration("numberleds");
	    if($matches[1] != $nbleds) {
	      throw new Exception("Nombre de leds incompatible. Le fichier indique $matches[1] leds, l'équipement a $nbleds leds.");
	    }

            if (filesize($_FILES['file']['tmp_name']) > 1000000) {
              throw new Exception(__('Le fichier est trop gros (maximum 1mo)', __FILE__));
            }

            $filepath = dirname(__FILE__) . '/../../data/twinkly_' . $id . '_' . $filename;
	    log::add('kTwinkly','debug',"upload d'un fichier pour id $id : $filepath");

            file_put_contents($filepath, file_get_contents($_FILES['file']['tmp_name']));

	    $movieCmd = $eqLogic->getCmd(null, 'movie');
	    $oldList = $movieCmd->getConfiguration('listValue');

	    $newList = add_movie_to_listValue($id, $oldList, basename($filepath));
	    log::add('kTwinkly','debug','Nouvelle liste d\'animations pour eq ' . $id . ' => ' . $newList);
	    $movieCmd->setConfiguration('listValue', $newList);
	    $movieCmd->save();
	    $eqLogic->save();
	    $eqLogic = kTwinkly::byId($eqLogic->getId());

            ajax::success();
    }

    if (init('action') == 'deleteMovie') {
	    $id = init(id);
	    $eqLogic = eqLogic::byId($id);

	    $deletedfilenames = $_POST["deletedFilenames"];
	    $filenames = $_POST["files"];
            $labels = $_POST["labels"];

            $newList = "";
            for($i=0; $i < sizeof($filenames); $i++) {
	        if(in_array($filenames[$i], $deletedfilenames)) {
		  $filepath = dirname(__FILE__) . '/../../data/' . $filenames[$i];
		  log::add('kTwinkly','debug','Delete file => ' . $filepath);
		  unlink($filepath);
		} else {
                  $newList .= ';' . $filenames[$i] . '|' . $labels[$i];
		}
            }
            $newList = substr($newList, 1);

	    $movieCmd = $eqLogic->getCmd(null, 'movie');
	    $oldList = $movieCmd->getConfiguration('listValue');
	    $movieCmd->setConfiguration('listValue', $newList);
	    $movieCmd->save();
	    $eqLogic->save();
	    
	    //$eqLogic = kTwinkly::byId($eqLogic->getId());
	    ajax::success();
    }

    if (init('action') == 'saveMovie') {
	    $filenames = $_POST["files"];
	    $labels = $_POST["labels"];

	    $newList = "";
	    for($i=0; $i < sizeof($filenames); $i++) {
		$newList .= ';' . $filenames[$i] . '|' . $labels[$i];
	    }
	    $newList = substr($newList, 1);

	    $id = init(id);
	    $eqLogic = eqLogic::byId($id);
	    $movieCmd = $eqLogic->getCmd(null, 'movie');

	    log::add('kTwinkly','debug','savemovie eq=' . $id . ' / cmd=' . $movieCmd->getId() . ' => new listvalue ' . $newList);

	    $movieCmd->setConfiguration('listValue', $newList);
	    $movieCmd->save();
	    $eqLogic->save();

	    ajax::success();
    }

    if (init('action') == 'discoverDevices') {
	    kTwinkly::discover();
	    ajax::success();
    }

    if (init('action') == 'updateMqtt') {

	    $id = init(id);
	    $eqLogic = eqLogic::byId($id);
	    $ip = $eqLogic->getConfiguration("ipaddress");
	    $mac = $eqLogic->getConfiguration("macaddress");

	    $broker_ip = $_POST["mqttBroker"];
	    $broker_port = $_POST["mqttPort"];
	    $client_id = $_POST["mqttClientId"];
	    $mqtt_user = $_POST["mqttUser"];

	    $t = new Twinkly($ip, $mac, FALSE);
	    //$t->set_mqtt_configuration($broker_ip, $broker_port, $client_id, $mqtt_user);


	    log::add('kTwinkly','debug',"Mise à jour MQTT $ip / $mac => $broker_ip:$broker_port");
	    ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondant à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}

