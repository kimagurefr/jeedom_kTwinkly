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
?>

<div style="display: none;width : 100%" id="div_alert_playlists"></div>

<div class="input-group pull-right" style="display:inline-flex;">
    <a class="btn btn-sm btn-default addToPlaylist" style="margin-top:5px"><i class="fas fa-plus-circle"></i> {{Ajouter un élément}}</a>
    <a class="btn btn-sm sendPlaylist" style="margin-top:5px"><i class="fas fa-file-import"></i> {{Activer}}</a>
    <a class="btn btn-sm btn-danger deletePlaylist" style="margin-top:5px"><i class="fas fa-trash"></i> {{Effacer la playlist}}</a>
    <a class="btn btn-sm btn-danger clearMemory" style="margin-top:5px"><i class="fas fa-trash"></i> {{Effacer la mémoire}}</a>
    <span class="btn btn-sm btn-file" title="Uploader un fichier" style="margin: 5px 0px 0px" ><i class="fas fa-file-upload"></i> {{Charger}}<input id="bt_uploadPlaylist" type="file" name="file"></span>
    <!--<a href="core/php/downloadFile.php?pathfile=<?=urlencode("/var/www/html/plugins/kTwinkly/data/playlist_$eqId_01.json")?>" class="btn btn-sm downloadPlaylist" style="margin-top:5px"><i class="fas fa-file-download"></i> {{Télécharger}}</a>    -->
    <a class="btn btn-sm downloadPlaylist" style="margin-top:5px"><i class="fas fa-file-download"></i> {{Télécharger}}</a> 
    <a class="btn btn-sm btn-success savePlaylist" style="margin-top:5px"><i class="fas fa-save"></i> {{Sauvegarder}}</a>    
</div>

<select id="availableMoviesList" style="visibility: hidden"></select>

<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <div>
            <form id="playlist" class="form-horizontal">
                <input type="hidden" id="id" name="id" value="<?php echo $eqId; ?>">
                <input type="hidden" id="action" name="action" value="">
                <table id="table_playlists" class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>{{Animation}}</th>
                            <th>{{Durée}}</th>
                            <th style="width: 150px; align"></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</div>

<?php include_file('desktop', 'playlist', 'js', 'kTwinkly');?>
