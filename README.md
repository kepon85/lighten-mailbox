# Lighten Mailbox (Béta) - Allégez votre boîte mail

Lighten Mailbox est une interface web qui permet de faire du ménage dans sa boîte mail. Ce ménage ce fait  soit en supprimant des vieux messages, soit en les téléchargeant au format EML ou HTML/TXT. Le ménage ce fait par critère de date (début/fin)  puis en sélectionnant les dossiers IMAP concernés.

Exemple d'utilisation : Télécharger et archiver ([exemple de rendu](https://lighten-mailbox.zici.fr/archive/example/)) ces emails vieux de 2 ans et les enregistrant sur un disque dur externe, puis (quand vous vous êtes assuré de l’intégrité des donnée) supprimer ces messages.

Instance de test : http://lighten-mailbox.zici.fr/

Exemple d'index d'archive : https://lighten-mailbox.zici.fr/archive/example/ (utilisable hors ligne, dans un navigateur internet depuis une clé usb par exemple...)

## Installation

Pré-requis

* PHP > 7.0
  * php dbo mysql
  * php yaml
  * composer
    * php-mime-mail-parser
    * net_dns2
    * phpmailer
* Apache http serveur (for htaccess but nginix is possible)
* Mysql
* openssl

Télécharger le dépôt git et le rendre accessible en HTTP

Installation des dépendances php : 

```bash
composer require php-mime-mail-parser/php-mime-mail-parser
composer require pear/net_dns2
composer require phpmailer
```

Créer une base de donnée Mysql et y injecter le contenu de  *	*

```bash
cat SQL/lightmb.sql | mysql -u utilisateur -p base
```

Copier le fichier config.yaml

```bash
cp config.yaml_default config.yaml
```

Editer el fichier config.yaml et paramétrer ce dont vous avez besoin, notaement les accès Mysql, le mailer...

Pour le daemon, le script ini.d se trouve dans *init.d/lighten-mailbox*

## Changelog

* Futur :
  * Orientation "serveur dédier" limiter a une liste de @domaine et ou un serveur
  * BUG index.html si eml masque lien des archives !
  * MAX usage... par IP et/ou cookies... ? (pour éviter que le service ne soit détourné)
  * Estimer temps/durée  (avant validation)
  * Prévenir de "combien de personne avant vous" avant validation et combien de temps ça va prendre (estimation possible avec le size de tout les spooler en cours + config débit download)
  * Mot de passe personnalisé sur archive (pour l'instant on met le mot de passe mail)
  * Tabulator : 
    * Faire meilleur recherche (avec OU / ET...  (plusieurs champs quoi)
    * recherche dans toutes les colonnes
    * transformer en PDF jsPDF http://tabulator.info/docs/4.5/download#pdf
    * imprimer
  * Multi serveur ?

## Licence 

By [David Mercereau](https://david.mercereau.info)  Licence : [![Créative Common Zero](https://lighten-mailbox.zici.fr/assets/img/CC-Zero-badge.svg)](https://creativecommons.org/publicdomain/zero/1.0/deed.fr) 

Theme en Wizard based on "Material Bootstrap Wizard" (free responsive Bootstrap form wizard download it on <a href="http://azmind.com"><strong>AZMIND</strong></a> !)

Les projets libre utilisé dans ce projet : 

* Tabulator http://tabulator.info
* Jquery