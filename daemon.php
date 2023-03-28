<?php

// Vérification cli
if(php_sapi_name() != 'cli') {
	exit('Vous devez lancer ce script en "cli" (terminal)');
}

// Gestion options
$options = getopt('d:');
if (empty($options['d'])) {
	$config['dir']['absolut']='.';
} else {
	$config['dir']['absolut']=$options['d'];
}

if (!is_readable($config['dir']['absolut'].'/config.yaml')) {
    exit("Error: The configuration file is not present, move config.yaml.default to config.yaml\n");
}
if (($config = yaml_parse_file($config['dir']['absolut'].'/config.yaml')) == false) {
    exit("config.yaml syntax error, check with : http://www.yamllint.com/ \n");
} 

include($config['dir']['absolut'].'/header.php'); 

// For interpret signal
declare(ticks = 1);

function sig_handler($sig) {
    toLog(1, 'Daemon STOP');
    exit;
}

pcntl_signal(SIGTERM, "sig_handler");

toLog(3, "Lancement du daemon");

$dayTask=0;
while (true) {
	try {
		$spoolerNext = $db->prepare("SELECT spooler.id, spooler.session_id, spooler.password, spooler.task, session.user, session.domain, session.dateCreate, session.imap_folder, session.dateStart, session.dateEnd, session.what, session.format, session.total_size, session.total_nb, open.imap_server, open.imap_port, open.imap_user, open.imap_secure, open.imap_auth, open.imap_cert  FROM spooler, session, open
										WHERE spooler.session_id = session.id
										AND open.session_id = session.id
										AND status = 2
										ORDER BY  date ASC");
		$spoolerNext->bindParam('session_id', $_POST['session_id'], PDO::PARAM_INT);
		$spoolerNext->execute();
	} catch ( PDOException $e ) {
		toLog(1, "SELECT spooler, error : ".$e->getMessage(), 0);
	}
	
	$spoolerNextArrayFetch = $spoolerNext->fetchAll();
	if (count($spoolerNextArrayFetch) == 0) {
		toLog(5, "Rien dans le spooler");
	} else {

		foreach($spoolerNextArrayFetch as $spoolerNextArray) {

			if ($spoolerNextArray['imap_user'] == '%e') {
				$spoolerNext_user = myDecrypt($spoolerNextArray['user']).'@'.$spoolerNextArray['domain'];
			} else {
				$spoolerNext_user = myDecrypt($spoolerNextArray['user']);
			}

			$req = $db->prepare("UPDATE spooler SET status = 3 WHERE id = :id");
			$req->bindParam('id', $spoolerNextArray['id'], PDO::PARAM_INT);
			$req->execute();


			if ($spoolerNextArray['task'] == 1) {
				toLog(3, "Archive demandé dans le spooler pour la session ".$spoolerNextArray['session_id']);

				$imapGetData_return = imapGetData('archive', $spoolerNextArray['session_id'], $spoolerNextArray['imap_server'], $spoolerNextArray['imap_port'], $spoolerNext_user, myDecrypt($spoolerNextArray['password']), $spoolerNextArray['imap_secure'], $spoolerNextArray['imap_auth'], $spoolerNextArray['imap_cert'], json_decode($spoolerNextArray['imap_folder']), $spoolerNextArray['dateStart'], $spoolerNextArray['dateEnd'], $spoolerNextArray['what'], $spoolerNextArray['format']);

				// Si ça c'est bien passé
				if ($imapGetData_return['result'] == true) {
					// Le nom du fichier généré sera : 
					$fileNameGen=substr(string2url($spoolerNextArray['session_id'].$spoolerNext_user.'.zip'), 0, 70);
					toLog(5, "Copie du tableau html + des assests");
					// On récupère le dossier "lib" + le "index.html"
					// copy($config['dir']['templateTab']['html'], $config['dir']['archive']."/".$spoolerNextArray['session_id']."/");
					// toLog(5, $config['dir']['templateTab']['html'] . '-'.  $config['dir']['archive']."/".$spoolerNextArray['session_id']."/");
					// Copy asset (lib)
					recurse_copy($config['dir']['absolut'].'/'.$config['dir']['templateTab'], $config['dir']['absolut'].'/'.$config['dir']['archive']."/".$spoolerNextArray['session_id']);
					// On zip
					if (Zip($config['dir']['absolut'].'/'.$config['dir']['archive']."/".$spoolerNextArray['session_id']."/", $config['dir']['absolut'].'/'.$config['dir']['archive']."/".$fileNameGen)) {
						toLog(2, "Le ZIP est fait, on l'enregistre : ".$fileNameGen);
						// Enregistrement de l'archive : 
						$req = $db->prepare("INSERT INTO archive (session_id, file) VALUES (:session_id, :file)");
						$req->bindParam('session_id', $spoolerNextArray['session_id'], PDO::PARAM_INT);
						$req->bindParam('file', $fileNameGen, PDO::PARAM_STR);
						$req->execute();
						// Changement de status dans la bd
						$req = $db->prepare("UPDATE spooler SET status = 5, password = null WHERE id = :id");
						$req->bindParam('id', $spoolerNextArray['id'], PDO::PARAM_INT);
						$req->execute();
						// Ménage
						rrmdir($config['dir']['absolut'].'/'.$config['dir']['archive']."/".$spoolerNextArray['session_id']."/");

						// Notification archive prête
					    $urlSpool=$config['baseUrl'].'spool_'.$spoolerNextArray['session_id'];
					    $deleteApproval='';
					    if ($spoolerNextArray['what'] == 1) {
					    	$deleteApproval='<br /><br />

'._('When you have downloaded your archive and you have checked that its content is readable, you can start the cleaning (deletion of archived messages) by this link (irrevocable decision):').' <a href="'.$urlSpool.'_DeleteApproval">'.$urlSpool.'_DeleteApproval</a>';
						}
						$mailSend_return = mailSend(username2email($spoolerNextArray['user'], $spoolerNextArray['domain']), _('Archive ready to download'), _('Hello').'<br /></br>

'._('Your archive is available for download until the').' '.date('Y-m-d', time()+$config['archive']['life']*86400).'  '._('by this link:').' <a href="'.$config['url']['archive'].$fileNameGen.'">'.$config['url']['archive'].$fileNameGen.'</a>'.$deleteApproval);
					    if ($mailSend_return != true) {
					    	toLog(1, 'Erreur mailSend '.$mailSend_return);
						}

					} else {
						//  Erreur à la création du zip
						toLog(2, "Erreur dans le spooler à la création du ZIP sur la session ".$spoolerNextArray['session_id']);
						$req = $db->prepare("UPDATE spooler SET status = 0 WHERE id = :id");
						$req->bindParam('id', $spoolerNextArray['id'], PDO::PARAM_INT);
						$req->execute();
						$mailSend_return = mailSend(username2email($spoolerNextArray['user'], $spoolerNextArray['domain']), _('Archive error'), _('Hello').'<br /></br>

'._('Sorry but the archiving encountered too many errors for you to download.'));
					    if ($mailSend_return != true) {
					    	toLog(1, 'Erreur mailSend '.$mailSend_return);
						}
					}
				} else {
					toLog(2, "Erreur dans le spooler sur la session ".$spoolerNextArray['session_id'].' : '.$imapGetData_return['resultMsg']);
					$req = $db->prepare("UPDATE spooler SET status = 0 WHERE id = :id");
					$req->bindParam('id', $spoolerNextArray['id'], PDO::PARAM_INT);
					$req->execute();
					$mailSend_return = mailSend(username2email($spoolerNextArray['user'], $spoolerNextArray['domain']), _('Archive error'), _('Hello').'<br /></br>

'._('Sorry but the archiving encountered too many errors for you to download.'));
				    if ($mailSend_return != true) {
				    	toLog(1, 'Erreur mailSend '.$mailSend_return);
					}
				}
			} else if ($spoolerNextArray['task'] == 2) {
				toLog(3, "Suppression demandé dans le spooler pour la session ".$spoolerNextArray['session_id']);
				$imapDeleteData_return = imapDeleteData($spoolerNextArray['session_id'], $spoolerNextArray['imap_server'], $spoolerNextArray['imap_port'], $spoolerNext_user, myDecrypt($spoolerNextArray['password']), $spoolerNextArray['imap_secure'], $spoolerNextArray['imap_auth'], $spoolerNextArray['imap_cert'], json_decode($spoolerNextArray['imap_folder']), $spoolerNextArray['dateStart'], $spoolerNextArray['dateEnd']);
				// Changement de status dans la bd
				if ($imapDeleteData_return['result'] == true) {
					$req = $db->prepare("UPDATE spooler SET status = 5, password = null WHERE id = :id");
					$req->bindParam('id', $spoolerNextArray['id'], PDO::PARAM_INT);
					$req->execute();
					$mailSend_return = mailSend(username2email($spoolerNextArray['user'], $spoolerNextArray['domain']), _('You have relieved your mailbox'), _('Hello').'<br /></br>

'._('Congratulations, you have relieved your mailbox of').' '.$spoolerNextArray['total_nb'].' '._('emails').' ('.convertOctect2humain($spoolerNextArray['total_size']).')');
				    if ($mailSend_return != true) {
				    	toLog(1, 'Erreur mailSend '.$mailSend_return);
					}
				} else {
					$req = $db->prepare("UPDATE spooler SET status = 0 WHERE id = :id");
					$req->bindParam('id', $spoolerNextArray['id'], PDO::PARAM_INT);
					$req->execute();
				}
			}
		}
	}
	# Day task
	if ($dayTask+86400 < time()) {
		toLog(1, 'Lancement des tâches quotidiennes');
		// Ménage dans els 
		try {
			$archive = $db->prepare("SELECT session_id, file 
						    FROM archive
						    WHERE dateCreate < '".date('Y-m-d', time()-$config['archive']['life']*86400)."'");
			$archive->execute();
		} catch ( PDOException $e ) {
			toLog(1, "SELECT archive, error : ".$e->getMessage(), 0);
		}
		$archiveArrayFetch = $archive->fetchAll();
		if (count($archiveArrayFetch) == 0) {
			toLog(5, "Rien à supprimer dans les archives");
		} else {
			foreach($archiveArrayFetch as $archiveFetch) {
				toLog(2, 'Suppression de l\'archives expiré : '.$archiveFetch['file']);
				unlink($config['dir']['absolut'].'/'.$config['dir']['archive'].'/'.$archiveFetch['file']);
				$req = $db->prepare("DELETE FROM archive WHERE session_id = :session_id");
				$req->bindParam('session_id', $archiveFetch['session_id'], PDO::PARAM_INT);
				$req->execute();
			}
		}
		// Relance suppression 
		try {
			$spoolerWait = $db->prepare("SELECT session.id, spooler.session_id, spooler.date, user, domain, archive.file
										FROM spooler, session, archive
										WHERE spooler.session_id = session.id
										AND spooler.session_id = archive.session_id
										AND what = 1
										AND task = 2 
										AND status = 1");
			$spoolerWait->execute();
		} catch ( PDOException $e ) {
			toLog(1, "SELECT archive, error : ".$e->getMessage(), 0);
		}
		$spoolerWaitArrayFetch = $spoolerWait->fetchAll();
		if (count($spoolerWaitArrayFetch) == 0) {
			toLog(5, "Aucune relance à faire");
		} else {
			foreach($spoolerWaitArrayFetch as $spoolerWaitFetch) {
				foreach($config['delete']['relaunch'] as $relaunch) {
					if (date('Y-m-d', strtotime($spoolerWaitFetch['date']. ' + '.$relaunch.' days')) == date('Y-m-d')) {
						toLog(2, 'Relance à +'.$relaunch.' pour la session : '.$spoolerWaitFetch['id']);
						$urlSpool=$config['baseUrl'].'spool_'.$spoolerWaitFetch['session_id'];
						$mailSend_return = mailSend(username2email($spoolerWaitFetch['user'], $spoolerWaitFetch['domain']), '['._('Relaunch').'] '._('Archive ready to download'), _('Hello').'<br /></br>

'._('Your archive is available for download until the').' '.date('Y-m-d', time()+$config['archive']['life']*86400).'  '._('by this link:').' <a href="'.$config['url']['archive'].$spoolerWaitFetch['file'].'">'.$config['url']['archive'].$spoolerWaitFetch['file'].'</a>'.'<br /><br />

'._('And above all, if you have already downloaded it:').'<br /><br />

<b>'._('You can start the cleaning (deletion of archived messages) by this link (irrevocable decision):').'</b> <a href="'.$urlSpool.'_DeleteApproval">'.$urlSpool.'_DeleteApproval</a>');
			    		if ($mailSend_return != true) {
			   	 			toLog(1, 'Erreur relance mailSend '.$mailSend_return);
						}
					}
				}

			}
		}
		
		$dayTask=time();
	}
	sleep($config["daemon"]["sleep"]);
}

?>
