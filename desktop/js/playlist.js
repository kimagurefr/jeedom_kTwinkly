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

$("#playlist").sortable({
    axis: "y",
    cursor: "move",
    items: ".plitem",
    placeholder: "ui-state-highlight",
    tolerance: "intersect",
    forcePlaceholderSize: true,
    update: function( event, ui ) { playlistNotSaved = 1; }
});

$('#md_modal').on('dialogclose', function(event) {
    $('#md_modal').off('dialogclose');
    //console.log('equipement id = ' + $('.eqLogicAttr[data-l1key=id]').value());
});

$('.deletePlaylist').off('click').on('click', function() {
    bootbox.confirm('{{Etes-vous sûr de vouloir supprimer la playlist courante}} ?', function (result) {
        if (result) {
            $('#playlist #action').val('deletePlaylist');
            $.ajax({
                type: "POST",
                url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
                data: $("#playlist").serialize(),
                datatype: 'json',
                error: function(request, status, error) { },
                success: function (data) {
                    if (data.state != 'ok') { 
                        $('#div_alert_playlists').showAlert({message: data.result, level: 'danger'});
                        return;
                    } else {
                        playlistNotSaved = 0;
                        $('#md_modal').load('index.php?v=d&plugin=kTwinkly&modal=playlist&id=' + $('.eqLogicAttr[data-l1key=id]').value() + '&reload=1');
                        $('#div_alert_playlists').showAlert({message: data.result, level: 'info'});
                    }
                }
            });
        }
    });
});

$('.clearMemory').off('click').on('click', function() {
    bootbox.confirm('{{Etes-vous sûr de vouloir effacer les animations en mémoire}} ?', function (result) {
        if (result) {
            $('#playlist #action').val('clearMemory');
            $.ajax({
                type: "POST",
                url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
                data: $("#playlist").serialize(),
                datatype: 'json',
                error: function(request, status, error) { },
                success: function (data) {
                    if (data.state != 'ok') { 
                        $('#div_alert_playlists').showAlert({message: data.result, level: 'danger'});
                        return;
                    } else {
                        $('#div_alert_playlists').showAlert({message: data.result, level: 'info'});
                    }
                }
            });
        }
    });
});

$('.downloadPlaylist').off('click').on('click', function() {
    window.open('core/php/downloadFile.php?pathfile=/var/www/html/plugins/kTwinkly/data/playlist_' + $('.eqLogicAttr[data-l1key=id]').value() + '_01.json', "_blank", null)
});

$('.sendPlaylist').off('click').on('click', function() {
    var newPlaylist = [];
    $('#playlist tr.plitem').each(function(i,v) {
        var duration = $(v).find('input.playlistDuration').val();
        var movie = $(v).find('select.playlistMovie option:selected').val();
        var uniqueId = $(v).find('select.playlistMovie option:selected').attr("data-movieid");
        newPlaylist.push({"index":i, "filename": movie, "duration": duration, "unique_id": uniqueId});
    });

    $.ajax({
        type: "POST",
        url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
        data: {
            'action': 'createPlaylist',
            'id': $('.eqLogicAttr[data-l1key=id]').value(),
            'playlist': newPlaylist
        },
        datatype: 'json',
        error: function(request, status, error) { },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert_playlists').showAlert({message: data.result, level: 'danger'});
                return;
            } else {
                $('#div_alert_playlists').showAlert({message: data.result, level: 'info'});
                playlistNotSaved=0;
            }
        }
    });
});

$('.savePlaylist').off('click').on('click', function() {
    var newPlaylist = [];
    $('#playlist tr.plitem').each(function(i,v) {
        var duration = $(v).find('input.playlistDuration').val();
        var movie = $(v).find('select.playlistMovie option:selected').val();
        var uniqueId = $(v).find('select.playlistMovie option:selected').attr("data-movieid");
        newPlaylist.push({"index":i, "file": movie, "duration": duration, "unique_id": uniqueId});
    });

    $.ajax({
        type: "POST",
        url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
        data: {
            'action': 'savePlaylist',
            'id': $('.eqLogicAttr[data-l1key=id]').value(),
            'playlist': newPlaylist
        },
        datatype: 'json',
        error: function(request, status, error) { },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert_playlists').showAlert({message: data.result, level: 'danger'});
                return;
            } else {
                $('#div_alert_playlists').showAlert({message: data.result, level: 'info'});
                playlistNotSaved=0;
            }
        }
    });   
});

