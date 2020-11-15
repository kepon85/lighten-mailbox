<?php

if (!is_readable('./config.yaml')) {
    exit('Error: The configuration file is not present, move config.yaml.default to config.yaml');
}
if (($config = yaml_parse_file('./config.yaml')) == false) {
    exit('config.yaml syntax error, check with : http://www.yamllint.com/');
} 

include($config['dir']['absolut'].'/header.php'); 

if (isset($_GET['DeleteApproval']) && isset($_GET['session_id'])) {
    $req = $db->prepare("UPDATE spooler SET status = 2 WHERE task = 2 AND session_id = :session_id");
    $req->bindParam('session_id', $_GET['session_id'], PDO::PARAM_INT);
    $req->execute();
    header('Location: '.$config['baseUrl'].'spool_'.$_GET['session_id']);
    exit();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
	<title><?= $config['title'] ?> <?= $config['subTitle'] ?></title>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon and touch icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/ico//apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/ico/favicon-16x16.png">
    <!-- CSS -->
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/form-elements.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .f1-password.note {
            font-size: 13px;
            text-align: justify;
        }
        .imapwarning {
            display: none;
            color: #FF5050;
        }
        .imapwarning  a {
            text-decoration: underline;
        }
        .form-group.imapAutoDetect {
            display: none;
        }
        .detectAutoWait, .previewWait{
            display: none;
        }
        .detectAutoWait p, .previewWait p {
            text-align: center;
        }
        .detectAuto.error {
            display: none;
            color: #FF3E3E;
        }
        .imapTestCon{
            text-align: center;
            display: none;
        }
        .imapTestCon.success{
            color: #317c1d;
        }
        .imap-password {
            display: none;
        }
        .imapfolder-group {
            display: none;
        }
        .validation-result {
            display: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            border: 1px solid #828282;
            padding: 1px;
            text-align: center;
        }
        .overQuota {
            display: none;
            color: #FF5050;
            font-weight: bold;
        }
        .logo {
            font-weight: 700;
            font-size: 200%;
        }
        .navbar-header{
            padding: 20px;
        }
        .nav.navbar-nav.navbar-right li {
            padding-left: 15px;
        }
        .footer {
            background-color: rgba(255, 255, 255, 0.8);
            margin-top: 60px;
            padding: 20px;
        }
        #howto-video-fr {
            margin: 10px;
            padding: 10px;
            background-color: #d2ffb6;
        }
        #howto-video-fr img {
            float: right;
        }
    </style>
</head>

<body>
    <?php @include_once('./body-start.php'); ?>
<!-- Top menu -->
		<nav class="navbar navbar-inverse navbar-no-bg" role="navigation">
			<div class="container">
				<div class="navbar-header">
					<a class="logo" href="/"><?= $config['title'] ?></a>
				</div>
				<!-- Collect the nav links, forms, and other content for toggling -->
				<div class="collapse navbar-collapse" id="top-navbar-1">
					<ul class="nav navbar-nav navbar-right">
                            <?php
                            foreach($langueEtLocalDispo as $langShort=>$lang) {
                                $flag='';
                                if ($localeshort == $langShort) {
                                    $flag=' drapeauActif';
                                }
                                echo '<li><span class="li-text"><a id="href'.$langShort.'" href="?langueChange='.$langShort.'"><img class="drapeau'.$flag.'" src="'.$config['baseUrl'].'assets/img/'.$langShort.'.png" alt="'.$langShort.'" width="23" height="15" /></a></span></li>';
                            }
                            ?>
						<li>
                            <span class="li-social">
                                <a href="https://framagit.org/kepon/lighten-mailbox/" target="_blank"><i class="fa fa-gitlab"></i></a>
                            </span>
                        </li>
                        <li>
                            <span class="li-social">
                                <a href="https://github.com/kepon85/lighten-mailbox" target="_blank"><i class="fa fa-github"></i></a>
                            </span>
                        </li>

					</ul>
				</div>
			</div>
		</nav>

    <!-- Top content -->
    <div class="top-content">
        <div class="container">
<!--
            
            <div class="row">
                <div class="col-sm-8 col-sm-offset-2 text">
                    <h1>Free <strong>Bootstrap</strong> Wizard</h1>
                    <div class="description">
                        <p>
                            This is a free responsive Bootstrap form wizard. 
                            Download it on <a href="http://azmind.com"><strong>AZMIND</strong></a>, customize and use it as you like!
                        </p>
                    </div>
                </div>
            </div>
