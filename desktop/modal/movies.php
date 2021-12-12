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

// Génération de l'équipement (support des playlists)
$hwgen = $eqLogic->getConfiguration("hwgen");

// Liste des animations disponibles pour cet équipement
$cmdMovies = $eqLogic->getCmd(null, 'movie');
$lv = $cmdMovies->getConfiguration("listValue");
if ($lv != "") {
	$moviesList = explode(';', $lv);
} else {
    $moviesList = array();
}

// Etat du proxy mitm
if (isset($_GET["proxy"])) {
    $proxymode = $_GET["proxy"];
} else {
    $proxymode = ($eqLogic->getConfiguration("proxy_enabled")=="1");
}

// Nombre d'animations récupérées lors de la capture
if (isset($_GET["newmovies"])) {
    $newmovies = $_GET["newmovies"];
} else {
    $newmovies = -1;
}
?>
<div style="display: none;width : 100%" id="div_alert_movies"></div>

<div class="row row-overflow">
    <div class="col-xs-12">
        <div class="eqLogicThumbnailContainer">
<?php
if (!$proxymode) {
    // Proxy arrêté - Affichage du bouton de capture
    echo '<div class="cursor changeProxyState card success" data-mode="1" data-state="0"  >';
    echo '<i class="fas fa-sign-in-alt fa-rotate-90"></i>';
    echo '<br/>';
    echo '<span>{{Capturer une animation}}</span>';
    echo '</div>';
} else {
    // Proxy démarré - Affichage du bouton d'arrêt
    echo '<div class="cursor changeProxyState card danger" data-mode="1" data-state="1"  >';
    echo '<i class="fas fa-sign-in-alt fa-rotate-90"></i>';
    echo '<br/>';
    echo '<span>{{Arrêter la capture}}</span>';
    echo '</div>';
}
?>
        </div>
<?php
if ($proxymode == 1) {
?>
        <div style="width : 100%; padding: 5px 30px; background-color: var(--al-info-color); color: var(--sc-lightTxt-color) !important; border: none;" id="div_messages_movies">Le proxy de capture des animations a démarré. Configurez le proxy de votre smartphone sur l'adresse IP <?= network::getNetworkAccess('internal', 'ip', '', false) ?> et le port 14233, lancez l'application Twinkly, et téléchargez une ou plusieurs animations vers cette guirlande uniquement. Ensuite, arrêtez le proxy pour continuer.</div>
<?php
} else if ($newmovies == 0) {
?>
        <div style="width : 100%; padding: 5px 30px; background-color: var(--al-warning-color); color: var(--sc-lightTxt-color) !important; border: none;" id="div_messages_movies">Aucune nouvelle animation n'a été récupérée. Veuillez redémarrer le processus et vérifier la configuration du proxy sur votre smartphone (adresse IP et port).<br>Jeedom, le smartphone et la guirlande doivent être sur le même réseau ou sur des réseaux directement connectés.</div>
<?php
} else if ($newmovies > 0) {
?>
        <div style="width : 100%; padding: 5px 30px; background-color: var(--al-success-color); color: var(--sc-lightTxt-color) !important; border: none;" id="div_messages_movies"><?= $newmovies ?> nouvelle(s) animation(s) a (ont) été récupérée(s). Vous pouvez changer leur nom dans la liste ci-dessous ou les télécharger sur votre ordinateur pour les sauvegarder. Les animations capturées ne sont valables que pour cette guirlande, et correspondent à son positionnement actuel uniquement.</div>
<?php
}
?>
        <div>
            <form id="moviesList" class="form-horizontal">
                <fieldset>
                    <input type="hidden" id="id" name="id" value="<?php echo $eqId; ?>">
                    <input type="hidden" id="action" name="action" value="">
<?php
  if (count($moviesList) > 0) {
?>
                    <table id="table_movies" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th style="width: 50px"></th>
                                <th style="width: 50px"></th>
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
    $title = $listItem[1];

	echo '<tr class="movie">';
	echo '  <input type="hidden" id="file_' . $cnt . '" name="files[]" value="' . $filename . '"/>';

    // Case de sélection
	echo '  <td class="center" style="width: 50px">';
	echo '      <input type="checkbox" class="kTWinklyMovieItem" name="selectedFilenames[]" value="' . $filename . '"/>';
	echo '  </td>';

    // Bouton de téléchargement
    echo '  <td class="center" style="width: 50px">';
    echo '      <a href="plugins/kTwinkly/data/'.$filename.'" target="_blank"><i class="fas fa-file-download"></i></a>';
    echo '  </td>';

    // Titre de l'animation
	echo '  <td>';
	echo '      <input class="movieAttr form-control input-sm" maxlength="50" id="label_' . $cnt . '" name="labels[]" value="' . $title . '"/>';
	echo '  </td>';

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
                    <span class="btn btn-success" id="bt_saveMovie"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</span>
                </fieldset>
            </form>
        </div>
    </div>
</div>
<script>
$(function() {
    var parentWidth = $( window ).width()
    var parentHeight = $( window ).height()
    if (parentWidth > 850 && parentHeight > 750) {
      $('#md_modal').dialog("option", "width", 800).dialog("option", "height", 650)
      $("#md_modal").dialog({
        position: {
          my: "center center",
          at: "center center",
          of: window
        }
      })
    }
    moviesNotSaved = 0;
})
</script>
<?php include_file('desktop', 'movies', 'js', 'kTwinkly');?>
