# Lighten Mailbox (Béta) - Allégez votre boîte mail

Lighten Mailbox est une interface web qui permet de faire du ménage dans sa boîte mail. Ce ménage ce fait  soit en supprimant des vieux messages, soit en les téléchargeant au format EML ou HTML/TXT. Le ménage ce fait par critère de date (début/fin)  puis en sélectionnant les dossiers IMAP concernés.

Exemple d'utilisation : Télécharger et archiver ([exemple de rendu](https://lighten-mailbox.zici.fr/archive/example/)) ces emails vieux de 2 ans et les enregistrant sur un disque dur externe, puis (quand vous vous êtes assuré de l’intégrité des donnée) supprimer ces messages.

Instance de test : http://lighten-mailbox.zici.fr/

Exemple d'index d'archive : https://lighten-mailbox.zici.fr/archive/example/ (utilisable hors ligne, dans un navigateur internet depuis une clé usb par exemple...)

## Installation

Pré-requis

* PHP > 7.0
  * php pdo mysql
  * php-imap
  * php yaml
  * php cli
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
#### Dépendance système (debian 10)
apt install php-imap php-yaml php-mailparse curl php-cli php-mbstring git unzip composer
cd /var/www/lighten-mailbox.zici.fr/web # Chemin de votre installation puis
#### Mail parse : 
composer require php-mime-mail-parser/php-mime-mail-parser
### net_dns2
composer require pear/net_dns2
### phpmailer
composer require phpmailer/phpmailer
```

Créer une base de donnée Mysql et y injecter le contenu de  *	*

```bash
cat SQL/lightmb.sql | mysql -u utilisateur -p base
```

Copier le fichier config.yaml

```bash
cp config.yaml_default config.yaml
```

Editer le fichier config.yaml et paramétrer ce dont vous avez besoin, notaement les accès Mysql, le mailer, le répertoire de travail (minimum) :

```yaml
baseUrl: https://lighten-mailbox.zici.fr/:
[...]
db:
    dsn: 'mysql:host=localhost;dbname=lightmb'
    user: 'lightmb'
    password: '**********'
[...]
mailer:
    host: "mail.exemple.com"
    port: 587
    secure: "tls" # ssl or tls or comment for unsecure
    certverify: false
    auth: true
    username: "vous@exemple.com"
    password : "***********"
    from: "vous@exemple.com"
    replyto: "vous@exemple.com"
[...]
url: 
    archive: 'https://lighten-mailbox.zici.fr/archive/'
[...]
dir:
    absolut: '/var/www/lighten-mailbox.zici.fr/web' # Votre répertoire de travail
[...]
crypt:   # https://www.php.net/manual/fr/function.openssl-encrypt.php
    method: 'AES-128-CTR'
    key: 'LesBoitesMailsDoiventMaigrires'
    iv: 'ck98sle98zy39eft'
```

Assurez vous que le dossier "archive" soit accessible en écriture par votre serveur web :

```bash
mkdir /var/www/lighten-mailbox.zici.fr/web/archive
chown -R www-data /var/www/lighten-mailbox.zici.fr/archive/
```

Assurez vous que le fichier de log soit créé et accessible en écriture par votre serveur web.

Exemple de fichier de config : 

```yaml
log:
    path: /var/www/lighten-mailbox.zici.fr/private/lighten.log
    level: 4
```

Créer le répertorie (un endroit non accessible sur le web de préférence), et s'assurer que votre utilisateur php (ici www-data) est accès en écriture à celui-ci :

```bash
mkdir -p /var/www/lighten-mailbox.zici.fr/private
touch /var/www/lighten-mailbox.zici.fr/private/lighten.log
chown -R www-data /var/www/lighten-mailbox.zici.fr/private/
```

Pour le daemon, le script ini.d se trouve dans *init.d/lighten-mailbox* éditer le début du script pour indiquer (a minima) : 

```bash
###### Configure THIS !!
# Chemin de l'application : 
DIR="/var/www/lighten-mailbox.zici.fr/web"
# Utilisateur qui lance le daemon (le même qui exécute php sur votre serveur web, souvent www-data)
USER="web242"
#USER="www-data" 
```

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