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

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$eqId = $_GET["id"];
$eqLogic = eqLogic::byId($eqId);
$cmdMovies = $eqLogic->getCmd(null, 'movie');
$lv = $cmdMovies->getConfiguration("listValue");
if($lv != "") {
	$moviesList = explode(';', $lv);
}

if($_GET["proxy"]) {
    $proxymode = $_GET["proxy"];
} else {
    $proxymode = ($eqLogic->getConfiguration("proxy_enabled")=="1");
}

?>
<div style="display: none;width : 100%" id="div_alert_movies"></div>

<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <div class="eqLogicThumbnailContainer">
<?php
if(!$proxymode) {
    echo '<div class="cursor changeProxyState card success" data-mode="1" data-state="0"  >';
    echo '<i class="fas fa-sign-in-alt fa-rotate-90"></i>';
    echo '<br/>';
    echo '<span>{{Capturer une animation}}</span>';
    echo '</div>';
} else {
    echo '<div class="cursor changeProxyState card danger" data-mode="1" data-state="1"  >';
    echo '<i class="fas fa-sign-in-alt fa-rotate-90"></i>';
    echo '<br/>';
    echo '<span>{{Arrêter la capture}}</span>';
    echo '</div>';
}
?>
        </div>
    </div>
</div>
<?php
if($proxymode) {
?>
<div style="width : 100%; padding: 5px 30px; background-color: var(--al-info-color); color: var(--sc-lightTxt-color) !important; border: none;" id="div_messages_movies">Le proxy de capture des animations a démarré. Configurez le proxy de votre smartphone sur l'adresse IP <?= network::getNetworkAccess('internal', 'ip', '', false) ?> et le port 14233, lancez l'application Twinkly, et téléchargez une ou plusieurs animations vers cette guirlande uniquement. Ensuite, arrêtez le proxy pour continuer.</div>
<?php
}
?>
<div style="display: none;width : 100%; padding: 5px 30px; background-color: var(--al-info-color); color: var(--sc-lightTxt-color) !important; border: none;" id="div_messages_movies">Le proxy de capture des animations a démarré. Configurez le proxy de votre smartphone sur l'adresse IP <?= network::getNetworkAccess('internal', 'ip', '', false) ?> et le port 14233, lancez l'application Twinkly, et téléchargez une ou plusieurs animations vers cette guirlande uniquement. Ensuite, arrêtez le proxy pour continuer.</div>
<div>
<form id="moviesList">
<input type="hidden" id="id" name="id" value="<?php echo $eqId; ?>">
<input type="hidden" id="action" name="action" value="">
<?php
  if(count($moviesList) > 0) {
?>
  <table id="table_anims" class="table table-bordered table-condensed">
    <thead>
      <tr>
        <th>{{Sélection}}</th>
        <th>{{Fichier}}</th>
        <th>{{Titre}}</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
<?php
  //$animfiles = glob(dirname(__FILE__) . '/../../data/twinkly_' . $eqId . '_*.bin');
  $cnt=0;
  //foreach($animfiles as $a) {
  foreach($moviesList as $item) {
	$cnt++;
	$listItem = explode('|', $item);
	$filename = $listItem[0];
    $title = $listItem[1];
	echo '<tr class="movie">';
	echo '<td style="min-width:10px; width=10px;">';
	echo '<input type="checkbox" name="deletedFilenames[]" value="' . $filename . '">';
	echo '</td>';
	echo '<td style="min-width:100px; width=150px;">';
	echo $filename;
	echo '</td>';
	echo '<td style="min-width:100px; width=150px;">';
	echo '<input type="hidden" id="file_' . $cnt . '" name="files[]" value="' . $filename . '">';
	echo '<input class="cmdAttr form-control input-sm" id="label_' . $cnt . '" name="labels[]" value="' . $title . '">';
	echo '</td>';
    echo '<td>';
    echo '<a href="plugins/kTwinkly/data/'.$filename.'" target="_blank"><i class="fas fa-file-download"></i></a>';
    echo '</td>';
	echo '</tr>';
  }
?>
    </tbody>
  </table>
<?php } ?>
<span class="btn btn-default btn-file">
{{Ajouter}}... <input id="bt_uploadMovie" type="file" name="file" style="display: inline-block">
</span>
<span class="btn btn-default" id="bt_deleteMovie">{{Supprimer}}</span>
<span class="btn btn-default" id="bt_saveMovie">{{Sauvegarder}}</span>
</form>
</div>

<?php include_file('desktop', 'movies', 'js', 'kTwinkly');?>
