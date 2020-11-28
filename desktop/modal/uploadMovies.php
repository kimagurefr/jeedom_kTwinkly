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
?>


<form id="moviesList">
<input type="hidden" id="id" name="id" value="<?php echo $eqId; ?>">
<input type="hidden" id="action" name="action" value="">
<div style="display: none;width : 100%" id="div_alert_movies"></div>
  <table id="table_anims" class="table table-bordered table-condensed">
    <thead>
      <tr>
        <th>{{Sélection}}</th>
        <th>{{Fichier}}</th>
        <th>{{Titre}}</th>
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
	$displayname = substr($filename, strlen($eqId) + 9);
        $title = $listItem[1];
	echo '<tr class="movie">';
	echo '<td style="min-width:10px; width=10px;">';
	echo '<input type="checkbox" name="deletedFilenames[]" value="' . $filename . '">';
	echo '</td>';
	echo '<td style="min-width:100px; width=150px;">';
	echo $displayname;
	echo '</td>';
	echo '<td style="min-width:100px; width=150px;">';
	echo '<input type="hidden" id="file_' . $cnt . '" name="files[]" value="' . $filename . '">';
	echo '<input class="cmdAttr form-control input-sm" id="label_' . $cnt . '" name="labels[]" value="' . $title . '">';
	echo '</td>';
	echo '</tr>';
  }
?>
    </tbody>
  </table>

<span class="btn btn-default btn-file">
{{Ajouter}}... <input id="bt_uploadMovie" type="file" name="file" style="display: inline-block">
</span>
<span class="btn btn-default" id="bt_deleteMovie">{{Supprimer}}</span>
<span class="btn btn-default" id="bt_saveMovie">{{Sauvegarder}}</span>
</form>

<script>
$('#bt_uploadMovie').fileupload({
    replaceFileInput: false,
    url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php?action=uploadMovie&id=<?php echo $eqId; ?>&jeedom_token=<?php ajax::getToken(); ?>',
    dataType: 'json',
    done: function (e, data) {
      if (data.result.state != 'ok') {
        $('#div_alert_movies').showAlert({message: data.result.result, level: 'danger'});
        return;
      }else{
        //$('#div_alert_movies').showAlert({message: '{{Fichier envoyé avec succès}}', level: 'success'});
        $('#md_modal').load('index.php?v=d&plugin=kTwinkly&modal=uploadMovies&id=<?php echo $eqId; ?>&reload=1');
      }
    }
});
</script>

<?php include_file('desktop', 'movies', 'js', 'kTwinkly');?>
