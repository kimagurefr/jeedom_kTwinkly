# Plugin kTwinkly

Ce plugin pour Jeedom permet le pilotage des guirlandes connectées [Twinkly](https://www.twinkly.com/)

Listes des fonctionnalités disponibles :

- [Découverte automatique](#Découverte et paramétrage des équipements) des guirlandes connectées sur le réseau et de leurs caractéristiques
- Pilotage simple on/off
- Contrôle du niveau de luminosité
- Chargement d'une animation sur la guirlande
- [Capture des animations](#Capture des animations) envoyés vers la guirlande depuis l'application mobile officielle Twinkly pour pouvoir ensuite les charger sur le sapin.

Certaines fonctionnalités peuvent ne pas être disponibles sur les guirlandes d'anciennes générations ("gen 1") à cause de limitation du contrôleur ou du firmware.



## Information importante

A cause d'une limitation "by design" du mécanisme d'authentification sur le contrôleur, il est n'est possible d'utiliser qu'**un seul outil à la fois** pour piloter une guirlande. Il n'est donc pas possible d'utiliser confortablement et sans erreur le plugin en même temps que l'app mobile. Cette limitation n'est pas spécifique à ce plugin, mais empêche également l'utilisation simultanée de plusieurs smartphones pour piloter une même guirlande : voir la [FAQ](https://www.twinkly.com/knowledge/how-to-manage-twinkly-from-multiple-smartphone/) sur le site de Twinkly.

Pour contourner ce problème, notamment pendant les phases de capture des animations, il est possible de désactiver temporairement le rafraichissement automatique des informations d'une guirlande par le plugin. Plus aucun accès n'est donc fait en arrière plan, et l'app mobile est pleinement utilisable.



## Installation du plugin

Après installation du plugin depuis le market Jeedom et son activation, il est nécessaire d'installer les dépendances pour pouvoir utiliser la fonction de capture des animations.

Il y a 2 paramètres disponibles dans la configuration générale du plugin :

- la fréquence à laquelle le plugin appelera l'API des différents contrôleurs Twinkly pour récupérer la mise à jour des informations (état, luminosité). Cette fréquence est de 10 secondes par défaut.
- le port HTTP du proxy qui sera lancé sur le serveur Jeedom pour [capturer les animations](#Capture des animations) depuis l'application mobile.

![](https://kimagurefr.github.io/jeedom_kTwinkly/images/config_plugin.png)



## Découverte et paramétrage des équipements

Depuis la page du plugin (Objets Connectés > Twinkly), il faut ensuite créer ou faire détecter automatiquement les guirlandes. 

**Important** : il est nécessaire que les guirlandes soient configurées préalablement et connectées au réseau wifi en utilisant l'application mobile officielle Twinkly sur iOS ou Android.



La solution la plus simple est d'utiliser le bouton <img src="https://kimagurefr.github.io/jeedom_kTwinkly/images/recherche.png" alt="Recherche" style="zoom: 25%;" /> pour lancer la découverte automatique des équipements.



Si la découverte automatique ne marche pas (équipements sur un réseau différent, ou trafic UDP broadcast bloqué), il est possible de créer les équipements manuellement. Il faut alors fournir les informations suivantes :

- L'adresse IP de la guirlande
- L'adresse MAC

Ces 2 informations sont visibles dans l'application mobile Twinkly.

![Configuration Equipement](https://kimagurefr.github.io/jeedom_kTwinkly/images/config_equipement.png)

Après sauvegarde de l'équipement, les caractéristiques seront récupérées depuis le contrôleur de la guirlande.

Dans cet écran, il est également possible de désactiver le rafraîchissement automatique des informations, pour ne pas perturber l'application mobile.



## Capture des animations

