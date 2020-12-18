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

require_once __DIR__  . '/../class/kTwinkly_utils.php';

function add_movie_to_listValue($oldlist, $newitem, $displayname=NULL)
{
    if (!$displayname) {
	    $displayname = substr($newitem, 0, strlen($newitem)-4);
    }

	$newlist = "";
	if ($oldlist) {
		$newlist = $oldlist . ";";
	}
	$newlist .= $newitem . '|' . $displayname;
	return $newlist;
}

function recupere_movies($id) {
    log::add('kTwinkly','debug',"Récupération des captures de l'équipement " . $id);

    $result = 0;

    $eqLogic = eqLogic::byId($id);
    $tempdir = jeedom::getTmpFolder('kTwinkly');

    $movieCmd = $eqLogic->getCmd(null, 'movie');
    $movieList = $movieCmd->getConfiguration('listValue');

    log::add('kTwinkly','debug','Génération = ' . $eqLogic->getConfiguration("hwgen"));
    if ($eqLogic->getConfiguration("hwgen") == "1") {
        // GEN 1
        $capturedMask = $tempdir . '/tmovie_' . $id. '-*.bin';
        log::add('kTwinkly','debug','Masque de recherche : ' . $capturedMask);

        $capturedMovies = glob($capturedMask);
        if (count($capturedMovies) == 0) {
            log::add('kTwinkly','info',"Aucun fichier n'a été trouvé. Vérifiez le paramétrage du proxy sur le smartphone.");
        }


        foreach($capturedMovies as $m) {
            $filename = substr($m, strlen($tempdir)+1);
            $jsonfilename = substr($filename, 0, strlen($filename)-4) . '.json';
            if (file_exists($tempdir . '/' . $jsonfilename)) {
                log::add('kTwinkly','debug','Fichier trouvé : '. $filename . ' & ' . $jsonfilename);

                $newbasefile = 'movie_' . $id . '_' . date('YmdHis');
                $zipfilename = $newbasefile . '.zip';
                $zippath = $tempdir . '/' . $zipfilename;

                $zip = new ZipArchive();
                if ($zip->open($zippath, ZipArchive::CREATE)) {
                    log::add('kTwinkly','debug','Création zip : ' . $zippath);
                    log::add('kTwinkly','debug','Ajout dans zip : ' . $m . ' renommé en ' . $newbasefile.'.bin');
                    $zip->addFile($m, $newbasefile . '.bin');
                    log::add('kTwinkly','debug','Ajout dans zip : ' . $tempdir.'/'.$jsonfilename  . ' renommé en ' . $newbasefile.'.json');
                    $zip->addFile($tempdir . '/' . $jsonfilename, $newbasefile . '.json');
                    $zip->close();

                    // Déplace le fichier zip dans le dossier data
                    rename($zippath, __DIR__ . '/../../data/' . $zipfilename);

                    // Supprime les fichiers bin et json
                    unlink($m);
                    unlink($tempdir . '/' . $jsonfilename);

                    // Met à jour la liste déroulante
                    $movieList = add_movie_to_listValue($movieList, $zipfilename, $newbasefile);
                    log::add('kTwinkly','debug',"Nouveau fichier ajouté : " . $zippath);
                    $result += 1;
                } else {
                    log::add('kTwinkly','error','Impossible de créer le fichier zip '.$zippath);
                }
            } else {
                log::add('kTwinkly','error',"Fichier $jsonfilename non trouvé");
            }
        }
    } else { 
        // GEN 2
        $capturedMask = $tempdir . '/tmovie_' . $id. '-*.bin';
        log::add('kTwinkly','debug','Masque de recherche : ' . $capturedMask);

        $capturedMovies = glob($capturedMask);
        if (count($capturedMovies) == 0) {
            log::add('kTwinkly','info',"Aucun fichier n'a été trouvé. Vérifiez le paramétrage du proxy sur le smartphone.");
        }

        foreach($capturedMovies as $m) {
            $filename = substr($m, strlen($tempdir)+1);
            $jsonfilename = substr($filename, 0, strlen($filename)-4) . '.json';
            if (file_exists($tempdir . '/' . $jsonfilename)) {
                log::add('kTwinkly','debug','Fichier trouvé : '. $filename . ' & ' . $jsonfilename);

                $json = json_decode(file_get_contents($tempdir.'/'.$jsonfilename), TRUE);
                $moviename = $json["name"];
                $newbasefile = 'movie_' . $id . '_' . date('YmdHis') . '_' . sanitize_filename($moviename);
                $zipfilename = $newbasefile . '.zip';
                $zippath = $tempdir . '/' . $zipfilename;

                $zip = new ZipArchive();
                if ($zip->open($zippath, ZipArchive::CREATE)) {
                    log::add('kTwinkly','debug','Création zip : ' . $zippath);
                    log::add('kTwinkly','debug','Ajout dans zip : ' . $m . ' renommé en ' . $newbasefile.'.bin');
                    $zip->addFile($m, $newbasefile . '.bin');
                    log::add('kTwinkly','debug','Ajout dans zip : ' . $tempdir.'/'.$jsonfilename  . ' renommé en ' . $newbasefile.'.json');
                    $zip->addFile($tempdir . '/' . $jsonfilename, $newbasefile . '.json');
                    $zip->close();

                    // Déplace le fichier zip dans le dossier data
                    rename($zippath, __DIR__ . '/../../data/' . $zipfilename);

                    // Supprime les fichiers bin et json
                    unlink($m);
                    unlink($tempdir . '/' . $jsonfilename);

                    // Met à jour la liste déroulante
                    $movieList = add_movie_to_listValue($movieList, $zipfilename, $moviename);
                    log::add('kTwinkly','debug',"Nouveau fichier ajouté : " . $moviename . ' => ' . $zippath);
                    $result += 1;
                } else {
                    log::add('kTwinkly','error','Impossible de créer le fichier zip '.$zippath);
                }
            } else {
                log::add('kTwinkly','error',"Fichier $jsonfilename non trouvé");
            }
        }
    }
    if ($result > 0) {
        log::add('kTwinkly','debug', $result . ' nouvelles animations récupérées');
        log::add('kTwinkly','debug','Nouvelle liste d\'animations : ' . $movieList);
        $movieCmd->setConfiguration('listValue', $movieList);
        $movieCmd->save();
        $eqLogic->save();
        $eqLogic->refreshWidget();
    } else {
        if ($result == 0) {
            log::add('kTwinkly','debug', 'Aucun fichier n\'a été enregistré par le proxy.');
        }
    }
    return $result;
}

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }
    require_once __DIR__ . '/../../core/class/TwinklyString.class.php';
    
    /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
        En V3 : indiquer l'argument 'true' pour contrôler le token d'accès Jeedom
        En V4 : autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
    */  
    ajax::init(array('uploadMovie','saveMovie','deleteMovie','discoverDevices','updateMqtt'));

    if (init('action') == 'uploadMovie') {
        $id = init(id);
        $eqLogic = eqLogic::byId($id);

        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu verifié l\'id', __FILE__));
        }

        if (!isset($_FILES['file'])) {
            throw new Exception(__('Aucun fichier trouvé. Vérifiez paramètre PHP (post size limit)', __FILE__));
        }

        $nbleds = $eqLogic->getConfiguration("numberleds");
        $hwgen = $eqLogic->getConfiguration("hwgen");

        $filename = $_FILES['file']['name'];
        $extension = strtolower(strrchr($filename, '.'));

        log::add('kTwinkly','debug',"Tentative d'upload d'un fichier pour l'equipement " . $id . ' : ' . $filename);

        if (!in_array($extension, array('.zip'))) {
            throw new Exception(__("L'extension du fichier est invalide (zip uniquement)", __FILE__));
        }

        if (filesize($_FILES['file']['tmp_name']) > 1000000) {
            throw new Exception(__('Le fichier est trop gros (maximum 1mo)', __FILE__));
        }

        $zip = new ZipArchive();
        if ($zip->open($_FILES['file']['tmp_name'])) {
            log::add('kTwinkly','debug','Analyse du fichier zip en cours');
            $index_bin = -1;
            $index_json = -1;
            $is_gen2 = FALSE;

            $cnt=0;
            for ($i=0; $i<$zip->numFiles; $i++) {
                $cnt++;
                $zfilename = $zip->statIndex($i)["name"];
                if (preg_match('/bin$/',strtolower($zfilename))) {
                    $index_bin = $i;
                }
                if (preg_match('/json$/',strtolower($zfilename))) {
                    $index_json = $i;
                }
            }
            log::add('kTwinkly','debug',"Analyse terminée. Fichiers dans le zip=$cnt : BIN_index=$index_bin JSON_index=$index_json");

            if ($cnt==2 && $index_bin >=0 && $index_json >= 0) {
                $json = json_decode($zip->getFromIndex($index_json), TRUE);
                if ($json["name"]) {
                    $is_gen2 = TRUE;
                }
                if (($hwgen == "1" && $is_gen2) || ($hwgen == "2" && !$is_gen2)) {
                    $zip->close();
                    throw new Exception(__("Le format de l'animation ne correspond pas au type de guirlande", __FILE__));
                } else {
                    if ($is_gen2) {
                        // Upload fichier GEN2
                        log::add('kTwinkly','debug',"Upload d'un fichier GEN2");
                        if ($json["leds_per_frame"] != $nbleds) {
                            $zip->close();
                            log::add('kTwinkly','error',"Le nombre de leds de l'animation (".$json["leds_per_frame"].") ne correspond pas à celui de la guirlande ($nbleds)");
                            throw new Exception(__("Le nombre de leds de l'animation ne correspond pas à celui de la guirlande", __FILE__));
                        }

                        $destfilepath = dirname(__FILE__) . '/../../data/movie_' . $id . '_' . date('YmdHis') . '_' . sanitize_filename($json["name"]) . '.zip';
                        log::add('kTwinkly','debug',"upload d'un fichier pour id $id : $destfilepath");
                        file_put_contents($destfilepath, file_get_contents($_FILES['file']['tmp_name']));

                        $movieCmd = $eqLogic->getCmd(null, 'movie');
                        $oldList = $movieCmd->getConfiguration('listValue');
                        $newList = add_movie_to_listValue($oldList, basename($destfilepath), $json["name"]);
                        log::add('kTwinkly','debug','Nouvelle liste d\'animations pour eq ' . $id . ' => ' . $newList);
                    } else {
                        // Upload fichier GEN1
                        log::add('kTwinkly','debug',"Upload d'un fichier GEN1");
                        if ($json["leds_number"] != $nbleds) {
                            $zip->close();
                            throw new Exception(__("Le nombre de leds de l'animation ne correspond pas à celui de la guirlande", __FILE__));
                        }
                        $destfilepath = dirname(__FILE__) . '/../../data/movie_' . $id . '_' . date('YmdHis') . '.zip';
                        log::add('kTwinkly','debug',"upload d'un fichier pour id $id : $destfilepath");
                        file_put_contents($destfilepath, file_get_contents($_FILES['file']['tmp_name']));

                        $movieCmd = $eqLogic->getCmd(null, 'movie');
                        $oldList = $movieCmd->getConfiguration('listValue');
                        $newList = add_movie_to_listValue($oldList, basename($destfilepath), substr($filename, 0, strlen($filename)-4)); 
                        log::add('kTwinkly','debug','Nouvelle liste d\'animations pour eq ' . $id . ' => ' . $newList);
                    }
                    $movieCmd->setConfiguration('listValue', $newList);
                    $movieCmd->save();
                    $eqLogic->save();
                    $eqLogic->refreshWidget();
                }
            } else {
                $zip->close();
                throw new Exception(__("Le fichier zip ne doit contenir qu'un fichier bin et un fichier json", __FILE__));
            }
            $zip->close();
        } else {
            throw new Exception(__("Le fichier chargé n'est pas un fichier zip valide", __FILE__));
        }

        ajax::success();
    }

    if (init('action') == 'deleteMovie') {
	    $id = init(id);
	    $eqLogic = eqLogic::byId($id);

	    $deletedfilenames = $_POST["selectedFilenames"];
	    $filenames = $_POST["files"];
        $labels = $_POST["labels"];

        $newList = "";
        for($i=0; $i < sizeof($filenames); $i++) {
	        if (in_array($filenames[$i], $deletedfilenames)) {
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
	    $eqLogic->refreshWidget();
	    
	    ajax::success();
    }

    if (init('action') == 'saveMovie') {
	    $filenames = $_POST["files"];
	    $labels = $_POST["labels"];

	    $newList = "";
	    for ($i=0; $i < sizeof($filenames); $i++) {
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
	    $eqLogic->refreshWidget();

	    ajax::success();
    }

    if (init('action') == 'createPlaylist') {
        $id = init(id);
        $eqLogic = eqLogic::byId($id);

        $ip = $eqLogic->getConfiguration("ipaddress");
        $mac = $eqLogic->getConfiguration("macaddress");

        $playlist = init(playlist);

        $movies = [];
        foreach ($playlist as $item) {
            log::add('kTwinkly','debug','adding playlist item with file ' . __DIR__ . '/../../data/' . $item["filename"] . ' and duration = ' . $item["duration"]);
            $playlist_item = create_playlist_item(__DIR__ . '/../../data/' . $item["filename"], $item["duration"]);
            $movies[] = $playlist_item;
        }

        if (sizeof($movies) > 0) {
            $t = new TwinklyString($ip, $mac, FALSE);
            if ($t->create_new_playlist($movies)) {
                ajax::success("La playlist de " . sizeof($movies) . " élements a été créée avec succès.");
                return;
            }
        }
        ajax::error("Aucun élément n'a été ajouté à la playlist");
    }

    if (init('action') == 'deletePlaylist') {
        $id = init(id);
        $eqLogic = eqLogic::byId($id);

        $ip = $eqLogic->getConfiguration("ipaddress");
        $mac = $eqLogic->getConfiguration("macaddress");

        $t = new TwinklyString($ip, $mac, FALSE);
        $t->delete_playlist();

        ajax::success("La playlist a été effacée.");
    }

    if (init('action') == 'clearMemory') {
        $id = init(id);
        $eqLogic = eqLogic::byId($id);

        $ip = $eqLogic->getConfiguration("ipaddress");
        $mac = $eqLogic->getConfiguration("macaddress");

        $t = new TwinklyString($ip, $mac, FALSE);
        $t->set_mode('off');
        $t->delete_movies();

        ajax::success("Les animations en mémoire ont été supprimées.");
    }


    if (init('action') == 'changeproxystate') {
        $id = init(id);
        $eqLogic = eqLogic::byId($id);
        $tempdir = jeedom::getTmpFolder('kTwinkly');

        $result = array();
        $oldstate = init('proxy_enabled');

        if ($oldstate == "1") {
            $newstate = "0";
            log::add('kTwinkly','debug','Tentative d\'arrêt de mitmproxy');
            if (!kTwinkly::stop_mitmproxy()) {
                $result["proxy_enabled"] = "1";
                ajax::error(__("Erreur lors de l'arrêt de mitmproxy", __FILE__));
            }

            log::add('kTwinkly','debug',"Récupération des captures de l'équipement " . $id);
            $newmovies = recupere_movies($id);
            $result["proxy_enabled"] = "0";
            $result["newmovies"] = $newmovies;
            $eqLogic->setConfiguration("proxy_enabled", "0");
            $eqLogic->save();
            ajax::success($result);
        } else {
            log::add('kTwinkly','debug','Tentative de démarrage de mitmproxy');
            if (kTwinkly::start_mitmproxy($id)) {
                $result["proxy_pid"] = kTwinkly::find_mitm_proc();
                $result["proxy_port"] = kTwinkly::get_mitm_port(); 
                $result["proxy_enabled"] = "1";
                $eqLogic->setConfiguration("proxy_enabled", "1");
                $eqLogic->save();
                ajax::success($result);
            } else {
                $result["proxy_enabled"] = "0";
                ajax::error(__('Impossible de démarrer mitmproxy', __FILE__));
            }
        }
    }

    // Arrete le proxy et ignore les fichiers capturés : appelé lors de la fermeture de la modale
    if (init('action') == 'stopProxy') {
        $id = init(id);
        $eqLogic = eqLogic::byId($id);
        if (kTwinkly::is_mitm_running()) {
            kTwinkly::stop_mitmproxy();
        }
        $eqLogic->setConfiguration("proxy_enabled", "0");
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

	    $t = new TwinklyString($ip, $mac, FALSE);
	    //$t->set_mqtt_configuration($broker_ip, $broker_port, $client_id, $mqtt_user);


	    log::add('kTwinkly','debug',"(désactivé) Mise à jour MQTT $ip / $mac => $broker_ip:$broker_port");
	    ajax::success();
    }

    if (init('action') == 'copiecaptures') {
        $id = init('id');
        recupere_movies($id);
        ajax::success();
    }

    if (init('action') == 'getDetailedPlaylist') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);

        $movieCmd = $eqLogic->getCmd(null, 'movie');
        $lv = $movieCmd->getConfiguration('listValue');

        $moviesList = array();

        if ($lv != "") {
            $lvs = explode(';',$lv);
            foreach ($lvs as $lvi) {
                $item = explode('|',$lvi);
                $mli = array('filename' => $item[0], 'title' => $item[1]);
                $zipfile = __DIR__ . '/../../data/' . $mli['filename'];
                $zip = new ZipArchive();
                if ($zip->open($zipfile)) {
                    for ($i=0; $i<$zip->numFiles; $i++) {
                        $zfilename = $zip->statIndex($i)["name"];
                        if (preg_match('/json$/',strtolower($zfilename))) {
                            $json = json_decode($zip->getFromIndex($i), TRUE);
                            $unique_id = $json['unique_id'];
                            break;
                        }
                    }
                }
                $moviesList[] = array('unique_id' => $unique_id, 'filename' => $item[0], 'title' => $item[1]);
            }
        }

        $playlist = kTwinkly::get_playlist($id);
        $result = array('movies' => $moviesList, 'playlist' => $playlist);

        ajax::success($result);
    }

    throw new Exception(__('Aucune méthode correspondant à : ', __FILE__) . init('action'));
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