$('.addToPlaylist').off('click').on('click', function() {
    if($('.plitem').length < 15) {
        var tr = '<tr class="plitem">';
        tr += '  <td>';
        tr += '      <select class="form-control playlistAttr playlistMovie" name="plItems[]">';
        $('#availableMoviesList option').each(function() {
            tr += '<option value="' + $(this).val() + '" data-movieid="' + $(this).attr("data-movieid").toLowerCase() + '">' + $(this).text() + '</option>';
        });
        tr += '      </select>';
        tr += '  </td>';
        tr += '  <td>';
        tr += '      <input class="playlistAttr form-control input-sm playlistDuration" type="text" style="width: 15%" maxlength="3" name="duration[]" value="30"/>';
        tr += '  </td>';
        tr += '  <td>';
        tr += '     <i class="fas fa-minus-circle pull-right cursor removeFromPlaylist" data-plitem="1234"></i>';
        tr += '  </td>';
        tr += '</tr>';
    
        $('#playlist tbody').append(tr);
        playlistNotSaved=1;
    } else {
        $('#div_alert_playlists').showAlert({message: "{{La playlist ne peut contenir plus de 15 animations}}", level: 'info'});
        setTimeout(function() {$('#div_alert_playlists').hideAlert();}, 3000);
    }
});

$("#playlist").on("click", ".removeFromPlaylist", function(){
    $(this).closest('tr').remove();
    playlistNotSaved = 1;
});

$("#playlist").on("change", ".playlistAttr", function() {
    playlistNotSaved = 1;
});

$("#playlist").on("keyup",".playlistDuration", function(event) {
    if (event.which !== 8 && event.which !== 0 && event.which < 48 || event.which > 57) {
        $(this).val(function (index, value) {
            return value.replace(/[^0-9]/g, "");
        });
    }
});

$('#bt_uploadPlaylist').fileupload({
    replaceFileInput: false,
    url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php?action=uploadPlaylist&id=' + $('.eqLogicAttr[data-l1key=id]').value(),
    dataType: 'json',
    done: function (e, data) {
      if (data.result.state != 'ok') {
        $('#div_alert_movies').showAlert({message: data.result.result, level: 'danger'});
        return;
      }else{
        $('#md_modal').load('index.php?v=d&plugin=kTwinkly&modal=playlist&id=' + $('.eqLogicAttr[data-l1key=id]').value() + '&reload=1');
      }
    }
});


$.ajax({
    type: "POST",
    url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
    data: {
        'action': 'getDetailedPlaylist',
        'id': $('.eqLogicAttr[data-l1key=id]').value()
    },
    datatype: 'json',
    error: function(request, status, error) { },
    success: function (data) {
        if (data.state != 'ok') {
            $('#div_alert_playlists').showAlert({message: data.result, level: 'danger'});
            return;
        } else {
            //console.log(data.result.movies);
            //console.log(data.result.playlist);
            var allmovies = data.result.movies;
            var playlist = data.result.playlist;
            allmovies.forEach(function(e) {
                $('#availableMoviesList').append($('<option>', { 
                    //val: e.unique_id,
                    val: e.file,
                    text : e.name,
                    "data-movieid" : e.unique_id.toLowerCase()
                }));
            });
            var allmoviesfound = 1;
            playlist.forEach(function(e) {
                var moviefound = 0;
                var tr = '<tr class="plitem">';
                tr += '  <td>';
                tr += '      <select class="form-control playlistAttr playlistMovie" name="plItems[]">';
                $('#availableMoviesList option').each(function() {
                    tr += '<option value="' + $(this).val() + '"';
                    if ($(this).attr('data-movieid') === e.unique_id.toLowerCase()) {
                        tr += " selected";
                        moviefound = 1;
                    }
                    tr += ' data-movieid="' + e.unique_id.toLowerCase() + '">' + $(this).text() + '</option>';
                });
                tr += '      </select>';
                tr += '  </td>';
                tr += '  <td>';
                tr += '      <input class="playlistAttr form-control input-sm playlistDuration" type="text" maxlength="3" style="width: 15%" name="duration[]" value="' + e.duration + '"/>';
                tr += '  </td>';
                tr += '  <td>';
                tr += '     <i class="fas fa-minus-circle pull-right cursor removeFromPlaylist"></i>';
                tr += '  </td>';
                tr += '</tr>';

                if (moviefound == 1) {
                    $('#playlist tbody').append(tr);
                }  else {
                    allmoviesfound = 0;
                }
            });
            playlistNotSaved=0;
            if (allmoviesfound != 1) {
                $('#div_alert_playlists').showAlert({message: "{{Les fichiers pour certaines animations de la playlist actuelle ont été supprimés}}", level: 'warn'});
            }
        }
    }
});
