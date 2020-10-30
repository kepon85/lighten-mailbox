<?php

if (!is_readable('./config.yaml')) {
    exit('Error: The configuration file is not present, move config.yaml.default to config.yaml');
}
if (($config = yaml_parse_file('./config.yaml')) == false) {
    exit('config.yaml syntax error, check with : http://www.yamllint.com/');
} 

include($config['dir']['absolut'].'/header.php'); 

// Création session 
if (isset($_POST['id']) && isset($_POST['email']) && isset($_POST['dateStart']) && isset($_POST['dateEnd']) && isset($_POST['what']) && isset($_POST['format'])) {
    try {
		$email = splitEmailAddress($_POST['email']);
		$user = myCrypt($email['user']);
		$dateStart = strptime($_POST['dateStart'], '%Y-%m-%d');
		$dateStartTimestamp = mktime(0, 0, 0, $dateStart['tm_mon']+1, $dateStart['tm_mday'], $dateStart['tm_year']+1900);
		$dateEnd = strptime($_POST['dateEnd'], '%Y-%m-%d');
		$dateEndTimestamp = mktime(0, 0, 0, $dateEnd['tm_mon']+1, $dateEnd['tm_mday'], $dateEnd['tm_year']+1900);
		$req = $db->prepare("INSERT INTO session (id, user, domain, dateCreate, dateStart, dateEnd, what, format) VALUES (:id, :user, :domain, '".time()."', :dateStart, :dateEnd, :what, :format)");
		$req->bindParam('id', $_POST['id'], PDO::PARAM_INT);
		$req->bindParam('user', $user, PDO::PARAM_STR);
		$req->bindParam('domain', $email['domain'], PDO::PARAM_STR);
		$req->bindParam('dateStart', $dateStartTimestamp, PDO::PARAM_INT);
		$req->bindParam('dateEnd', $dateEndTimestamp, PDO::PARAM_INT);
		$req->bindParam('what', $_POST['what'], PDO::PARAM_INT);
		$req->bindParam('format', $_POST['format'], PDO::PARAM_STR);
		$req->execute();
    } catch ( PDOException $e ) {
		toLog(1, "INSERT in session, error : ".$e->getMessage(), 0);
    }	
    echo '{"result": true}';
}

