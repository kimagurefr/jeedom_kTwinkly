<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('kTwinkly');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<!-- Page d'accueil du plugin -->
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
		<!-- Boutons de gestion du plugin -->
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Ajouter}}</span>
			</div>
                        <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                                <i class="fas fa-wrench"></i>
                                <br>
                                <span>{{Configuration}}</span>
                        </div>
                        <div class="cursor eqLogicAction logoSecondary" id="bt_discover">
                                <i class="fas fa-sync-alt"></i>
                                <br>
                                <span>{{Recherche}}</span>
                        </div>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes guirlandes}}</legend>
		<!-- Champ de recherche -->
		<div class="input-group" style="margin:5px;">
			<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic"/>
			<div class="input-group-btn">
				<a id="bt_resetSearch" class="btn roundedRight" style="width:30px"><i class="fas fa-times"></i></a>
			</div>
		</div>
		<!-- Liste des équipements du plugin -->
		<div class="eqLogicThumbnailContainer">
			<?php
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
				if ($eqLogic->getConfiguration("productimage") != '') {
				    echo '<img src="' . $eqLogic->getImage() . '" alt="'. $eqLogic->getImage() . '"/>';
                } else {
				    echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
                }
				echo '<br>';
				echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				echo '</div>';
			}
			?>
		</div>
	</div> <!-- /.eqLogicThumbnailDisplay -->

	<!-- Page de présentation de l'équipement -->
	<div class="col-xs-12 eqLogic" style="display: none;">
		<!-- barre de gestion de l'équipement -->
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs">  {{Dupliquer}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>
		<!-- Onglets -->
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
			<!-- Onglet de configuration de l'équipement -->
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<br/>
				<div class="row">
					<!-- Partie gauche de l'onglet "Equipements" -->
					<!-- Paramètres généraux de l'équipement -->
					<div class="col-lg-7">
						<form class="form-horizontal">
							<fieldset>
								<legend><i class="fas fa-wrench"></i> {{Général}}</legend>
								<div class="form-group">
									<label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
									<div class="col-xs-11 col-sm-7">
										<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;"/>
										<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-3 control-label" >{{Objet parent}}</label>
									<div class="col-xs-11 col-sm-7">
										<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
											<option value="">{{Aucun}}</option>
											<?php
											$options = '';
											foreach ((jeeObject::buildTree(null, false)) as $object) {
												$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
											}
											echo $options;
											?>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-3 control-label">{{Catégorie}}</label>
									<div class="col-sm-9">
										<?php
										foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
											echo '<label class="checkbox-inline">';
											echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
											echo '</label>';
										}
										?>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-3 control-label">{{Options}}</label>
									<div class="col-xs-11 col-sm-7">
										<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
										<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
									</div>
								</div>

								<br/>
								<legend><i class="fas fa-cogs"></i> {{Paramètres}}</legend>
								<div class="form-group">
									<label class="col-sm-3 control-label">{{Adresse IP}}</label>
									<div class="col-sm-3">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="ipaddress" placeholder="{{Adresse IP}}"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-3 control-label">{{Adresse MAC}}</label>
									<div class="col-sm-3">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="macaddress" placeholder="12:34:56:78:90"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-3 control-label">{{Rafraichissement auto}}</label>
									<div class="col-sm-3">
										<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="autorefresh"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-3 control-label"></label>
									<div class="col-sm-8">
										<span class="btn btn-default" id="bt_movies">
											Gérer les animations...
										</span>
                                        <!--
										<span class="btn btn-default" id="bt_mqtt">
											Configurer MQTT...
										</span>
                                        -->
									</div>
								</div>
								<br/>
							</fieldset>
						</form>
					</div>
					<!-- Partie droite de l'onglet "Equipement" -->
					<!-- Affiche l'icône du plugin par défaut mais vous pouvez y afficher les informations de votre choix -->
					<div class="col-lg-5">
						<form class="form-horizontal">
							<fieldset>
								<legend><i class="fas fa-info"></i> {{Informations}}</legend>
								<div class="form-group">
									<label class="col-sm-3"></label>
									<div class="col-sm-7 text-center">
									<!--	<img name="icon_visu" src="<?= $plugin->getPathImgIcon(); ?>" style="max-width:160px;"/> -->
                                        <img src="core/img/no_image.gif" data-original=".jpg" id="img_device" class="img-responsive" style="max-height : 250px;" onerror="this.src='plugins/kTwinkly/plugin_info/kTwinkly_icon.png'"/>
									</div>
								</div>
<?php if ($eqLogics[0] !== NULL && $eqLogics[0]->getConfiguration()["product"] !== NULL) { ?>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">{{Produit}}</label>
                                    <div class="col-sm-3">
                                        <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="productname" id="productfield"></span>
                                    </div>
                                    <label class="col-sm-3 control-label">{{Nom}}</label>
                                    <div class="col-sm-3">
                                        <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="devicename" id="devicenamefield"></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">{{Nombre de leds}}</label>
                                    <div class="col-sm-3">
                                        <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="numberleds" id="numberledsfield"></span>
                                    </div>
                                    <label class="col-sm-3 control-label">{{Type de leds}}</label>
                                    <div class="col-sm-3">
                                        <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="ledtype" id="ledtypefield"></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">{{Firmware}}</label>
                                    <div class="col-sm-3">
                                        <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="firmware" id="firmwarefield"></span>
                                    </div>
                                    <label class="col-sm-3 control-label">{{ID Matériel}}</label>
                                    <div class="col-sm-3">
                                        <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="hardwareid" id="hardwareidfield"></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">{{Gen}}</label>
                                    <div class="col-sm-3">
                                        <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="hwgen" id="hwgenfield"></span>
                                    </div>
                                </div>
<?php } ?>
							</fieldset>
						</form>
					</div>
				</div><!-- /.row-->
			</div><!-- /.tabpanel #eqlogictab-->

			<!-- Onglet des commandes de l'équipement -->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<br/><br/>
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th>{{Id}}</th>
								<th>{{Nom}}</th>
								<!-- <th>{{Type}}</th> -->
								<th>{{Options}}</th>
								<th>{{Paramètres}}</th>
								<th>{{Action}}</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtab-->

		</div><!-- /.tab-content -->
	</div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'kTwinkly', 'js', 'kTwinkly');?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');?>