-->
            
            <div class="row">
                <div class="col-sm-10 col-sm-offset-1 col-md-8 col-md-offset-2 col-lg-6 col-lg-offset-3 form-box">
                    <form role="form" action="" method="post" class="f1">
                        <h3><?= _('Lighten your mailbox') ?></h3>
                        <?php 
                        if ($config['maintenance']['active'] == true && $_SERVER["REMOTE_ADDR"] == $config['maintenance']['ipForTest']) {
                            echo '<p style="color: red">Maintenance mode ON</p>';
                        }

                        if ($config['maintenance']['active'] == true && $_SERVER["REMOTE_ADDR"] != $config['maintenance']['ipForTest']) {
                            echo '<p>'.$config['maintenance']['publicMsg'].'</p>';
                        } elseif (isset($_GET['session_id'])) { 
                            
                            try {
                                $session = $db->prepare("SELECT session.user, session.domain, session.what
                                                        FROM session
                                                        WHERE id = :session_id
                                                        LIMIT 1");
                                $session->bindParam('session_id', $_GET['session_id'], PDO::PARAM_INT);
                                $session->execute();
                            } catch ( PDOException $e ) {
                                toLog(1, "SELECT session, error : ".$e->getMessage(), 0);
                            }
                            $sessionFetch = $session->fetch();
                            printf('<h4>'._('Status of your request [%d]').'</h4>', $_GET['session_id']);
                            printf('<p>'._('For %s on %s').'</p>', myDecrypt($sessionFetch['user']), $sessionFetch['domain']);
                            // echo
                            if (count($sessionFetch) == 1) {
                                echo "<p>"._("Error session : Not Found")."</p>";
                                unset($refreshAuto);
                            } else {
                                $refreshAuto=true;
                                if ($sessionFetch['what'] == 1 || $sessionFetch['what'] == '2') {
                                    echo "<p>"._("Creation of an archive of your emails: ");
                                    try {
                                        $spoolerArchive = $db->prepare("SELECT spooler.status
                                                                        FROM spooler
                                                                        WHERE session_id = :session_id
                                                                        AND spooler.task = 1
                                                                        LIMIT 1");
                                        $spoolerArchive->bindParam('session_id', $_GET['session_id'], PDO::PARAM_INT);
                                        $spoolerArchive->execute();
                                    } catch ( PDOException $e ) {
                                        toLog(1, "SELECT session, error : ".$e->getMessage(), 0);
                                    }
                                    $spoolerArchiveFetch = $spoolerArchive->fetch();
                                    echo status2humain($spoolerArchiveFetch['status']);
                                    $spoolerWaitBefore=spoolerWaitBefore($_GET['session_id']);
                                    if ($spoolerArchiveFetch['status'] == 2 && $spoolerWaitBefore != 0) {
                                        echo " ";
                                        printf(_("(In front of you : %d, each in turn ...)"), $spoolerWaitBefore);
                                    } 
                                    echo "</p>";
                                    if ($spoolerArchiveFetch['status'] == 5) {
                                        $archive = $db->prepare("SELECT file, dateCreate
                                                                FROM archive
                                                                WHERE session_id = :session_id
                                                                LIMIT 1");
                                        $archive->bindParam('session_id', $_GET['session_id'], PDO::PARAM_INT);
                                        $archive->execute();
                                        $archiveFetch = $archive->fetch();
                                        if (strtotime($archiveFetch['dateCreate']. ' + '.$config['archive']['life'].' days') > time()) {
                                            echo '<p><b>'._('Your archive is available for download until the').' '.date('Y-m-d', strtotime($archiveFetch['dateCreate']. ' + '.$config['archive']['life'].' days')).' '._('by this link:').' <a href="'.$config['url']['archive'].$archiveFetch['file'].'">'.$config['url']['archive'].$archiveFetch['file'].'</a></b></p>';
                                        } else {
                                            printf("<p><i>"._('It is no longer possible to recover your archive, the deadline of %d days has expired.')."</i></p>", $config['archive']['life']);
                                        }
                                    }

                                } 
                                if ($sessionFetch['what'] == 1 || $sessionFetch['what'] == 3) {
                                    echo "<p>"._("Deleting your emails: ");
                                    try {
                                        $spoolerDelete = $db->prepare("SELECT spooler.status
                                                                    FROM spooler
                                                                    WHERE session_id = :session_id
                                                                    AND spooler.task = 2
                                                                    LIMIT 1");
                                        $spoolerDelete->bindParam('session_id', $_GET['session_id'], PDO::PARAM_INT);
                                        $spoolerDelete->execute();
                                    } catch ( PDOException $e ) {
                                        toLog(1, "SELECT session, error : ".$e->getMessage(), 0);
                                    }
                                    $spoolerDeleteFetch = $spoolerDelete->fetch();
                                    echo status2humain($spoolerDeleteFetch['status']);
                                    $spoolerWaitBefore=spoolerWaitBefore($_GET['session_id']);
                                    if ($spoolerDeleteFetch['status'] == 2 && $spoolerWaitBefore != 0) {
                                        echo " ";
                                        printf(_("(In front of you : %d, each in turn ...)"), $spoolerWaitBefore);
                                    } else if ($spoolerDeleteFetch['status'] == 1 && $spoolerArchiveFetch['status'] != 5) {
                                        echo _(' (after archive)');
                                    } else if ($spoolerDeleteFetch['status'] == 1 && $spoolerArchiveFetch['status'] == 5) {
                                        echo ' ::: <a id="deleteApproval" href="'.$config['baseUrl'].'spool_'.$_GET['session_id'].'_DeleteApproval">'._("It's time to clean up!").'</a>';
                                    } else {
                                        unset($refreshAuto);
                                    }
                                    echo "</p>";
                                } 
                            }


                        } else if (isset($_GET['page'])) {
                            if (is_file('page/'.$_GET['page'].'.html')) {
                                include('page/'.$_GET['page'].'.html');
                            } else {
                                echo "404 Not Found";
                            }
                        } else { ?>
                        
                        
                        <p><?= _('Online storage is very important on the environment, this tool offers you to archive your messages by downloading them for storage on an external hard drive / USB key ...') ?></p>
                        <div class="f1-steps">
                            <div class="f1-progress">
                                <div class="f1-progress-line" data-now-value="16.66" data-number-of-steps="4" style="width: 16.66%;"></div>
                            </div>
                            <div class="f1-step active" id="stepUser">
                                <div class="f1-step-icon"><i class="fa fa-user"></i></div>
                                <p><?= _('Your account') ?></p>
                            </div>
                            <div class="f1-step" id="stepSetting">
                                <div class="f1-step-icon"><i class="fa fa-cogs"></i></div>
                                <p><?= _('Setting') ?></p>
                                    </div>
                                    <div class="f1-step" id="stepValida">
                                <div class="f1-step-icon"><i class="fa fa-check"></i></div>
                                <p><?= _('Validation') ?></p>
                            </div>
                        </div>
                        
                        <fieldset>
                            <div class="form-group">
                                <label for="f1-level"><?= _('Level of computer knowledge') ?> : </label>
                                <select id="f1-level" class="f1-level form-control"  name="f1-level">
                                    <option value="1" selected="selected"><?= _('Beginner') ?></option>
                                    <option value="2"><?= _('Enlightened') ?></option>
                                    <option value="3"><?= _('Expert') ?></option>
                                </select>

                                <div id="howto-video-fr">
                                    <?php 
                                    if ($localeshort == 'fr') {
                                        echo "<a href='https://vimeo.com/477628241'><img src='./assets/img/howto-video-fr-preview.png' /></a>Une vidéo tutoriel pour comprendre ce que fait ce machin est disponible par <a href='https://vimeo.com/477628241'>ici</a>. ";
                                    }
                                    ?>
                                </div>

                                <label for="f1-what" class="f1-what"><?= _('What do you want to do ?') ?> (<a href="https://lighten-mailbox.zici.fr/archive/example/" target="_blank"><?= _('archive example') ?></a>) </label>
                                <select id="f1-what" class="f1-what form-control"  name="f1-what">
                                    <option value="1" selected="selected"><?= _('Archive (download these emails) then delete') ?></option>
                                    <option value="2"><?= _('Archive (download these emails)') ?></option>
                                    <option value="3"><?= _('Delete these emails') ?></option>
                                </select>
                                
                                <label for="f1-format" class="f1-format"><?= _('In what format do you want to download these emails ?') ?> : </label>
                                <select id="f1-format" class="f1-format form-control"  name="f1-format">
                                    <option value="html" selected="selected">HTML</option>
                                    <option value="eml">EML <?= _('Open with Thunderbird, Outlook...') ?></option>
                                </select>
                                
                                <div class="form-group f1-date">
                                    <p><?= _('Select emails') ?></p>
                                    <span><?= _('From') ?></span>
                                    <input type="date" id="f1-dateStart" name="f1-dateStart"
                                           value="<?= date('Y-m-d', strtotime('-2 year')) ?>"
                                           min="1990-01-01" max="<?= date('Y-m-d', strtotime('-1 day')) ?>">
                                    
                                    <span><?= _('To') ?></span>
                                    <input type="date" id="f1-dateEnd" name="f1-dateEnd"
                                           value="<?= date('Y-m-d', strtotime('-1 year')) ?>"
                                           min="1990-01-02" max="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                                </div>

                                <div class="form-group f1-folderBeginner">
                                    <label for="f1-folderBeginner"><?= _('The emails that can be found') ?>:</label>
                                    <select id="f1-folderBeginner" class="f1-folderBeginner form-control"  name="f1-folderBeginner">
                                        <option value="1"><?= _('Just the messages received') ?></option>
                                        <option value="2" selected="selected"><?= _('Message received + sent') ?></option>
                                    </select>
                                </div>

                            </div>
                            
                            <h4><?= _('Enter your email information') ?> :</h4>
                            
                            <div class="form-group">
                                <label for="f1-email"><?= _('Email adress') ?> : </label>
                                <input type="text"  autocomplete="off" name="f1-email" placeholder="Email..." class="f1-email form-control" id="f1-email">
                            </div>
                            
                            <div class="imapwarning gmail">
                            <h4><?= _('Warning') ?> gmail.com :</h4>
                            <p><?=_("Gmail uses a special authentication, so it's a bit more complicated than for the others ... For gmail you need:
                                    <ul>
                                        <li>Log in to your account: <a href='https://gmail.com/'>https://gmail.com/</a></li>
                                        <li>Have activated IMAP: <a href='https://support.google.com/mail/'>https://support.google.com/mail/answer/7126229</a></li>
                                        <li>Finally on <a href='https://myaccount.google.com/lesssecureapps'>https://myaccount.google.com/lesssecureapps</a> click on 'Allow less secure applications'</li>
                                    </ul>")?></p></div>
                            <div class="form-group">
                                <label for="f1-password-first"><?= _('Email password') ?> : </label>
                                <input type="password" name="f1-password-first" placeholder="<?= _('Your email password') ?>" class="f1-password-first f1-password form-control" id="f1-password-first">
                                <p class="f1-password note"><?= printf(_('Note for your password : You are using free software (the code of which is available here), so you can verify that it is used (encrypted with %s) so that the software performs the requested action temporarily. As soon as the action is started the password is deleted. Despite this, I encourage you to change your password after using this software (or by changing it now temporarily).'), $config['crypt']['method']) ?></p>
                            </div>
                            
                            <div class="form-group imapAutoDetect">
                                <input class="f1-imapAutoDetect" type="checkbox" name="f1-imapAutoDetect" id="f1-imapAutoDetect" checked="checked"> 
                                <label for="f1-imapAutoDetect"><?= _('Automatic detection of IMAP parameters') ?></label>
                            </div>
                            <div class="f1-buttons">
                                <input class="f1-cgu" type="checkbox" name="f1-cgu" id="f1-cgu">
                                <label for="f1-cgu"><?= _('I accept the general terms of use') ?> : <a href="./cgu.html" target="_blank">CGU</a></label>
                                <button type="button" class="btn btn-next"><?= _('Next') ?></button>
                            </div>
                        </fieldset>

                        <fieldset>
                            <h4><?= _('Email IMAP Connexion') ?> :</h4>
                            
                            <input type="hidden" name="imapTestCon" id="imapTestCon" value="0" />
                            
                            <div class="detectAutoWait">
                                <p><?= _('We are trying to detect your IMAP configuration, please wait') ?></p>
                                <p><img src="assets/img/wait.svg" /></p>
                                <button type="button" class="btn btn-previous"><?= _('Cancel') ?></button>
                            </div>
                            
                            <div class="previewWait">
                                <p><?= _('We create an overview for validation, please wait') ?></p>
                                <p><img src="assets/img/wait.svg" /></p>
                                <button type="button" class="btn btn-previous"><?= _('Cancel') ?></button>
                            </div>
                            
                            <div class="detectAuto imapTestCon error ResultError">
                                <p><?= _('An error occured') ?> : <span id="detectAutoResultError"></span></p>
                            </div>
                            
                            <div class="detectAuto error ResultFalse">
                                <p><?= _('We were unable to automatically detect your configuration, but you can specify it manually. Contact your email provider for more information on your IMAP connection settings. Check if your password is correct, otherwise automatic discovery is impossible.') ?></p>
                            </div>
                            
                            <div class="imapTestCon error ResultFalse">
                                <p><?= _('Contact your email provider for more information on your IMAP connection settings. Check if your password is correct.') ?></p>
                            </div>

                            <div class="imapTestCon success">
                                <p><?= _('The connection to your mailbox has been successful!') ?></p>
                            </div>

                            <div class="form-group imap-form imap-detectAuto">
                                <input class="btn" type="button" id="f1-detectAuto" value="<?= _('Automatically search for IMAP settings') ?>" />
                            </div>
                            
                            <div class="form-group imap-form imap-user">
                                <label for="f1-user"><?= _('IMAP username') ?> : </label>
                                <select id="f1-user" class="f1-user form-control imap-config"  name="f1-user">
                                    <option value="%u">usernameOnly</option>
                                    <option value="%e" selected="selected">fullEmailWithDomain</option>
                                </select>
                            </div>
                            
                            <div class="form-group imap-form imap-password">
                                <label for="f1-password"><?= _('IMAP password') ?> : </label>
                                <input type="password" name="f1-password" placeholder="<?= _('Your email password') ?>" class="f1-password form-control imap-config" id="f1-password">
                            </div>
                            
                            <div class="form-group imap-form">
                                <label class="" for="f1-server"><?= _('IMAP server') ?></label>
                                <input type="text" name="f1-server" placeholder="ex: imap.provider.com" class="f1-server form-control imap-config" id="f1-server">

                                <label class="" for="f1-port"><?= _('IMAP port') ?></label>
                                <select id="f1-port" class="f1-port form-control imap-config"  name="f1-port">
                                    <option value="143">143</option>
                                    <option value="993" selected="selected">993</option>
                                </select>
                            </div>
                            
                            <div class="form-group imap-form">
                                <label for="f1-secure"><?= _('Connection security') ?> : </label>
                                <select id="f1-secure" class="f1-secure form-control imap-config"  name="f1-secure">
                                        <option value="0"><?= _('None') ?></option>
                                        <option value="1"><?= _('STARTTLS') ?></option>
                                        <option value="2" selected="selected"><?= _('SSL/TLS') ?></option>
                                </select>
                                <p><input type="checkbox" id="f1-cert" checked="checked" class="imap-config" />
                                <label for="f1-cert"><?= _('Validate the certificate') ?> : </label></p>
                                <label for="f1-auth"><?= _('Authentication method') ?> : </label>
                                <select id="f1-auth" class="f1-ssl form-control imap-config"  name="f1-auth">
                                        <option value="0"><?= _('Normal') ?></option>
                                        <option value="1" selected="selected"><?= _('Encrypt') ?></option>
                                </select>
                            </div>

                            <div class="form-group imapfolder-group">
                                <label for="f1-imapfolder"><?= _('Select the IMAP folders to use') ?></label>
                                <div id="imapfolder">
                                </div>
                            </div>

                            <div class="imapTestCon">
                                <img src="assets/img/wait.svg" />
                                <?= _('Current connection...') ?>
                            </div>
                            
                            <div class="f1-buttons imap-form">
                                <button type="button" class="btn btn-previous"><?= _('Cancel') ?></button>
                                <button type="button" class="btn btn-next"><?= _('Next') ?></button>
                                <button type="button" class="btn btn-check"><?= _('Check the connection') ?></button>
                            </div>
                        </fieldset>

                        <fieldset>
                            <h4><?= _('Validation') ?><h4>
                            <div class="form-group">
                                <p><?= _('Here is an overview of the impact of your request:') ?></p>
                                <table id="folderPreviewList">
                                    <tr>
                                        <th><?= _('Folder name') ?></th><th><?= _('Number of emails selected') ?></th><th><?= _('Selected email sizes') ?></th>
                                    </tr>
                                </table>
                            </div>
                            <div class="overQuota"><?= _('Over quota : Your archive exceeds the allowed quota size. A tip: reduce the time range (just 1 years, 6 months ...)') ?></div>
                            <div class="validation-result">
                                <p><?= _('Here we go !') ?></p>
                                <p><?= _('You can follow the progress of your request by the address: ') ?> <span id="spoolUrl"></span></p>
                                <p><?= _('You will be redirected there automatically in a few seconds') ?></p>
                            </div>
                            <div class="f1-buttons">
                                <button type="button" class="btn btn-previous"><?= _('Cancel') ?></button>
                                <button type="button" class="btn btn-next btn-validation"><?= _('Validate') ?></button>
                            </div>
                        </fieldset>
                        
                        <input type="hidden" name="f1-session_id" id="f1-session_id">
                        
                     <?php } ?>
                    </form>
                </div>


                <div class="col-sm-10 col-sm-offset-1 col-md-8 col-md-offset-2 col-lg-6 col-lg-offset-3 footer">
                    Lighten-mailbox v<?= VERSION ?> - By <a href="https://david.mercereau.info">David Mercereau</a> - Licence : <a href="https://creativecommons.org/publicdomain/zero/1.0/deed.fr"><img src="assets/img/CC-Zero-badge.svg" width="100" alt="Créative Common Zero" /></a> - <a href="https://framagit.org/kepon/lighten-mailbox/">Sources</a>
                </div>

            </div>
                
        </div>
    </div>


    <!-- Javascript -->
    <script src="assets/js/jquery-3.4.1.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/popper.min.js"></script>

    <script src="assets/js/jquery.backstretch.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script type="text/javascript">
        <?php if (empty($_GET['session_id'])) { ?>

        $('.imap-config').on('change',function(){
            $('#imapTestCon').val(0);
            $('.btn-check').show();
            $('.btn-next').hide();
        });
        $('#f1-email').on('change',function(){
            emailChange();
        });
        $('#f1-what').on('change',function(){
            if ($('#f1-what').val() == 3) {
                $('.f1-format').hide();
            } else {
                $('.f1-format').show();
            }
        });
        // On recopie 
        $('#f1-password-first').on('change',function(){
            $('#f1-password').val($('#f1-password-first').val());
        });
        $('#f1-detectAuto').on('click',function(){
            imapDetectConfig();
        });
        $('#f1-secure').on('change',function(){
            if ($('#f1-secure').val() == 2) {
                $('#f1-port').val(993);
                $('#f1-auth').val(0);
            } else if ($('#f1-secure').val() == 1) {
                $('#f1-port').val(143);
                $('#f1-auth').val(0);
            } else {
                $('#f1-port').val(143);
                $('#f1-auth').val(1);
            }
        });
        $('#f1-level').on('change',function(){
            levelChange();
        });

        emailChange();
        levelChange();

        <?php } else { ?>
            $('#deleteApproval').click( function(e) {e.preventDefault(); 
                if (window.confirm("<?= _('You are about to delete the messages in your previously selected mailbox.\nNote: if you have requested an archive check that the download went well, that the archive is readable and that the messages are present.') ?>")) { 
                    document.location.href='<?= $config['baseUrl'] ?>spool_<?= $_GET['session_id'] ?>_DeleteApproval';
                }
                return false;
                } );
        <?php } ?>

        <?php if (isset($refreshAuto)) { ?>
            function reFresh() {
                location.reload(true)
            }
            window.setInterval("reFresh()",8000);
        <?php } ?>

        // var configDirArchive = "<?= $config['dir']['archive'] ?>";
        var configQuotaArchive = "<?= $config['quotaArchive'] ?>";
        var configBaseUrl = "<?= $config['baseUrl'] ?>";
    </script>
    
    <?php @include_once('./body-end.php'); ?>
</body>

</html>
<?php @include_once('./footer.php'); ?>