// autodétection config imap
if (isset($_POST['imapDetectConfig']) && isset($_POST['session_id']) && isset($_POST['user']) && isset($_POST['domain']) && isset($_POST['password'])) {
    $connexionSuccess = false;
	$mxConca = mxConca($_POST['domain']);
	// 1er tentative, si un paramètre est proposé par l'admin (session_id = null)
	try {
		$selectNoSession = $db->prepare("SELECT domain, imap_server, imap_port, imap_user, imap_secure, imap_auth, imap_cert 
											FROM `open` 
											WHERE (domain = :domain OR mx = :mx) AND session_id IS NULL 
											ORDER BY dateCreate DESC 
											LIMIT 1");
		$selectNoSession->bindParam('domain', $_POST['domain'], PDO::PARAM_STR);
		$selectNoSession->bindParam('mx', $mxConca, PDO::PARAM_STR);
		$selectNoSession->execute();
    } catch ( PDOException $e ) {
		toLog(1, "SELECT (no session) in open, error : ".$e->getMessage(), 0);
    }
    $imap_config_nosession = $selectNoSession->fetch();
    $selectNoSession->closeCursor();
    if (count($imap_config_nosession) > 1) {
		if ($imap_config_nosession['imap_user'] == '%e') {
			$imap_user = $_POST['user'].'@'.$_POST['domain'];
		} else {
			$imap_user = $_POST['user'];
		}
		$retourImapConfigNoSession = imapTestCon($_POST['session_id'], $imap_config_nosession['domain'], $imap_config_nosession['imap_server'], $imap_config_nosession['imap_port'], $imap_user, $_POST['password'], $imap_config_nosession['imap_secure'], $imap_config_nosession['imap_auth'], $imap_config_nosession['imap_cert']);
		if ($retourImapConfigNoSession['result'] == true) {
			$retourImapConfigNoSession['src'] = 'admin';
			echo json_encode($retourImapConfigNoSession);
			$connexionSuccess = true;
		}
	}
	// 2ème tentative, si des paramètres sont proposé par les utilisateurs
    if ($connexionSuccess == false) {
		try {
			$selectSession = $db->prepare("SELECT count(*) nb, domain, imap_server, imap_port, imap_user, imap_secure, imap_auth, imap_cert 
											FROM `open` 
											WHERE (domain = :domain OR mx = :mx) AND session_id IS NOT NULL 
											GROUP by imap_server, imap_port, imap_user, imap_auth, imap_cert 
											ORDER BY nb DESC, dateCreate DESC
											LIMIT 1");
			$selectSession->bindParam('domain', $_POST['domain'], PDO::PARAM_STR);
			$selectSession->bindParam('mx', $mxConca, PDO::PARAM_STR);
			$selectSession->execute();
		} catch ( PDOException $e ) {
			toLog(1, "SELECT (with session) in open, error : ".$e->getMessage(), 0);
		}
		$imap_config_session = $selectSession->fetch();
		$selectSession->closeCursor();
		if (count($imap_config_session) > 1) {
			if ($imap_config_session['imap_user'] == '%e') {
				$imap_user = $_POST['user'].'@'.$_POST['domain'];
			} else {
				$imap_user = $_POST['user'];
			}
			$retourImapConfigSession = imapTestCon($_POST['session_id'], $imap_config_session['domain'], $imap_config_session['imap_server'], $imap_config_session['imap_port'], $imap_user, $_POST['password'], $imap_config_session['imap_secure'], $imap_config_session['imap_auth'], $imap_config_session['imap_cert']);
			if ($retourImapConfigSession['result'] == true) {
				$retourImapConfigSession['src'] = 'user';
				echo json_encode($retourImapConfigSession);
				$connexionSuccess = true;
			}
		}
	}
	// 3ème, autodétection
	if ($connexionSuccess == false) {
		echo json_encode(imapAutoDetect($_POST['session_id'], $_POST['user'], $_POST['domain'], $_POST['password']));
	} 
}

// test connexion 
if (isset($_POST['imapTestCon']) && isset($_POST['session_id']) && isset($_POST['domain']) && isset($_POST['port']) && isset($_POST['user']) && isset($_POST['password']) && isset($_POST['secure']) && isset($_POST['auth']) && isset($_POST['cert'])) {
	echo json_encode(imapTestCon($_POST['session_id'], $_POST['domain'], $_POST['server'], $_POST['port'], $_POST['user'], $_POST['password'], $_POST['secure'], $_POST['auth'], $_POST['cert']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Sauvegarde des folder et liste de ceux-ci
if (isset($_POST['imapFolderValidation']) && isset($_POST['session_id']) && isset($_POST['imapfolder']) && isset($_POST['password'])) {
	try {
		$selectSession = $db->prepare("SELECT user, session.domain, imap_folder, dateStart, dateEnd, what, format, imap_server, imap_port, imap_user, imap_secure, imap_auth, imap_cert
										FROM session,  open 
										WHERE session.id = open.session_id AND session_id = :session_id
										LIMIT 1");
		$selectSession->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
		$selectSession->execute();
	} catch ( PDOException $e ) {
		toLog(1, "SELECT imapFolderValidation, error : ".$e->getMessage(), 0);
	}
	$imapConfig = $selectSession->fetch();
	$selectSession->closeCursor();
	if (count($imapConfig) > 1) {
		if ($imapConfig['imap_user'] == '%e') {
			$imap_user = myDecrypt($imapConfig['user']).'@'.$imapConfig['domain'];
		} else {
			$imap_user = myDecrypt($imapConfig['user']);
		}
	}
	$imapGetData = imapGetData('preview', $_POST['session_id'], $imapConfig['imap_server'], $imapConfig['imap_port'], $imap_user, $_POST['password'], $imapConfig['imap_secure'], $imapConfig['imap_auth'], $imapConfig['imap_cert'], $_POST['imapfolder'], $imapConfig['dateStart'], $imapConfig['dateEnd'], $imapConfig['what'], $imapConfig['format']);
	echo json_encode($imapGetData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	// Enregistrement liste dossier & totaux :
	$imapfolder_json=json_encode($_POST['imapfolder']);
	try {
		$req = $db->prepare("UPDATE session SET imap_folder = :imap_folder, total_nb = :total_nb, total_size = :total_size  WHERE id = :session_id");
		$req->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
		$req->bindParam('imap_folder', $imapfolder_json, PDO::PARAM_STR);
		$req->bindParam('total_size', $imapGetData['totalSize'], PDO::PARAM_INT);
		$req->bindParam('total_nb', $imapGetData['totalNb'], PDO::PARAM_INT);
		$req->execute();
    } catch ( PDOException $e ) {
		toLog(1, "INSERT in session, error : ".$e->getMessage(), 0);
    }	
}

// Validation, mise en spooler
if (isset($_POST['spoolerGo']) && isset($_POST['session_id']) && isset($_POST['password'])) {
	try {
		$selectSession = $db->prepare("SELECT user, domain, what
										FROM `session` 
										WHERE id = :session_id
										LIMIT 1");
		$selectSession->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
		$selectSession->execute();
	} catch ( PDOException $e ) {
		toLog(1, "SELECT session error : ".$e->getMessage(), 0);
	}
	$session = $selectSession->fetch();
	try {
		$password = myCrypt($_POST['password']);
		if ($session['what'] == 1) {
			// Requête d'archivage à exécuter
			$req = $db->prepare("INSERT INTO spooler (session_id, password, task, status) VALUES (:session_id, :password, 1, 2)");
			$req->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
			$req->bindParam('password', $password, PDO::PARAM_STR);
			$req->execute();
			// Requête de suppression en attente
			$req = $db->prepare("INSERT INTO spooler (session_id, password, task, status) VALUES (:session_id, :password, 2, 1)");
			$req->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
			$req->bindParam('password', $password, PDO::PARAM_STR);
			$req->execute();
		} elseif ($session['what'] == 2) {
			// Requête d'archivage à exécuter
			$req = $db->prepare("INSERT INTO spooler (session_id, password, task, status) VALUES (:session_id, :password, 1, 2)");
			$req->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
			$req->bindParam('password', $password, PDO::PARAM_STR);
			$req->execute();
		} elseif ($session['what'] == 3) {
			// Requête de suppression à exécuter
			$req = $db->prepare("INSERT INTO spooler (session_id, password, task, status) VALUES (:session_id, :password, 2, 2)");
			$req->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
			$req->bindParam('password', $password, PDO::PARAM_STR);
			$req->execute();
		}
		echo '{"result": true}';
    } catch ( PDOException $e ) {
		toLog(1, "INSERT in spooler, error : ".$e->getMessage(), 0);
		echo '{"result": false}';
    }
    $urlSpool=$config['baseUrl'].'spool_'.$_POST['session_id'];
    $mailSend_return = mailSend(username2email($session['user'], $session['domain']), _('Queuing'), _('Hello').'<br /></br>

'._('You can follow the progress of your request by the address: ').'<a href="'.$urlSpool.'">'.$urlSpool.'</a>');
    if ($mailSend_return != true) {
    	toLog(1, 'Erreur mailSend '.$mailSend_return);
	}
}


exit();
?>
