function convertOctect2humain(value) {
	if (value > 1000000000) {
		returnVal=Math.round(value/1024/1024/1024, 1)+'Go';
	}else if (value > 1000000) {
		returnVal=Math.round(value/1024/1024, 1)+'Mo';
	}else if (value > 1000) {
		returnVal=Math.round(value/1024, 1)+'Ko';
	} else {
		returnVal=value;
	}
	return returnVal;
}

function scroll_to_class(element_class, removed_height) {
	var scroll_to = $(element_class).offset().top - removed_height;
	if($(window).scrollTop() != scroll_to) {
		$('html, body').stop().animate({scrollTop: scroll_to}, 0);
	}
}

function bar_progress(progress_line_object, direction) {
	var number_of_steps = progress_line_object.data('number-of-steps');
	var now_value = progress_line_object.data('now-value');
	var new_value = 0;
	if(direction == 'right') {
		new_value = now_value + ( 100 / number_of_steps );
	}
	else if(direction == 'left') {
		new_value = now_value - ( 100 / number_of_steps );
	}
	progress_line_object.attr('style', 'width: ' + new_value + '%;').data('now-value', new_value);
}

function emailChange() {
	var email = $('#f1-email').val().split('@');
	if (email[1] == 'gmail.com') {
		$('.imapwarning.gmail').show();
	} else {
		$('.imapwarning').hide();
	}
}

function isEmail(email) {
	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
	return regex.test(email);
}

function imapDetectConfig() {
	$('.imap-form').hide();
	$('.detectAutoWait').show();
	$('.detectAuto.error').hide();
	$('#imapTestCon').val(0);
	var email = $('#f1-email').val().split('@');
	$.ajax({
		type: "POST",
		url: './form.php',
		data:  
			{ 
				imapDetectConfig: true, 
				session_id: localStorage.getItem('session_id'), 
				user: email[0],
				domain: email[1],
				password: $('#f1-password').val()
			},
		success: function( data ) {
			$('#imapfolder').empty();
			$('.imapfolder-group').show();
			$('.detectAutoWait').hide();
			if (data['result'] == false) {
				$('.detectAuto.error.ResultFalse').show();
				$('.imap-form').show();
				$('#f1-password').show();
				$('.btn-check').show();
				$('.btn-next').hide();
			} else if (data['result'] == true) {
				$('.imapTestCon.success').show();
				if ($('#f1-level').val() == 3) {
					$('.imap-form').show();
				}
				$('.btn-check').hide();
				$('.btn-next').show();
				$('#imapTestCon').val(1);
				$('.btn-next').prop("disabled",false);
				// Remplissage du formulaire : 
				$('#f1-server').val(data['param']['server']);
				$('#f1-user').val(data['param']['user']);
				$('#f1-port').val(data['param']['port']);
				$('#f1-secure').val(data['param']['secure']);
				$('#f1-auth').val(data['param']['auth']);
				if (data['param']['cert'] == true) {
					$('#f1-cert').prop('checked',true);
				} else {
					$('#f1-cert').prop('checked',false);
				}
				// Les dossiers : 
				data['folder'].forEach(function(item){
					if ($('#f1-level').val() == 1) {
						if ($('#f1-folderBeginner').val() == 1) {
							var regexSelected = /^INBOX$/;
						} else {
							if ($('#f1-server').val() == 'imap.gmail.com') {
								var regexSelected = /Tous les messages$|All/;
							} else {
								var regexSelected = /^INBOX$|Sent$|Envoyés$/;
							}
						}
					} else {
						if ($('#f1-server').val() == 'imap.gmail.com') {
							var regexSelected = /Tous les messages$|All/;
						} else {
							var regexSelected = /^INBOX$|Sent$|Envoyés$/;
						}
					}
					$('#imapfolder').append('<ul>');
					if (regexSelected.test(item)) {
						$('#imapfolder').append('<li><input type="checkbox" name="f1-imapfolder[]" class="f1-imapfolder" value="'+item+'" checked="checked"> ' + item+'</li>');
					}else{
						$('#imapfolder').append('<li><input type="checkbox" name="f1-imapfolder[]" class="f1-imapfolder" value="'+item+'"> ' + item +'</li>');
					}
					$('#imapfolder').append('</ul>');
				});
				if ($('#f1-level').val() == 1) {
					$('.imapfolder-group').hide();
				}
				$('.f1-buttons').show();
				// Expert  & Intermédiaire
				// if ($('#f1-level').val() == 3 ||$('#f1-level').val() == 2) {
				//	$('.imap-form').show();
				// Novice
				// # Avec ça, ça bug... (ça semble être le "click")
				// } else if ($('#f1-level').val() == 1) {
				// 	// Redirection prochaine étape
				// 	$('.f1-buttons.imap-form').show();
				// 	$('.imapfolder-group').hide();
					// $('.f1 .btn-next').get(0).click();
				// }
			}
		},
		error: function (xhr, status) {
			$('.detectAutoWait').hide();
			$('.detectAuto.error.ResultError').show();
			$('.detectAuto.error.ResultFalse').show();
			$('#detectAutoResultError').html(status);
			$('.imap-form').show();
			$('#f1-password').show();
			$('.btn-check').show();
			$('.btn-next').hide();
		},
		dataType: 'json'
	});
}

