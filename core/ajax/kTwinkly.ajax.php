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

                $json = json_decode(file_get_contents($tempdir.'/'.$jsonfilename), true);
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
    ajax::init(array('uploadMovie','saveMovie','deleteMovie','createPlaylist','deletePlaylist','savePlaylist','loadPlaylist','downloadPlaylist','uploadPlaylist','clearMemory','changeproxystate','stopProxy','discoverDevices','updateMqtt','copiecaptures','getDetailedPlaylist','exportAll','importAll'));

    $debug = false;
    $additionalDebugLog = __DIR__ . '/../../../../log/kTwinkly_debug';
    if (config::byKey('additionalDebugLogs','kTwinkly') == "1") {
        $debug = true;
    }
    
    if (init('action') == 'uploadMovie') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);

        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu verifiez l\'id', __FILE__));
        }

        if (!isset($_FILES['file'])) {
            throw new Exception(__('Aucun fichier trouvé. Vérifiez paramètre PHP (post size limit)', __FILE__));
        }

        $nbleds = $eqLogic->getConfiguration("numberleds");
        $hwgen = $eqLogic->getConfiguration("hwgen");

        $filename = $_FILES['file']['name'];
        $extension = strtolower(strrchr($filename, '.'));

        log::add('kTwinkly','debug',"Tentative d'upload d'un fichier pour l'equipement GEN" . $hwgen . ' ' . $id . ' : ' . $filename);

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
            $is_gen2 = false;

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
                $json = json_decode($zip->getFromIndex($index_json), true);
                //if ($json["hardwareid"]) {
                if ($json["unique_id"]) {
                    $is_gen2 = true;
                }
                if (($hwgen == "1" && $is_gen2) || ($hwgen == "2" && !$is_gen2)) {
                    $zip->close();
                    throw new Exception(__("Le format de l'animation ne correspond pas au type de guirlande", __FILE__));
                } else {
                    if ($is_gen2) {
                        // Fichier déjà existant dans le cache
                        $movieCache = get_movie_cache($id);

                        if(get_movie_from_cache($movieCache, $json["unique_id"]) !== null) {
                            throw new Exception(__("Une animation portant le même identifiant est déjà présent dans la liste", __FILE__));
                        }

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
                        if($json["name"] !== NULL && $json["name"]!== "") {
                            $destfilepath = dirname(__FILE__) . '/../../data/movie_' . $id . '_' . date('YmdHis') . '_' . sanitize_filename($json["name"]) . '.zip';
                        } else {
                            $destfilepath = dirname(__FILE__) . '/../../data/movie_' . $id . '_' . date('YmdHis') . '.zip';
                        }
                        
                        log::add('kTwinkly','debug',"upload d'un fichier pour id $id : $destfilepath");
                        file_put_contents($destfilepath, file_get_contents($_FILES['file']['tmp_name']));

                        $movieCmd = $eqLogic->getCmd(null, 'movie');
                        $oldList = $movieCmd->getConfiguration('listValue');
                        if($json["name"] !== NULL && $json["name"]!== "") {
                            $newList = add_movie_to_listValue($oldList, $json["name"]); 
                        } else {
                            $newList = add_movie_to_listValue($oldList, basename($destfilepath), substr($filename, 0, strlen($filename)-4)); 
                        }
                        
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
	    $id = init('id');
	    $eqLogic = eqLogic::byId($id);

        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu verifier l\'id', __FILE__));
        }

        $deletedMovieIDs = $_POST["selectedFilenames"];

        if(count($deletedMovieIDs) > 0 ) {
            log::add('kTwinkly','debug','Supression des ' . count($deletedMovieIDs) . ' animations sélectionnées de l\'équipement id=' . $id);

            $movieCache = get_movie_cache($id);

            $newCache = array();
            foreach($movieCache as $m) {
                // On parcourt toutes les animations du cache
                if(in_array($m["unique_id"], $deletedMovieIDs)) {
                    // On supprime l'animation courante du disque et du cache
                    $filepath = dirname(__FILE__) . '/../../data/' . $m["file"];
                    log::add('kTwinkly','debug','Suppression animation => ' . $filepath);
                    unlink($filepath);  
                } else {
                    // On garde l'animation dans le cache
                    $newCache[] = $m;
                }
            }

            file_put_contents($movieCacheFile, json_encode($newCache));

            $movieCmd = $eqLogic->getCmd(null, 'movie');
            $movieCmd->setConfiguration('listValue', convert_cache_to_listvalue($newCache));
            $movieCmd->save();
            $eqLogic->save();
            $eqLogic->refreshWidget();
            ajax::success(count($deletedMovieIDs) . " animation(s) supprimée(s)");
        } else {
            log::add('kTwinkly','debug','Aucune animation à supprimer n\'a été sélectionnée');
            throw new Exception(__('Aucun fichier sélectionné', __FILE__));
        }
    }

    if (init('action') == 'saveMovie') {
	    $filenames = $_POST["files"];
	    $labels = $_POST["labels"];

        $id = init('id');
	    $eqLogic = eqLogic::byId($id);
	    $movieCmd = $eqLogic->getCmd(null, 'movie');

        $oldlist = $movieCmd->getConfiguration('listValue');
        if($oldlist != NULL && $oldlist !== "") {
            $oldlistTable = array();
            foreach(explode(';',$oldlist) as $i) {
                $tmp = explode('|',$i);
                $item = array();
                array_push($oldlistTable, array("name" => $tmp[1], "zip" => $tmp[0]));
            }
        }
	    
	    $newList = "";
        $changed = array();

	    for ($i=0; $i < sizeof($filenames); $i++) {
            $oldid = array_search($filenames[$i], array_column($oldlistTable, 'zip'));
            $oldname = $oldlistTable[$oldid]["name"]; // Trouve l'ancien nom correspondant au zip

            if($oldname !== $labels[$i]) {
                // Le nom a été changé, il faut mettre à jour l'info dans le json
                array_push($changed, array("zip" => $filenames[$i], "old" => $oldname, "new" => $labels[$i]));
            }
		    $newList .= ';' . $filenames[$i] . '|' . $labels[$i];
	    }
	    $newList = substr($newList, 1);

	    log::add('kTwinkly','debug','savemovie eq=' . $id . ' / cmd=' . $movieCmd->getId() . ' => new listvalue ' . $newList);

        if(count($changed) > 0) {
            kTwinkly::update_titles($id, $changed);
        }

	    $movieCmd->setConfiguration('listValue', $newList);
	    $movieCmd->save();
	    $eqLogic->save();
	    $eqLogic->refreshWidget();

	    ajax::success();
    }

    // Envoie la playlist à la guirlande
    if (init('action') == 'createPlaylist') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);

        $ip = $eqLogic->getConfiguration("ipaddress");
        $mac = $eqLogic->getConfiguration("macaddress");
        $clearmemory = $eqLogic->getConfiguration("clearmemory");

        $playlist = init(playlist);

        $movies = [];
        foreach ($playlist as $item) {
            log::add('kTwinkly','debug','adding playlist item with file ' . __DIR__ . '/../../data/' . $item["filename"] . ' unique_id = ' . $item["unique_id"] . ' and duration = ' . $item["duration"]);
            $playlist_item = create_playlist_item(__DIR__ . '/../../data/' . $item["filename"], $item["duration"]);
            $movies[] = $playlist_item;
        }

        if (sizeof($movies) > 0) {
            $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));

            if ($clearmemory == 1) {
                log::add('kTwinkly','debug','Clearing current movies in memory before creating the playlist');
                $t->set_mode('off');
                $t->delete_movies();
            }
            if ($t->create_new_playlist($movies)) {
                //$eqLogic->setConfiguration('auth_token', $t->get_token());
                $eqLogic->refreshstate($id, true);
                ajax::success("La playlist de " . sizeof($movies) . " élements a été envoyée avec succès.");
                return;
            }
            $eqLogic->refreshstate($id, true);
        }
        ajax::error("Aucun élément n'a été ajouté à la playlist");
    }

    if (init('action') == 'deletePlaylist') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);

        $ip = $eqLogic->getConfiguration("ipaddress");
        $mac = $eqLogic->getConfiguration("macaddress");

        $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
        $t->delete_playlist();
        $eqLogic->refreshstate($id, true);
        $playlistFile = __DIR__ . '/../../data/playlist_' . $id . '_01.json';
        if(file_exists($playlistFile)) {
            unlink($playlistFile);
        }
        ajax::success("La playlist a été effacée.");
    }

    if (init('action') == 'savePlaylist') {
        $id = init('id');
        $movies = init(playlist);

        $movieCache = get_movie_cache($id);
        $playlist = array();
        foreach ($movies as $item) {
            $movieItem = get_movie_from_cache($movieCache, $item["unique_id"]);
            array_push($playlist, array("unique_id" => $item["unique_id"], "file" => $item["file"], "name" => $movieItem["name"], "duration" => $item["duration"]));
        }

        $json = json_encode($playlist);
        $playlistFile = __DIR__ . '/../../data/playlist_' . $id . '_01.json';
        file_put_contents($playlistFile, $json);
        ajax::success("La playlist a été enregistrée.");
    }

    if (init('action') == 'clearMemory') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);

        $ip = $eqLogic->getConfiguration("ipaddress");
        $mac = $eqLogic->getConfiguration("macaddress");

        $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
        $t->set_mode('off');
        $t->delete_movies();
        //$eqLogic->setConfiguration('auth_token', $t->get_token());

        ajax::success("Les animations en mémoire ont été supprimées.");
    }


    if (init('action') == 'changeproxystate') {
        $id = init('id');
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
        $id = init('id');
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
	    $id = init('id');
	    $eqLogic = eqLogic::byId($id);
	    $ip = $eqLogic->getConfiguration("ipaddress");
	    $mac = $eqLogic->getConfiguration("macaddress");

	    $broker_ip = $_POST["mqttBroker"];
	    $broker_port = $_POST["mqttPort"];
	    $client_id = $_POST["mqttClientId"];
	    $mqtt_user = $_POST["mqttUser"];

	    $t = new TwinklyString($ip, $mac, $debug, $additionalDebugLog, jeedom::getTmpFolder('kTwinkly'));
	    //$t->set_mqtt_configuration($broker_ip, $broker_port, $client_id, $mqtt_user);
        //$eqLogic->setConfiguration('auth_token', $t->get_token());

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

        /* // ANCIENNE VERSION - A SUPPRIMER LOSQUE LA NOUVELLE SERA COMPLETEMENT FINALISEE
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
                            $json = json_decode($zip->getFromIndex($i), true);
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
        */
        $movieCacheFile = __DIR__ . '/../../data/moviecache_' . $id . '.json';
        if(file_exists($movieCacheFile)) {
            $json = file_get_contents($movieCacheFile);
            $movieList = json_decode($json, true);
        }
        
        $playlistData = array();
        $playlistFile = __DIR__ . '/../../data/playlist_' . $id . '_01.json';
        if(file_exists($playlistFile)) {
            $json = file_get_contents($playlistFile);
            $playlist = json_decode($json, true);
            foreach($playlist as $m) {
                $playlistData[] = array('unique_id' => $m["unique_id"], 'file' => $m["file"], 'name' => $m["name"], 'duration' => $m["duration"]);
            }
        }            

        $result = array('movies' => $movieList, 'playlist' => $playlistData);
        ajax::success($result);        
    }

    if (init('action') == 'uploadPlaylist') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu verifiez l\'id', __FILE__));
        }

        $uploaddir = __DIR__ . '/../../data';
        if (!isset($_FILES['file'])) {
            throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
        }  
        $extension = strtolower(strrchr($_FILES['file']['name'], '.'));
        if (!in_array($extension, array('.json'))) {
                throw new Exception('Extension du fichier non valide (autorisé .json) : ' . $extension);
        }
        if (filesize($_FILES['file']['tmp_name']) > 5000) {
                throw new Exception(__('Le fichier est trop gros (maximum 5ko)', __FILE__));
        }        
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploaddir . '/playlist_' . $id . '_01.json')) {
            throw new Exception(__('Impossible de déplacer le fichier temporaire', __FILE__));
        }
        if (!file_exists($uploaddir . '/playlist_' . $id . '_01.json')) {
            throw new Exception(__('Impossible de charger le fichier (limite du serveur web ?)', __FILE__));
        }
        ajax::success();
    }

    if (init('action') == 'exportAll') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu verifier l\'id', __FILE__));
        }
        log::add('kTwinkly','debug','Export animations et playlist pour équipement id='.$id);

        $allFiles = array();

        // On supprime les anciens exports pour cet équipement
        $exportpath = __DIR__ . '/../../data/kTwinkly_export_' . $id . '_*.zip';
        try {
            array_map( "unlink", glob( $exportpath ) );
        } catch (\Exception $e) {}

        // On récupère les infos de la guirlande
        $infos = array(
            "productcode" => $eqLogic->getConfiguration('productcode'),
            "productname" => $eqLogic->getConfiguration('productname'),
            "product" => $eqLogic->getConfiguration('product'),
            "devicename" => $eqLogic->getConfiguration('device_name'),
            "hardwareid" => $eqLogic->getConfiguration('hardwareid'),
            "firmwarefamily" => $eqLogic->getConfiguration('firmwarefamily'),
            "firmware" => $eqLogic->getConfiguration('firmware')
        );

        $newCache = array();
        $newPlaylist = array();

        $movieCache = get_movie_cache($id);
        if(count($movieCache) > 0) {            
            foreach($movieCache as $m) {
                $movieFile = realpath(__DIR__ . '/../../data/' . $m["file"]);
                $movieDest = 'movie_' . sanitize_filename($m["name"]) . '.zip';
                $m["file"] = $movieDest;
                $newCache[] = $m;
                $allFiles[] = array('sourcefile' => $movieFile, 'destfile' => $movieDest);
            }

            $playlistFile = realpath(__DIR__ . '/../../data/playlist_' . $id . '_01.json');
            if(file_exists($playlistFile)) {
                $playlist = json_decode(file_get_contents($playlistFile), true);
                foreach($playlist as $p) {
                    $movieItem = get_movie_from_cache($newCache, $p["unique_id"]);
                    $newPlaylist[] = $p;
                }
            }
        }

        if(count($allFiles) > 0) {
            log::add('kTwinkly','debug','Export exported zip=' . $exportFile);
            $exportFile = __DIR__ . '/../../data/kTwinkly_export_' . $id . '_' . sanitize_filename($eqLogic->getName()) . '_' . $eqLogic->getConfiguration("productcode") . '_' . date('YmdHis') . '.zip';

            $zip = new ZipArchive();
            if ($zip->open($exportFile, ZipArchive::CREATE)) {
                // Ajout infos
                $zip->addFromString("infos.json", json_encode($infos));
                // Ajout Index
                log::add('kTwinkly','debug','Export adding index =' . json_encode($newCache));
                $zip->addFromString("index.json", json_encode($newCache));
                // Ajout playlist (si disponible)
                if(count($newPlaylist) > 0) {
                    log::add('kTwinkly','debug','Export adding playlist =' . json_encode($newPlaylist));
                    $zip->addFromString("playlist_01.json", json_encode($newPlaylist));
                }
                // Ajout animations
                foreach($allFiles as $f) {
                    log::add('kTwinkly','debug','Export adding file =' . $f["sourcefile"] . ' => ' . $f["destfile"]);
                    $zip->addFile($f["sourcefile"], $f["destfile"]);
                }
                $zip->close();
            }
        }
        ajax::success(array('count' => count($allFiles), 'exportFile' => $exportFile));
    }

    if (init('action') == 'importAll') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);

        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu verifiez l\'id', __FILE__));
        }
        log::add('kTwinkly','debug','Import animations et playlist pour équipement id='.$id);        
        if (!isset($_FILES['file'])) {
            throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
        }  
        $extension = strtolower(strrchr($_FILES['file']['name'], '.'));
        if (!in_array($extension, array('.zip'))) {
                throw new Exception('Extension du fichier non valide (autorisé .zip) : ' . $extension);
        }
        if (filesize($_FILES['file']['tmp_name']) > 10000000) {
                throw new Exception(__('Le fichier est trop gros (maximum 10Mo)', __FILE__));
        }        

        $datetag = date('YmdHis');
        $tempFile = jeedom::getTmpFolder('kTwinkly') . '/kTwinkly_import_' . $id . '_' . $datetag . '.zip';
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)) {
            throw new Exception(__('Impossible de déplacer le fichier temporaire', __FILE__));
        }
        if (!file_exists($tempFile)) {
            throw new Exception(__('Impossible de charger le fichier (limite du serveur web ?)', __FILE__));
        }        

        $currentIndexFile = __DIR__ . '/../../data/moviecache_' . $id . '.json';
        $currentIndex = array();
        if(file_exists($currentIndex)) {
            $currentIndex = json_decode(file_get_contents($currentIndexFile), true);
        }

        $zip = new ZipArchive();
        if($zip->open($tempFile) === true) {
            if(($infosFile = $zip->getFromName("infos.json")) === false) {
                $zip->close();
                throw new Exception(__('Le fichier n\'est pas un export kTwinkly valide (fichier infos non trouvé)', __FILE__));
            }
            $infos = json_decode($infosFile, true);
            if(($infos["productcode"] !== $eqLogic->getConfiguration('productcode')) || ($infos["hardwareid"] !== $eqLogic->getConfiguration('hardwareid'))) {
                $zip->close();
                throw new Exception(__('Le fichier d\'export ne correspond pas au modèle de guirlande de cet équipement', __FILE__));
            }

            if(($indexFile = $zip->getFromName("index.json")) === false) {
                $zip->close();
                throw new Exception(__('Le fichier n\'est pas un export kTwinkly valide (fichier index non trouvé)', __FILE__));
            }

            $tempdir = jeedom::getTmpFolder('kTwinkly') . '/import_' . $datetag;
            mkdir($tempdir);
            
            $newIndex = array();
           
            // Extractions des zips des animations
            foreach(json_decode($indexFile, true) as $m) {
                $oldMovieItem = array_search($m["unique_id"], array_column($currentIndex, 'unique_id'));
                if($oldMovieItem === false) {
                    $movieFile = $zip->getFromName($m["file"]);
                    $movieName = 'movie_' . $id . '_' . $datetag . '_' . sanitize_filename($m["name"]) . '.zip';
                    file_put_contents($tempdir . "/" . $movieName, $movieFile);
                    $m["file"] = $movieName;
                    $newIndex[] = $m;         
                }   
            }

            // Création du nouvel index            
            $newIndexFile = 'moviecache_' . $id . '.json';
            file_put_contents($tempdir . '/' . $newIndexFile, json_encode($newIndex));
            
            $newPlaylist = array();
            if(($playlistFile = $zip->getFromName("playlist_01.json")) !== false) {
                foreach(json_decode($playlistFile, true) as $p) {
                    $movieItem = array_search($p["unique_id"], array_column($newIndex, 'unique_id'));
                    $p["file"] = $newIndex[$movieItem]["file"];
                    $newPlaylist[] = $p;
                }
                $newPlaylistFile = 'playlist_' . $id . '_01.json';
                file_put_contents($tempdir . '/' . $newPlaylistFile, json_encode($newPlaylist));
            }
            $zip->close();

            // Suppression des anciennes animations et playlists
            if(is_file(__DIR__ . '/../../data/' . $newIndexFile)) {
                unlink(__DIR__ . '/../../data/' . $newIndexFile);
            }            
            if(is_file(__DIR__ . '/../../data/' . $newPlaylistFile)) {
                unlink(__DIR__ . '/../../data/' . $newPlaylistFile);
            }            
            try {
                array_map("unlink", glob(__DIR__ . '/../../data/movie_' . $id . '_*.zip'));
            } catch (\Exception $e) {}

            // Déplacement des fichiers dans la destination
            rename($tempdir . '/' . $newIndexFile, __DIR__ . '/../../data/' . $newIndexFile);
            if(count($newPlaylist) > 0) {
                rename($tempdir . '/' . $newPlaylistFile, __DIR__ . '/../../data/' . $newPlaylistFile);
            }
            foreach($newIndex as $m) {
                rename($tempdir . '/' . $m["file"], __DIR__ . '/../../data/' . $m["file"]);
            }

            // Suppression du repertoire temporaire
            rmdir($tempdir);

            // Chargement des infos dans la liste
            $cmdMovies = $eqLogic->getCmd(null, 'movie');
            $lv = "";
            foreach($newIndex as $m) {
                $lv .= ';' . $m["file"] . "|" . $m["name"];
            }
            $lv = substr($lv, 1);
            $cmdMovies->setConfiguration("listValue", $lv);
            $cmdMovies->save();
        }

        // Suppression du fichier temporaire
        unlink($tempFile);

        ajax::success();
    }

    if (init('action') == 'downloadSelectedMovies') {
        $id = init('id');
        $eqLogic = eqLogic::byId($id);

        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu verifier l\'id', __FILE__));
        }
        $exportedMovieIDs = $_POST["selectedFilenames"];

        if(count($exportedMovieIDs) > 0 ) {
            log::add('kTwinkly','debug','Export des ' . count($exportedMovieIDs) . ' animations sélectionnées de l\'équipement id=' . $id);

            // On supprime les anciens exports pour cet équipement
            $exportpath = __DIR__ . '/../../data/kTwinkly_moviexport_' . $id . '_*.zip';
            try {
                array_map( "unlink", glob( $exportpath ) );
            } catch (\Exception $e) {}
                        
            $exportFile = __DIR__ . '/../../data/kTwinkly_moviexport_' . $id . '_' . sanitize_filename($eqLogic->getName()) . '_' . $eqLogic->getConfiguration("productcode") . '_' . date('YmdHis') . '.zip';
            $movieCacheFile = __DIR__ . '/../../data/moviecache_' . $id . '.json';
            if(file_exists($movieCacheFile)) {
                $movieCache = json_decode(file_get_contents($movieCacheFile), true);
            }
                        
            if(count($exportedMovieIDs) > 1) {
                $zip = new ZipArchive();
                if ($zip->open($exportFile, ZipArchive::CREATE)) {
                    foreach($exportedMovieIDs as $item) {            
                        $idx = array_search($item, array_column($movieCache, 'unique_id'));
                        $m = $movieCache[$idx];
                        $movieFile = realpath(__DIR__ . '/../../data/' . $m["file"]);
                        $movieDest = 'movie_' . sanitize_filename($m["name"]) . '.zip';
                        $zip->addFile($movieFile, $movieDest);
                    }
                    $zip->close();
                    ajax::success(array('count' => count($exportedMovieIDs), 'exportFile' => $exportFile));
                } else {
                    throw new Exception(__('Impossible de créer le fichier zip d\'export', __FILE__));
                }
            } else {
                $idx = array_search($exportedMovieIDs[0], array_column($movieCache, 'unique_id'));
                $m = $movieCache[$idx];
                $movieFile = realpath(__DIR__ . '/../../data/' . $m["file"]);
                ajax::success(array('count' => 1, 'exportFile' => $movieFile));
            }
        } else {
            log::add('kTwinkly','debug','Aucune animation n\'a été sélectionnée pour l\'export');
            throw new Exception(__('Aucun fichier sélectionné', __FILE__));
        }       
    }

    throw new Exception(__('Aucune méthode correspondant à : ', __FILE__) . init('action'));
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
