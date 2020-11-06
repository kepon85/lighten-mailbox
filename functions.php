<?php

require_once __DIR__.'/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function cleanTxt($char) {
	return preg_replace(
			"([^a-zA-Z0-9@-])", 
			'', 
			$char
		);    
}
function splitEmailAddress($email){
	$split = explode('@', $email);
	$arr['user']=$split[0];
	$arr['domain']=$split[1];
	return $arr;
}
function myCrypt($value) {
	global $config;
	return openssl_encrypt($value, $config['crypt']['method'], $config['crypt']['key'], $config['crypt']['options'], $config['crypt']['iv']); 
}
function myDecrypt($value) {
	global $config;
	return openssl_decrypt ($value, $config['crypt']['method'], $config['crypt']['key'], $config['crypt']['options'],  $config['crypt']['iv']); 
}
function portCheck($ip, $portt) {
    $fp = @fsockopen($ip, $portt, $errno, $errstr, 0.1);
    if (!$fp) {
        return false;
    } else {
        fclose($fp);
        return true;
    }
}
function mxConca($domain) {
	global $config;
	// On prépare les données
	$r = new Net_DNS2_Resolver(array('nameservers' => $config['nameservers']));
	try {
		$result = $r->query($domain, 'MX');
	} catch(Net_DNS2_Exception $e) {
		return false;
	}
	foreach($result->answer as $mxrr) {
		$mxArray[]=$mxrr->exchange;
	}
	$mxConca='';
	sort($mxArray);
	foreach($mxArray as $mx) {
		if ($mxConca!='') {
			$mxConca.=','.$mx;
		} else {
			$mxConca.=$mx;
		}
	}
	return $mxConca;
}
function imapSecure($secure) {
    $return='/notls';
    if ($secure == 1) {
	$return='/tls';
    } else if ($secure == 2) {
	$return='/ssl';
    }
    return $return;
}
function imapAuth($auth) {
    $return='';
    if ($auth == 1) {
	$return='/secure';
    } 
    return $return;
}
function imapCert($cert) {
    $return='/novalidate-cert';
    if ($cert == true || $cert == 1) {
	$return='/validate-cert';
    } 
    return $return;
}


//~ secure :
    //~ 0 = None
    //~ 1 = STARTTLS
    //~ 2 = SSLTLS
//~ auth :
    //~ 0 = Normal
    //~ 1 = Encrypt
//~ cert : 
    //~ 0 = no validate
    //~ 1 = validate

function serverImapOpenString($server, $port, $secure, $auth, $cert, $inbox = 'INBOX') {
	return '{'.$server.':'.$port.'/imap'.imapSecure($secure).imapAuth($auth).imapCert($cert).'}'.$inbox;
}

function imapCon($server, $port, $user, $password, $secure, $auth, $cert) {
    $serverImapOpenString = serverImapOpenString($server, $port, $secure, $auth, $cert);
    $mailbox = @imap_open($serverImapOpenString , $user, $password, OP_READONLY, 0);
    return $mailbox;
}