function levelChange() {
	// Expert
	if ($('#f1-level').val() == 3) {
		$('#f1-imapAutoDetect').prop('checked',false);
		$('.f1-format').show();
		$('.form-group.imapAutoDetect').show();
		$('#f1-folderBeginner').hide();
	// Intermédiaire
	} else if ($('#f1-level').val() == 2) {
		$('#f1-imapAutoDetect').prop('checked',true);
		$('.form-group.imapAutoDetect').show();
		$('.f1-format').show();
		$('#f1-folderBeginner').hide();
	// Novice
	} else if ($('#f1-level').val() == 1) {
		$('#f1-imapAutoDetect').prop('checked',true);
		$('.form-group.imapAutoDetect').hide();
		$('.f1-format').hide();
		$('#f1-folderBeginner').show();
	}
}

function configImapFormCheck() {
	returnVal=true;
	var server = $('#f1-server').val();
	if (server.length === 0 || server.length > 511) {
		$('#f1-server').addClass('checkbox-error');
		returnVal=false;
	}
	var regExpIp = new RegExp("^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$");
	var regResultIp = regExpIp.exec(server);
	var regExpHostname = new RegExp(/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/); // RFC 1123
	var regResultHostname = regExpHostname.exec(server);
	if (regResultIp === null && regResultHostname === null) {  
		$('#f1-server').addClass('checkbox-error');
		returnVal=false;
	}
	if ($('#f1-password').val() == '') {
		$('#f1-password').show();
		$('#f1-password').addClass('input-error');
		returnVal=false;
	}
	return returnVal;
}

jQuery(document).ready(function() {
	
    /*
        Fullscreen background
    */
    $.backstretch("assets/img/backgrounds/dvbKFz3.jpg");
    
    $('#top-navbar-1').on('shown.bs.collapse', function(){
    	$.backstretch("resize");
    });
    $('#top-navbar-1').on('hidden.bs.collapse', function(){
    	$.backstretch("resize");
    });
    
    /*
        Form
    */
    $('.f1 fieldset:first').fadeIn('slow');
    
    $('.f1 input[type="text"], .f1 input[type="date"], .f1 input[type="password"], .f1 textarea').on('focus', function() {
    	$(this).removeClass('input-error');
    });
    $('.f1 input[type="checkbox"]').on('focus', function() {
    	$(this).removeClass('checkbox-error');
    });
    
    // next step
    $('.f1 .btn-next').on('click', function() {
		var next=true;
    	var parent_fieldset = $(this).parents('fieldset');
    	// navigation steps / progress steps
    	var current_active_step = $(this).parents('.f1').find('.f1-step.active');
    	var progress_line = $(this).parents('.f1').find('.f1-progress-line');
    	
    	// Step USER
    	if (current_active_step[0]['id'] == 'stepUser') {
			// fields validation
			if (!isEmail($('#f1-email').val())) {
				$('#f1-email').addClass('input-error');
				next=false;
			}
			if ($('#f1-password').val() == '') {
				$('#f1-password-first').addClass('input-error');
				next=false;
			}
			if ($('#f1-dateStart').val() == '') {
				$('#f1-dateStart').addClass('input-error');
				next=false;
			}
			if ($('#f1-dateEnd').val() == '') {
				$('#f1-dateEnd').addClass('input-error');
				next=false;
			}
			var dateStartSplit = $('#f1-dateStart').val().split('-');
			var dateEndSplit = $('#f1-dateEnd').val().split('-');
			var dateStart = new Date(dateStartSplit[0], dateStartSplit[1], dateStartSplit[2]);
			var dateEnd = new Date(dateEndSplit[0], dateEndSplit[1], dateEndSplit[2]);
			if (dateStart > dateEnd) {
				$('#f1-dateStart').addClass('input-error');
				$('#f1-dateEnd').addClass('input-error');
				next=false;
			}
			if(!$('#f1-cgu').is(':checked') ){
				$('#f1-cgu').addClass('checkbox-error');
				next=false;
			}
			if (next == true) {
				var session_id=1000 + Math.floor(Math.random() * 2147483646)
				$.ajax({
					type: "POST",
					url: './form.php',
					data:  
						{ 
							id: session_id, 
							email: $('#f1-email').val(),
							dateStart: $('#f1-dateStart').val(),
							dateEnd: $('#f1-dateEnd').val(),
							what: $('#f1-what').val(),
							format: $('#f1-format').val()
						},
					success: function( data ) {
						// Save session id localStorage
						localStorage.setItem('session_id', session_id);
						// Next : 
						parent_fieldset.fadeOut(400, function() {
							// change icons
							current_active_step.removeClass('active').addClass('activated').next().addClass('active');
							// progress bar
							bar_progress(progress_line, 'right');
							// show next step
							$(this).next().fadeIn();
							// scroll window to beginning of the form
							scroll_to_class( $('.f1'), 20 );
							var email = $('#f1-email').val().split('@');
							$('#f1-user option:contains("usernameOnly")').text(email[0]);
							$('#f1-user option:contains("fullEmailWithDomain")').text(email[0] + '@' + email[1]);
							$('.btn-check').show();
							$('.btn-next').hide();
							// Lancement détection auto si demandé
							if( $('#f1-imapAutoDetect').is(':checked') ){
								imapDetectConfig();
							} 
						});
						$('#f1-session_id').val(session_id);
					},
					error: function (xhr, status) {
						alert('Fatal error create session : ' + status);
						$('#f1-email').addClass('input-error');
					},
					dataType: 'json'
				});
			}
		// STEP config IMAP OK, Next
		} else if (current_active_step[0]['id'] == 'stepSetting') {
			if ($("input[name='f1-imapfolder[]']:checked").length == 0) {
				$("input[name='f1-imapfolder[]']").addClass('checkbox-error');
			} else {
				$('.imap-form').hide();
				$('.imapTestCon').hide();
				$('.imapfolder-group').hide();
				$('.previewWait').show();
				var imapFolderConca = [];
				$('.f1-imapfolder:checked').each(function() {
				  imapFolderConca.push($(this).val());
				});
				// Générer json avec messages + enregistrer dossier
				$.ajax({
					type: "POST",
					url: './form.php',
					data:  
						{ 
							imapFolderValidation: true,
							session_id: localStorage.getItem('session_id'), 
							password: $('#f1-password').val(),
							imapfolder: imapFolderConca
						},
					success: function( data ) {
						$(".btn-validation").val($('#f1-what').text());
						var tableBody = $("#folderPreviewList tbody"); 
						for (let key in data['folder']){
							if(data['folder'].hasOwnProperty(key)){
								tableBody.append('<tr><td>'+key+'</td><td>'+data['folder'][key]['nb']+'</td><td>'+convertOctect2humain(data['folder'][key]['size'])+'</td></tr>'); 
							}
						}
						tableBody.append('<tr><td>Total</td><td>&nbsp;</td><td>'+convertOctect2humain(data['totalSize'])+'</td><td></td></tr>'); 
						if (data['totalSize'] > configQuotaArchive) {
							$(".overQuota").show();
							$('.btn-validation').prop("disabled",true);
						}
						// Next : 
						parent_fieldset.fadeOut(400, function() {
							// change icons
							current_active_step.removeClass('active').addClass('activated').next().addClass('active');
							// progress bar
							bar_progress(progress_line, 'right');
							// show next step
							$(this).next().fadeIn();
							// scroll window to beginning of the form
							scroll_to_class( $('.f1'), 20 );
						});
					},
					error: function (xhr, status) {
						alert('Fatal error create preview : ' + status);
						$('.imap-form').show();
						$('.previewWait').hide();
						$('.imapfolder-group').show();
					},
					dataType: 'json'
				});
			}
		} else if (current_active_step[0]['id'] == 'stepValida') {
			console.log(   	current_active_step[0]['id'] );
			$('.previewWait').show();
			$('.f1-buttons').hide();
			$('.form-group').hide();
			$.ajax({
				type: "POST",
				url: './form.php',
				data:  
					{ 
						spoolerGo: true,
						session_id: localStorage.getItem('session_id'), 
						password: $('#f1-password').val(),
					},
				success: function( data ) {
					var spoolUrl=configBaseUrl + 'spool_' + localStorage.getItem('session_id');
					$('#spoolUrl').append("<a href='"+spoolUrl+"'>"+spoolUrl+"</a>");
					$('.validation-result').show();
					var obj = 'window.location.replace("'+spoolUrl+'");';
					setTimeout(obj,4000); 
				},
				error: function (xhr, status) {
					alert('Fatal error active spooler : ' + status);
				},
				dataType: 'json'
			});
		}
    });
    
    
    $('.f1 .btn-check').on('click', function() {
		next=configImapFormCheck();
		if (next == true) {
			$('#imapfolder').empty();
			$('.imapTestCon').show();
			$('#imapTestCon').val(0);
			$('.btn-check').hide();
			$('.btn-next').hide();
			var email = $('#f1-email').val().split('@');
			if ($('#f1-user').val() == '%u') {
				var user = email[0];
			}else {
				var user = $('#f1-email').val();
			}
			if($('#f1-cert').is(':checked') ){
				var cert = 1;
			} else {
				var cert = 0;
			}
			var email = $('#f1-email').val().split('@');
			$.ajax({
				type: "POST",
				url: './form.php',
				data:  
					{ 
						imapTestCon: true, 
						session_id: localStorage.getItem('session_id'), 
						domain: email[1],
						server: $('#f1-server').val(),
						port: $('#f1-port').val(),
						user: user,
						password: $('#f1-password').val(),
						secure: $('#f1-secure').val(),
						auth: $('#f1-auth').val(),
						cert: cert
					},
				success: function( data ) {
					$('.imapTestCon').hide();
					$('#imap-folder').empty();
					if (data['result'] == false) {
						$('.imapTestCon.error.ResultFalse').show();
						$('.imap-form').show();
						$('#f1-password').show();
						$('.btn-check').show();
					} else if (data['result'] == true) {
						$('.imapTestCon.success').show();
						if ($('#f1-level').val() != 3) {
							$('.imap-form').hide();
						}
						data['folder'].forEach(function(item){
							if ($('#f1-server').val() == 'imap.gmail.com') {
								var regexSelected = /Tous les messages$|All/;
							} else {
								var regexSelected = /^INBOX$|Sent$|Envoyés$/;
							}
							if (regexSelected.test(item)) {
								$('#imapfolder').append('<p><input type="checkbox" name="f1-imapfolder[]" class="f1-imapfolder" value="'+item+'" checked="checked"> ' + item+'</p>');
							}else{
								$('#imapfolder').append('<p><input type="checkbox" name="f1-imapfolder[]" class="f1-imapfolder" value="'+item+'"> ' + item +'</p>');
							}
						});
						$('.imapfolder-group').show();
						$('.f1-buttons').show();
						$('#imapTestCon').val(1);
						$('.btn-check').hide();
						$('.btn-next').show();
					}
				},
				error: function (xhr, status) {
					$('.imapTestCon').hide();
					$('.imapTestCon.error.ResultError').show();
					$('.imapTestCon.error.ResultFalse').show();
					$('#detectAutoResultError').html(status);
					$('.imap-form').show();
					$('#f1-password').show();
					$('.btn-check').show();
				},
				dataType: 'json'
			});
		}
    });
    
    // previous step / canncel
    $('.f1 .btn-previous').on('click', function() {
		localStorage.removeItem('session_id');
    	history.go(0);
    	location.reload();
    });
    
    // Disable submit form (enter key)
    $('.f1').on('submit', function(e) {
		return false;
    });
    
    
    
});