function imapTestCon($session_id, $domain, $server, $port, $user, $password, $secure, $auth, $cert) {
	global $db;
	global $config;
    $return['result'] = false;
    $mailbox = imapCon($server, $port, $user, $password, $secure, $auth, $cert);
    if (FALSE === $mailbox) {
		$return['result'] = false;
    } else {
		$return['result'] = true;
		// Paramètre
		if (preg_match('/@/', $user)) {
			$return['param']['user'] = '%e';
		} else {
			$return['param']['user'] = '%u';
		}
		$return['param']['server'] = $server;
		$return['param']['port'] = $port;
		$return['param']['secure'] = $secure;
		$return['param']['auth'] = $auth;
		$return['param']['cert'] = $cert;
		
		// == Enregistrement en BD
		
		try {
			/// D'abord on supprime si ça éxiste déjà
			$deletecmd = $db->prepare("DELETE FROM open WHERE session_id = :session_id");
			$deletecmd->bindParam('session_id', $session_id, PDO::PARAM_INT);
			$deletecmd->execute();
		} catch ( PDOException $e ) {
			toLog(1, "DELETE in open, error : ".$e->getMessage(), 0);
		}	
		
		$mxConca = mxConca($domain);
		
		if (preg_match('/@/', $user)) {
			$user='%e';
		} else {
			$user='%u';
		}
		try {
			// Enregistrer paramètre connexion en BD
			$req = $db->prepare("INSERT INTO open (session_id, domain, mx, dateCreate, imap_server, imap_port, imap_user, imap_secure, imap_auth, imap_cert) 
											VALUES (:session_id, :domain, :mx, '".time()."', :imap_server, :imap_port, :imap_user, :imap_secure, :imap_auth, :imap_cert)");
			$req->bindParam('session_id', $session_id, PDO::PARAM_INT);
			$req->bindParam('domain', $domain, PDO::PARAM_STR);		
			$req->bindParam('mx', $mxConca, PDO::PARAM_STR);
			$req->bindParam('imap_server', $server, PDO::PARAM_STR);
			$req->bindParam('imap_port', $port, PDO::PARAM_INT);
			$req->bindParam('imap_user', $user, PDO::PARAM_STR);
			$req->bindParam('imap_secure', $secure, PDO::PARAM_INT);
			$req->bindParam('imap_auth', $auth, PDO::PARAM_INT);
			$req->bindParam('imap_cert', $cert, PDO::PARAM_INT);
			$req->execute();	
		} catch ( PDOException $e ) {
			toLog(1, "INSET in open, error : ".$e->getMessage(), 0);
		}	

		// Liste dossier
		$list = imap_list($mailbox, "{".$server."}", "*");
		if (is_array($list)) {
			foreach ($list as $val) {
				$return['folder'][] = str_replace("{".$server."}", '', utf8_encode(imap_utf7_decode($val)));
			}
		}
		imap_close($mailbox);
    }
    
    return $return;
}


function imapAutoDetect($session_id, $user, $domain, $password) {
    global $config;
	$testImapServeurs = array(
		'imap.'.$domain,
		'mail.'.$domain,
		$domain,
    );
    foreach($testImapServeurs as $server) {
		// Test résolution DNS
		$resolv=true;
		$r = new Net_DNS2_Resolver(array('nameservers' => $config['nameservers']));
		try {
			$result = $r->query($domain, 'A');
		} catch(Net_DNS2_Exception $e) {
			$resolv=false;
		}
		if ($resolv) {
			if (portCheck($server, 993)) {
				// %e 
				$imapConnexion = imapTestCon($session_id, $domain, $server, 993, $user.'@'.$domain, $password, 2, 0, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %u
				$imapConnexion = imapTestCon($session_id, $domain, $server, 993, $user, $password, 2, 0, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %e  auth
				$imapConnexion = imapTestCon($session_id, $domain, $server, 993, $user.'@'.$domain, $password, 2, 1, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %u auth
				$imapConnexion = imapTestCon($session_id, $domain, $server, 993, $user, $password, 2, 1, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
			} 
			if (portCheck($server, 143)) {
				// %e 
				$imapConnexion = imapTestCon($session_id, $domain, $server, 143, $user.'@'.$domain, $password, 1, 0, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %u
				$imapConnexion = imapTestCon($session_id, $domain, $server, 143, $user, $password, 1, 0, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %e  auth
				$imapConnexion = imapTestCon($session_id, $domain, $server, 143, $user.'@'.$domain, $password, 1, 1, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %u  auth
				$imapConnexion = imapTestCon($session_id, $domain, $server, 143, $user, $password, 1, 1, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %e no tls
				$imapConnexion = imapTestCon($session_id, $domain, $server, 143, $user.'@'.$domain, $password, 0, 0, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
				// %u no tls
				$imapConnexion = imapTestCon($session_id, $domain, $server, 143, $user, $password, 0, 0, false);
				if($imapConnexion['result'] == true) {
					// Connexion trouvé !
					$imapConnexion['src'] = 'auto';
					return $imapConnexion;
					break;
				}
			}
		}
    }
    $imapConnexion['result'] = false;
    return $imapConnexion;
}

function jsonMessage($mod, $idClean, $parser, $header, $messageEML, $folder, $format) {

	// Enregistrement du json
	$arrayTmp['filename']=$idClean;
	$arrayTmp['message_id']='';
	if (isset($header->message_id)) {
		$arrayTmp['message_id']=$header->message_id;
	}
	$arrayTmp['date']='';
	if (isset($header->date)) {
		$arrayTmp['date']=$header->date;	
	}
	$arrayTmp['udate']='';
	if (isset($header->udate)) {
		$arrayTmp['udate']=$header->udate;
	}
	$arrayTmp['senderaddress']='';
	if (isset($header->senderaddress)) {
		$arrayTmp['senderaddress']=imap_utf8($header->senderaddress);
	}
	$arrayTmp['fromaddress']='';
	if (isset($header->fromaddress)) {
		$arrayTmp['fromaddress']=imap_utf8($header->fromaddress);
	}
	$arrayTmp['toaddress']='';
	if (isset($header->toaddress)) {
		$arrayTmp['toaddress']=imap_utf8($header->toaddress);
	}
	$arrayTmp['ccaddress']='';
	if (isset($header->ccaddress)) {
		$arrayTmp['ccaddress']=imap_utf8($header->ccaddress);
	}
	$arrayTmp['bccaddress']='';
	if (isset($header->bccaddress)) {
		$arrayTmp['bccaddress']=imap_utf8($header->bccaddress);
	}
	$arrayTmp['reply_toaddress']='';
	if (isset($header->reply_toaddress)) {
		$arrayTmp['reply_toaddress']=imap_utf8($header->reply_toaddress);
	}
	$arrayTmp['return_pathaddress']='';
	if (isset($header->return_pathaddress)) {
		$arrayTmp['return_pathaddress']=imap_utf8($header->return_pathaddress);
	}
	$arrayTmp['references']='';
	if (isset($header->references)) {
		$arrayTmp['references']=$header->references;
	}
	$arrayTmp['in_reply_to']='';
	if (isset($header->in_reply_to)) {
		$arrayTmp['in_reply_to']=imap_utf8($header->in_reply_to);
	}
	$arrayTmp['msgno']='';
	if (isset($header->Msgno)) {
		$arrayTmp['msgno']=$header->Msgno;
	}
	$arrayTmp['subject']='';
	if (isset($header->subject)) {
		$arrayTmp['subject']=imap_utf8($header->subject);
	}
	$arrayTmp['size']='';
	if (isset($header->Size)) {
		$arrayTmp['size']=$header->Size;
	} 
	// Suivi
	if ($header->Flagged == 'F') {
		$arrayTmp['flagged']=true;
	} else {
		$arrayTmp['flagged']=false;
	}
	// Répondu
	if ($header->Answered == 'A') {
		$arrayTmp['answered']=true;
	} else {
		$arrayTmp['answered']=false;
	}
	$arrayTmp['imap_folder'] = $folder;
	// Gestion des pièces jointes :
	$attachments = $parser->getAttachments();
	if (count($attachments) != 0) {
		$arrayTmp['attachments']=true;
		// Le format eml contient les PJ
		if ($format != 'eml') {
			foreach ($attachments as $attachment) {
				$arrayTmp['attachmentsFilename'][]=$attachment->getFilename();
			}
		}
	}else{
		$arrayTmp['attachments']=false;
		$arrayTmp['attachmentsFilename']=array();
	}
	if ($format == 'html') {
		// Recherche des formats supportés : 
		$text = $parser->getMessageBody('text');
		if ($text) {
			$arrayTmp['formatText']=true;
		}
		$html = $parser->getMessageBody('html');
		$htmlEmbedded = $parser->getMessageBody('htmlEmbedded');
		if ($htmlEmbedded) {
			$arrayTmp['formatHtml']=true;
		} else if ($html) {
			$arrayTmp['formatHtml']=true;
		}
	} else {
		$arrayTmp['formatEml']=true;
	}
	return $arrayTmp;
	unset($subject);
	unset($arrayTmp);
}

function saveMessage($session_id, $idClean, $parser, $header, $messageEML, $folder, $format, $mod) {
	global $config;
	toLog(5, "idClean : ".$idClean);
	// Gestion des pièces jointes :

	$attachments = $parser->getAttachments();
	if ($format != 'eml' && count($attachments) != 0) {
		toLog(5, "Gestion des ". count($attachments) ." pièce(s) jointe(s)");
		mkdir($config['dir'][$mod].'/'.$session_id.'/'.$folder.'/'.$idClean);
		//var_dump($attachments);
		foreach ($attachments as $attachment) {
			$attachment->save($config['dir'][$mod].'/'.$session_id.'/'.$folder.'/'.$idClean);
			$arrayTmp['attachmentsFilename'][]=$attachment->getFilename();
		}
	}
	// Export du message : 
	if ($format == 'html') {
		// toLog(5, "Export en HTML");
		// Préparation des entêtes		
		$headerTxt='';
		$headerHtml='';
		if (isset($header->date)) {
			$headerTxt.='Date : '.$header->date.'
';
			$headerHtml.='<p class="header">Date : '.$header->date.'</p>';
		}
		if (isset($header->senderaddress)) {
			$headerTxt.='Sender : '.imap_utf8($header->senderaddress).'
';
			$headerHtml.='<p class="header">Sender : '.imap_utf8($header->senderaddress).'</p>';
		}
		if (isset($header->fromaddress)) {
			$headerTxt.='From : '.imap_utf8($header->fromaddress).'
';
			$headerHtml.='<p class="header">From : '.imap_utf8($header->fromaddress).'</p>';
		}
		if (isset($header->toaddress)) {
			$headerTxt.='To : '.imap_utf8($header->toaddress).'
';
			$headerHtml.='<p class="header">To : '.imap_utf8($header->toaddress).'</p>';
		}
		if (isset($header->ccaddress)) {
			$headerTxt.='Cc : '.imap_utf8($header->ccaddress).'
';
			$headerHtml.='<p class="header">Cc : '.imap_utf8($header->ccaddress).'</p>';
		}
		if (isset($header->subject)) {
			$headerTxt.='Subject : '.imap_utf8($header->subject).'
';
			$headerHtml.='<p class="header">Subject : '.imap_utf8($header->subject).'</p>';
		}
		if (count($attachments) != 0) {
			$headerTxt.='Attachments :';
			$headerHtml.='<p class="header">Attachments :';
			foreach ($arrayTmp['attachmentsFilename'] as $attachementFilename) {
				$headerTxt.=' "'.$attachementFilename.'"';
				$headerHtml.=' "'.$attachementFilename.'"';
			}
			$headerTxt.='
';
			$headerHtml.='</p>';
		}
		$headerTxt.='----------------------
';
		$headerHtml.='<hr class="header">';
		// Recherche des formats supportés : 
		$text = $parser->getMessageBody('text');
		if ($text) {
			file_put_contents($config['dir'][$mod].'/'.$session_id.'/'.$folder.'/'.$idClean.'.txt', $headerTxt.$text);
		}
		$html = $parser->getMessageBody('html');
		$htmlEmbedded = $parser->getMessageBody('htmlEmbedded');
		if ($htmlEmbedded) {
			file_put_contents($config['dir'][$mod].'/'.$session_id.'/'.$folder.'/'.$idClean.'.html', $headerHtml.$htmlEmbedded);
		} else if ($html) {
			file_put_contents($config['dir'][$mod].'/'.$session_id.'/'.$folder.'/'.$idClean.'.html', $headerHtml.$html);
		}
	} else {
		// toLog(5, "Export en EML");
		file_put_contents($config['dir'][$mod].'/'.$session_id.'/'.$folder.'/'.$idClean.'.eml', $messageEML);
	}
	unset($arrayTmp);
}

function imapGetData($mod, $session_id, $server, $port, $user, $password, $secure, $auth, $cert, $imapfolder, $dateSince, $dateBefore, $what, $format) {
    global $config;
    $parser = new PhpMimeMailParser\Parser();
    if (is_dir($config['dir'][$mod].'/'.$session_id)) {
    	toLog(5, "Ménage du répertoire ".$config['dir'][$mod].'/'.$session_id);
	    rrmdir($config['dir'][$mod].'/'.$session_id);
    }
    $return['result'] = true;
    // Get folder information
    toLog(5, "ImapCon : $server, $port, $user, $secure, $auth, $cert");
    $mailbox = imapCon($server, $port, $user, $password, $secure, $auth, $cert) ;
    $mails = FALSE;
    if (FALSE === $mailbox) {
	    $return['result'] = false;
	    $return['resultMsg'] = 'Connexion error';
	    toLog(2, "Connexion error");
    } else {
	    if ($mod != 'preview') {
	    	toLog(5, "Création du répertoire ".$config['dir'][$mod].'/'.$session_id);
		    mkdir($config['dir'][$mod].'/'.$session_id);
	    }
	    $dataFolderJson = array();
	    $dataJson = array();
	    $totalSize=0;
	    $totalNb=0;
	    // Liste les fichier
	    foreach($imapfolder as $folder) {
		    // Connexion sur le dossier
		    toLog(5, "Connexion sur le dossier ".$folder);
		    $serverImapOpenString = serverImapOpenString($server, $port, $secure, $auth, $cert, $folder, $format);
		    imap_reopen($mailbox, $serverImapOpenString, null, 0);
		    $info = imap_check($mailbox);
		    // Vérification de l'existance du dossier
		    if (FALSE !== $info) {
		    	if ($mod != 'preview') {
			    	mkdir($config['dir'][$mod].'/'.$session_id.'/'.$folder);
			    }
			    // Avant
			    $dateBeforeForImap = date ( "d-M-Y", $dateBefore);
			    // Après
			    $dateSinceForImap = date ( "d-M-Y", $dateSince);
			    toLog(5, "IMAP SEARCH SINCE ".$dateSinceForImap." BEFORE ".$dateBeforeForImap);
			    $mails = imap_search($mailbox,'SINCE "'.$dateSinceForImap.'" BEFORE "'.$dateBeforeForImap.'"');
			    //~ $mails = imap_search($mailbox,'SINCE "03-Janv-2019" BEFORE "03-Janv-2020"');
			    $folderSize=0;
			    $nbEmail=0;
			    $nbEmailTotal=count($mails);
			    foreach($mails as $mail){
			    	//toLog(5, "Message : ".$mail."/".$nbEmailTotal);
				    // On compte les messages et leur taille
				    $header = imap_headerinfo($mailbox,$mail);
				    $folderSize=$folderSize+$header->Size;
				    $nbEmail++;
				    if ($mod != 'preview') {
						// EML format message : 
						$messageEML = imap_fetchbody($mailbox,$mail, '');
						// Parse email
						$parser->setText($messageEML); 
						// archive
						//createArchive($session_id,$mailbox,$mail);
						// export
						if ($header->Deleted != 'D') {
							//~ if ($mod != 'preview') {
								//~ array_push($dataJson, jsonMessage($mod, $parser, $header, $messageEML, $folder, $format));
							//~ } else {
							
							// Bug des messages sans id, on en génère un 
							if (empty($header->message_id)) {
								$idClean=rand(1000, mt_getrandmax()).rand(1000, mt_getrandmax()).rand(1000, mt_getrandmax());
							} else {
								$idClean=cleanTxt(substr(substr($header->message_id, 0, -1), 1));
							}
							array_push($dataJson, jsonMessage($mod, $idClean, $parser, $header, $messageEML, $folder, $format));
							saveMessage($session_id, $idClean, $parser, $header, $messageEML, $folder, $format, $mod);			
						}
				    }
			    }
			    $dataFolderJson['imap_folder'][]=$folder;
			    $return['folder'][$folder]['size']=$folderSize;
			    $totalSize = $totalSize + $folderSize;
			    $return['folder'][$folder]['nb']=$nbEmail;
			    $totalNb = $totalNb + $nbEmail;
		    } else {
		    	toLog(5, "Folder unread : ".$folder);
				$return['result'] = false;
				$return['resultMsg'] = 'Folder unread '.$folder;
		    }
		    if ($mod != 'preview') {
		    	toLog(4, "Enregistrement json/js messages");
		    	if (count($dataJson) > 0) {
					file_put_contents($config['dir'][$mod].'/'.$session_id.'/messages.json', json_encode($dataJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
					file_put_contents($config['dir'][$mod].'/'.$session_id.'/messages.js', 'var messages_json = '.json_encode($dataJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE).';');
					if (json_last_error() != 0) {
						toLog(1, "Erreur dans le json : ".json_last_error());
					}
				}
			}
	    }
	    $return['totalSize']=$totalSize;
	    $return['totalNb']=$totalNb;
		imap_close($mailbox);
    }
    return $return;
}


function imapDeleteData($session_id, $server, $port, $user, $password, $secure, $auth, $cert, $imapfolder, $dateSince, $dateBefore) {
    global $config;
    $return['result'] = true;
    // Get folder information
    toLog(5, "ImapCon : $server, $port, $user, $secure, $auth, $cert");
    $mailbox = imapCon($server, $port, $user, $password, $secure, $auth, $cert) ;
    $mails = FALSE;
    if (FALSE === $mailbox) {
	    $return['result'] = false;
	    $return['resultMsg'] = 'Connexion error';
	    toLog(2, "Connexion error");
    } else {
	    // Liste les fichier
	    foreach($imapfolder as $folder) {
		    // Connexion sur le dossier
		    toLog(5, "Connexion sur le dossier ".$folder);
		    $serverImapOpenString = serverImapOpenString($server, $port, $secure, $auth, $cert, $folder);
		    imap_reopen($mailbox, $serverImapOpenString, null, 0);
		    $info = imap_check($mailbox);
		    // Vérification de l'existance du dossier
		    if (FALSE !== $info) {
			    // Avant
			    $dateBeforeForImap = date ( "d-M-Y", $dateBefore);
			    // Après
			    $dateSinceForImap = date ( "d-M-Y", $dateSince);
			    toLog(5, "IMAP SEARCH SINCE ".$dateSinceForImap." BEFORE ".$dateBeforeForImap);
			    $mails = imap_search($mailbox,'SINCE "'.$dateSinceForImap.'" BEFORE "'.$dateBeforeForImap.'"');
			    foreach($mails as $mail){
				    imap_delete($mailbox,$mail);
			    }
		    } else {
		    	toLog(5, "Folder unread : ".$folder);
				$return['result'] = false;
				$return['resultMsg'] = 'Folder unread '.$folder;
		    }
	    }
	    imap_expunge($mailbox);
		imap_close($mailbox, CL_EXPUNGE);
    }
    return $return;
}


function rrmdir($dir) { 
	if (is_dir($dir)) { 
		$objects = scandir($dir); 
		foreach ($objects as $object) { 
			if ($object != "." && $object != "..") { 
				if (is_dir($dir."/".$object) && !is_link($dir."/".$object))
					rrmdir($dir."/".$object);
				else
					unlink($dir."/".$object); 
			} 
		}
		rmdir($dir); 
	} 
}

function convertOctect2humain($value) {
	if ($value > 1000000000) {
		$return=round($value/1024/1024/1024, 1).'Go';
	}elseif ($value > 1000000) {
		$return=round($value/1024/1024, 1).'Mo';
	}elseif ($value > 1000) {
		$return=round($value/1024, 1).'Ko';
	} else {
		$return=$value;
	}
	return $return;
}


function toLog($niveau, $msg) {
	global $config;
	if ($config['log']['level'] >= $niveau) {
		if (is_file($config['log']['path']) && filesize($config['log']['path']) > 10000000) {
			unlink($config['log']['path']);
		}
		$message = date ( "M j H:i:s" ) . " : " . trim ( $msg ) . "\n";
		file_put_contents($config['log']['path'], $message, FILE_APPEND);
	}
}


// This works in Windows.
// https://jsnelders.com/Blog/1670/php-recursively-zip-a-folder-directory-structure/
// Source and inspiration: https://gist.github.com/MarvinMenzerath/4185113/72db1670454bd707b9d761a9d5e83c54da2052ac - Marvin Menzerath. (http://menzerath.eu)
// Additional source and inspiration: https://stackoverflow.com/questions/1334613/how-to-recursively-zip-a-directory-in-php
function Zip($source, $destination) {
    if (!extension_loaded('zip'))  {
		toLog(1, "Zip extension not loaded");
        return false;
    }
	
	if (!file_exists($source))  {
		toLog(1, "Source not found:" . $source);
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE))  {
		toLog(1, "Zip not created or opened");
        return false;
    }
	
	$raw_source = $source;
    $source = str_replace('\\', '/', realpath($source));
	
	toLog(5, "Raw source: " . $raw_source);
	toLog(5, "Clean source: " . $source);
    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
		
		$sourceWithSeparator = $source . DIRECTORY_SEPARATOR;
        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            $file = realpath($file);
            if (is_dir($file) === true)             {
				$dir_path = str_replace($sourceWithSeparator, '', $file . DIRECTORY_SEPARATOR);
				toLog(5, "Directory: " . $file . " (Path: " . $dir_path . ")\n");
                //$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
				$zip->addEmptyDir($dir_path);
            } else if (is_file($file) === true)  {
				$zip_relative_path = str_replace($sourceWithSeparator, '', $file);
				
				$zip_relative_path = remove_from_start($zip_relative_path, $raw_source);
				
				toLog(5, "File: " . $file . " (Path: " . $zip_relative_path . ")");
                //$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
				//$zip->addFile($file, str_replace($source . '/', '', $file));
				$zip->addFile($file, $zip_relative_path);
            }
        }
    }  else if (is_file($source) === true)     {
        $zip->addFromString(basename($source), file_get_contents($source));
    }
    return $zip->close();
}
function remove_from_start($full_string, $prefix) {
	if (substr($full_string, 0, strlen($prefix)) == $prefix)  {
		$full_string = substr($full_string, strlen($prefix));
	} 
	return $full_string;
}
function string2url($chaine) { 
	$chaine = trim($chaine); 
	$chaine = strtr($chaine, 
	"ÀÁÂÃÄÅàáâãäåÒÓÔÕÖØòóôõöøÈÉÊËèéêëÇçÌÍÎÏìíîïÙÚÛÜùúûüÿÑñ", 
	"aaaaaaaaaaaaooooooooooooeeeeeeeecciiiiiiiiuuuuuuuuynn"); 
	$chaine = strtr($chaine,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"); 
	$chaine = preg_replace('#([^.a-z0-9]+)#i', '-', $chaine); 
	    $chaine = preg_replace('#-{2,}#','-',$chaine); 
	    $chaine = preg_replace('#-$#','',$chaine); 
	    $chaine = preg_replace('#^-#','',$chaine); 
	return $chaine; 
}

function status2humain($status) {
	switch($status) {
        case 0: 
            return _('Error');
            break;
        case 1: 
            return _('Waiting for approval');
            break;
        case 2: 
            return _('Waiting');
            break;
        case 3: 
            return _('In progress');
            break;
        case 5: 
            return _('Finished');
            break;
        default:
            return _('Unknown');
    }
}

function spoolerWait() {
	global $db;
	try {
        $spooler = $db->prepare("SELECT count(status) nb_wait 
        						FROM `spooler`
    							WHERE status = 2");
        $spooler->execute();
    } catch ( PDOException $e ) {
        toLog(1, "SELECT spoolerWait, error : ".$e->getMessage(), 0);
    }
    $spoolerFetch = $spooler->fetch();
    return $spoolerFetch['nb_wait'];
}

function spoolerWaitBefore($session_id) {
	global $db;
	try {
        $spooler = $db->prepare("SELECT count(status) nb_wait
								FROM spooler
								WHERE date < ( SELECT date FROM spooler WHERE session_id = :session_id )  
								AND status = 2 OR status = 3
								ORDER BY `spooler`.`date` ASC");
        $spooler->bindParam('session_id', $session_id, PDO::PARAM_INT);
        $spooler->execute();
    } catch ( PDOException $e ) {
        toLog(1, "SELECT spoolerWaitBefore, error : ".$e->getMessage(), 0);
    }
    $spoolerFetch = $spooler->fetch();
    return $spoolerFetch['nb_wait'];
}


function mailSend($to, $subject, $body) {
	toLog(5, 'mailSend to : '.$to);
	global $config;
	$mail = new PHPMailer(true);
	try {
		// Préparation
	    $mail->isSMTP();
	    $mail->Host       = $config['mailer']['host'];
	    $mail->SMTPAuth   = $config['mailer']['auth'];
	    $mail->Username   = $config['mailer']['username'];
	    $mail->Password   = $config['mailer']['password'];
	    if (isset($config['mailer']['secure'])) { $mail->SMTPSecure = $config['mailer']['secure']; }
	    $mail->Port       = $config['mailer']['port'];
	    if ($config['mailer']['certverify'] == false) { $mail->SMTPOptions = array(  'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false,  'allow_self_signed' => true ) ); }
	    $mail->setFrom($config['mailer']['from'], $config['mailer']['from']);
	    if (isset($config['mailer']['replyto'])) {  $mail->addReplyTo($config['mailer']['replyto']); } 
	    if (isset($config['mailer']['bcc'])) {  $mail->AddBCC($config['mailer']['bcc']); } 
	    if ($config['maintenance']['active'] == true) {
	    	$mail->addAddress($config['maintenance']['emailForTest']);
	    } else {
	    	$mail->addAddress($to);
	    }
	    // Contenu
	    $mail->CharSet = 'UTF-8';
	    $mail->isHTML(true);
	    $mail->Subject = $config['mailer']['subjectprefix'].' '.$subject;
	    $mail->Body    = $body.'<br /><br />

'.$config['mailer']['msgsignature'];
	    $mail->AltBody = strip_tags($body.'<br /><br />

'.$config['mailer']['msgsignature']);
	    $mail->send();
	    return true;
	} catch (Exception $e) {
	    return $mail->ErrorInfo;
	}
}

function username2email($username, $domain) {
	if (preg_match('/@/', myDecrypt($username))) {
		$email = myDecrypt($username);
	} else {
		$email = myDecrypt($username).'@'.$domain;
	}
	return $email;
}

// Copy dir https://stackoverflow.com/questions/2050859/copy-entire-contents-of-a-directory-to-another-using-php
function recurse_copy($src,$dst) { 
    $dir = opendir($src); 
    mkdir($dst); 
    while(false !== ( $file = readdir($dir)) ) { 
        if (( $file != '.' ) && ( $file != '..' )) { 
            if ( is_dir($src . '/' . $file) ) { 
                recurse_copy($src . '/' . $file,$dst . '/' . $file); 
            } 
            else { 
                copy($src . '/' . $file,$dst . '/' . $file); 
            } 
        } 
    } 
    closedir($dir); 
} 


function lang2locale($langue) {
	global $langueEtLocalDispo;
	if ($langueEtLocalDispo[$langue] != '') {
		return $langueEtLocalDispo[$langue];
	} else {
		// par défaut
		return 'en_US';
	}
}
function locale2lang($localeRecherche) {
	global $langueEtLocalDispo;
	foreach($langueEtLocalDispo as $code=>$locale) {
		if ($locale == $localeRecherche) {
			return $code; 
			break;
		}
	}
	// par défaut
	return 'en';
}

?>
