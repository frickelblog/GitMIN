<?php
/*
 ==================================================================================================================== 
							Einstellungen - bitte anpassen!
 ==================================================================================================================== */
// Einstellungen für Benutzer und Passwort		
define("GITMIN_USER", "admin");
define("GITMIN_PASS", "pass");
// Pfade zu git und git-http-backend			
define("GITBIN", "/usr/bin/git");
define("GITHTTPBACKEND", "/usr/lib/git-core/git-http-backend");

/*
 ==================================================================================================================== 
							!!! Ab hier nichts ändern !!! 
 ==================================================================================================================== */
define("REPOROOT", str_replace("/gitmin.php","",$_SERVER["SCRIPT_FILENAME"]));
define("PASSWDFILE", REPOROOT."/.htpasswd");
define("GITHTTPBACKENDFILE", REPOROOT."/git-http-backend.cgi");
define("GITMIN_VERSION", "0.9");

session_start();

#region selbstdefinierte Funktionen	
function GetDirectorySize($path)
{
    $bytestotal = 0;
    $path = realpath($path);
    if($path!==false)
	{
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object)
		{
            $bytestotal += $object->getSize();
        }
    }
    return $bytestotal;
}

function array_orderby()
{
    $args = func_get_args();
    $data = array_shift($args);
    foreach ($args as $n => $field) {
        if (is_string($field)) {
            $tmp = array();
            foreach ($data as $key => $row)
                $tmp[$key] = $row[$field];
            $args[$n] = $tmp;
            }
    }
    $args[] = &$data;
    call_user_func_array('array_multisort', $args);
    return array_pop($args);
}

function User()
{
	return file(".htpasswd");
}

function repoArray()
{
	$dh  = opendir(".");
	while (false !== ($repodir = readdir($dh))) 
	{
		if(is_dir($repodir) && $repodir!="." && $repodir!=".." && substr($repodir,-4)==".git")
		{
			//Name		
			$repos[$repodir]['name'] = $repodir;
			// Erstelldat	
			$repos[$repodir]['date'] = filemtime($repodir."/."); //date("d.m.Y H:i:s",filemtime($repodir."/."));
			// Größe		
			$repos[$repodir]['size'] = GetDirectorySize($repodir);
		}
	}

	if(!empty($repos)) 
	{
		sort($repos);
	}
	
	return $repos;
}

function updateHTAccessUserPassword($file, $user, $newpass) 
{
	$newFile = "";
	$UserArray = file($file);
	
	for($i=0;$i<sizeof($UserArray);$i++)
	{	
		$s = explode(":",$UserArray[$i]);
		
		if($s[0]==$user)
		{
			$newFile.= $s[0].":".crypt($newpass)."\n";
		}
		else
		{
			$newFile.= $s[0].":".$s[1];
		}
	}
	file_put_contents($file,$newFile);
}

function updateHTAccessUserdel($file, $user)
{
	$newFile = "";
	$UserArray = file($file);
	
	for($i=0;$i<sizeof($UserArray);$i++)
	{	
		$s = explode(":",$UserArray[$i]);
		
		// nur alle User in die neue Datei aufnehmen, die nicht dem übergebenen user entsprechen
		if($s[0]!=$user)
		{
			$newFile.= $s[0].":".$s[1];
		}
	}
	file_put_contents($file,$newFile);
}

function jsDOMfunction($js)
{
	return "<script>
				$(document).ready(function() 
				{
					".$js."
				});
			</script>";
}
#endregion

$action  = (!empty($_GET['action'])  ? $_GET['action']  : $_POST['action']);
$action2 = (!empty($_GET['action2']) ? $_GET['action2'] : $_POST['action2']);

#region Vorbereiten der .htaccess und CGI Dateien	
if(file_exists(GITBIN) && file_exists(GITHTTPBACKEND))
{
	// .htpasswd erstellen,falls nicht vorhanden
	if(!file_exists(PASSWDFILE))
	{
		file_put_contents(PASSWDFILE,"");
	}

	//.htaccess für mod_rewrite und CGI erstellen, falls noch nciht vorhanden
	if(!file_exists(REPOROOT."/.htaccess"))
	{
		$URLPfad = str_replace("/gitmin.php","",$_SERVER["SCRIPT_NAME"]);
		$htaccess_inhalt = '<Files ~ (\.cgi$)>'."\n";
		$htaccess_inhalt.= 'SetHandler cgi-script'."\n";
		$htaccess_inhalt.= 'Options +ExecCGI'."\n";
		$htaccess_inhalt.= 'allow from all'."\n";
		$htaccess_inhalt.= '</Files>'."\n";
		$htaccess_inhalt.= "\n";
		$htaccess_inhalt.= '#This is the rewrite algorithm1'."\n";
		$htaccess_inhalt.= 'RewriteEngine on'."\n";
		$htaccess_inhalt.= 'RewriteBase '.$URLPfad.'/'."\n";
		$htaccess_inhalt.= 'RewriteRule ^([a-zA-Z0-9._]*\.git/(HEAD|info/refs|objects/(info/[^/]+|[0-9a-f]{2}/[0-9a-f]{38}|pack/pack-[0-9a-f]{40}\.(pack|idx))|git-(upload|receive)-pack))$ '.$URLPfad.'/git-http-backend.cgi/$1'."\n";

		file_put_contents(REPOROOT."/.htaccess",$htaccess_inhalt);
	}


	// git-http-backend.cgi erstellen, falls nicht vorhanden
	if(!file_exists(GITHTTPBACKENDFILE))
	{
		$githttpbackend_inhalt = '#!/bin/sh'."\n";
		$githttpbackend_inhalt.= '#first we export the GIT_PROJECT_ROOT'."\n";
		$githttpbackend_inhalt.= 'export GIT_PROJECT_ROOT='.REPOROOT.''."\n";
		$githttpbackend_inhalt.= ''."\n";
		$githttpbackend_inhalt.= 'if [ -z "$REMOTE_USER" ]'."\n";
		$githttpbackend_inhalt.= 'then'."\n";
		$githttpbackend_inhalt.= '    export REMOTE_USER=$REDIRECT_REMOTE_USER'."\n";
		$githttpbackend_inhalt.= 'fi'."\n";
		$githttpbackend_inhalt.= ''."\n";;
		$githttpbackend_inhalt.= '#and run your git-http-backend'."\n";
		$githttpbackend_inhalt.= ''.GITHTTPBACKEND.''."\n";
		
		file_put_contents(GITHTTPBACKENDFILE,$githttpbackend_inhalt);
		exec("chmod 755 ".GITHTTPBACKENDFILE."");
	}
	
}
#endregion 

if($action=="login")
{
	if($_POST['user_login'] == GITMIN_USER && $_POST['user_password'] == GITMIN_PASS)
	{
		$_SESSION['username'] = GITMIN_USER;
		$action="dashboard";
	}
}
if($action=="logout")
{
	$_SESSION['username'] = "";
	$action = "login";
	session_destroy(); 
}

?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Metro, a sleek, intuitive, and powerful framework for faster and easier web development for Windows Metro Style.">
    <meta name="keywords" content="HTML, CSS, JS, JavaScript, framework, metro, front-end, frontend, web development">
    <meta name="author" content="Sergey Pimenov and Metro UI CSS contributors">
    <title>GitMin</title>
	<link href="https://cdn.rawgit.com/olton/Metro-UI-CSS/master/build/css/metro.min.css" rel="stylesheet">
	<link href="https://cdn.rawgit.com/olton/Metro-UI-CSS/master/build/css/metro-responsive.min.css" rel="stylesheet">
	<link href="https://cdn.rawgit.com/olton/Metro-UI-CSS/master/build/css/metro-schemes.min.css" rel="stylesheet">
	<link href="https://cdn.rawgit.com/olton/Metro-UI-CSS/master/build/css/metro-rtl.min.css" rel="stylesheet">
	<link href="https://cdn.rawgit.com/olton/Metro-UI-CSS/master/build/css/metro-icons.min.css" rel="stylesheet">
	<script src="https://code.jquery.com/jquery-2.1.3.min.js"></script>
	<script src="https://cdn.datatables.net/1.10.9/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.rawgit.com/olton/Metro-UI-CSS/master/build/js/metro.min.js"></script>

    <style>
		.login-form {
            width: 25rem;
            height: 18.75rem;
            position: fixed;
            top: 50%;
            margin-top: -9.375rem;
            left: 50%;
            margin-left: -12.5rem;
            background-color: #ffffff;
            opacity: 1;
        }

		html, body {
            height: 100%;
        }
        body {
        }
        .page-content {
            padding-top: 3.125rem;
            min-height: 100%;
            height: 100%;
        }
        .table .input-control.checkbox {
            line-height: 1;
            min-height: 0;
            height: auto;
        }

        @media screen and (max-width: 800px){
            #cell-sidebar {
                flex-basis: 52px;
            }
            #cell-content {
                flex-basis: calc(100% - 52px);
            }
        }
    </style>

    <script>
        function pushMessage(t,msg){
            $.Notify({
                caption: msg.split("|")[0],
                content: msg.split("|")[1],
                type: t,
				keepOpen: true,
				icon: "<span class='mif-info'></span>"
            });
        }
		
		function showDialog(id,reponame){
			
			var dialog =  $(id).data('dialog');
			dialog.element.context.innerHTML = dialog.element.context.innerHTML.replace(/(\[\[1\]\])/gm, reponame);
			if (!dialog.element.data('opened')) {
				dialog.open();
			} else {
				dialog.close();
			}
		}
        
		$(function(){
            $('.sidebar').on('click', 'li', function(){
                if (!$(this).hasClass('active')) {
                    $('.sidebar li').removeClass('active');
                    $(this).addClass('active');
                }
            })
        });
    </script>
</head>

<?php
if(empty($_SESSION['username']) || $action=="login")
{
	?>
	
	<body class="bg-darkTeal">
    <div class="login-form align-center padding10 block-shadow">
		<div class="align-left">
		<?php
			if(!file_exists(GITBIN))
			{
				echo "<h6><span class=\"tag alert\">Fehler</span> ".GITBIN." wurde nicht gefunden</h6>";
			}
			if(!file_exists(GITHTTPBACKEND))
			{
				echo "<h6><span class=\"tag alert\">Fehler</span> ".GITHTTPBACKEND." wurde nicht gefunden</h6>";
			}
		?>
		</div>
		
        <form action="gitmin.php" method="post">
            <h1 class="text-light">gitMIN Login</h1>
            <hr class="thin"/>
            <br />
            <div class="input-control text full-size" data-role="input">
                <label for="user_login">Benutzer:</label>
                <span class="mif-user prepend-icon"></span>
				<input type="text" name="user_login" id="user_login">
                <button class="button helper-button clear"><span class="mif-cross"></span></button>
            </div>
            <br />
            <br />
            <div class="input-control password full-size" data-role="input">
                <label for="user_password">Passwort:</label>
                <span class="mif-key prepend-icon"></span>
				<input type="password" name="user_password" id="user_password">
                <button class="button helper-button reveal"><span class="mif-looks"></span></button>
            </div>
            <br />
            <br />
            <div class="form-actions">
				<input type="hidden" name="action" value="login">
                <button type="submit" class="button primary">Login</button>
            </div>
        </form>
    </div>
	</body>
	</html>
	<?php
	die();
}

?>


<body class="bg-steel">
	<?php
	if($action2=="action2_newrepo")
	{
		$newrepo_reponame = $_POST['newrepo_reponame'];
		$newrepo_username = $_POST['newrepo_username'];
		$newrepo_password = $_POST['newrepo_password'];
		$repoPfad = REPOROOT."/".$newrepo_reponame.".git";
		
		if(file_exists($repoPfad))
		{
			echo jsDOMfunction("showDialog('#RepoAddErrorDialog','".$newrepo_reponame."')");
		}
		else
		{
			// Repo anlegen...
			exec("mkdir $repoPfad");
			exec("".GITBIN." init --bare $repoPfad");
			exec("cd $repoPfad && ".GITBIN." --bare update-server-info");
			exec("cp $repoPfad/hooks/post-update.sample $repoPfad/hooks/post-update");
			exec("chmod a+x $repoPfad/hooks/post-update");
			exec("touch $repoPfad/git-daemon-export-ok");
			
			// .htaccess Datei schreiben...
			$htaccess_inhalt = "#This is used for group/user access control\n";
			$htaccess_inhalt.= "AuthName \"Private Git Access ($newrepo_reponame)\"\n";
			$htaccess_inhalt.= "AuthType Basic\n";
			$htaccess_inhalt.= "AuthUserFile ".PASSWDFILE."\n";
			$htaccess_inhalt.= "Require user $newrepo_username\n";
			$htaccess_inhalt.= "\n";			
			file_put_contents($repoPfad."/.htaccess",$htaccess_inhalt);
			
			// .htpasswd Datei schreiben...
			$htpasswd_inhalt = file_get_contents(PASSWDFILE);
			$htpasswd_inhalt.= $newrepo_username.":".crypt($newrepo_password)."\n";
			file_put_contents(PASSWDFILE,$htpasswd_inhalt);
			
			// ...fertig!	
			echo jsDOMfunction("pushMessage('success','Repo-Verwaltung|Git-Repo <b>$newrepo_reponame</b> wurde erstellt')");
		}
	}

	if($action2=="action2_delrepo")
	{
			$del_reponame = $_POST['del_reponame'];
			$repoPfad = REPOROOT."/".$del_reponame;
			exec("rm -rf $repoPfad");
			echo jsDOMfunction("pushMessage('success','Repo-Verwaltung|Git-Repo <b>$del_reponame</b> gelöscht')");
	}
	
	if($action2=="action2_htaccessPassChange")
	{
		$userpwd_username = $_POST['userpwd_username'];
		$userpwd_password = $_POST['userpwd_password'];
		
		updateHTAccessUserPassword(PASSWDFILE, $userpwd_username, $userpwd_password);
		echo jsDOMfunction("pushMessage('success','User-Verwaltung|Passwort für Benutzer <b>$userpwd_username</b> erfolgreich in \"$userpwd_password\" geändert')");
	}
	
	if($action2=="action2_htaccessUserdel")
	{
		$del_username = $_POST['del_username'];
		updateHTAccessUserdel(PASSWDFILE, $del_username);
		echo jsDOMfunction("pushMessage('success','User-Verwaltung|Benutzer <b>$del_username</b> wurde erfolgreich gelöscht')");
	}
	
	?>
    <div class="app-bar fixed-top" data-role="appbar">
        <a class="app-bar-element branding">GitMIN</a>
        <span class="app-bar-divider"></span>
        <ul class="app-bar-menu">
            <li><a href="gitmin.php?action=dashboard">Dashboard</a></li>
			<li><a href="gitmin.php?action=user">Benutzer</a></li>
			<li><a href="gitmin.php?action=repos">Git Repos</a></li>
			
        </ul>
        <div class="app-bar-element place-right">	
            <span class="dropdown-toggle"><span class="mif-cog"> </span> <?php echo $_SESSION['username']; ?></span>
            <div class="app-bar-drop-container padding10 place-right no-margin-top block-shadow fg-dark" data-role="dropdown" data-no-close="true" style="width: 220px">
                <ul class="unstyled-list fg-dark">
                    <li><a href="gitmin.php?action=logout" class="fg-white3">Abmelden</a></li>
                </ul>
            </div>
        </div>
		<a href="gitmin.php?action=info" class="app-bar-element place-right"><span class="mif-info"> </span> Info</a>
    </div>

    <div class="page-content">
        <div class="flex-grid no-responsive-future" style="height: 100%;">
            <div class="row" style="height: 100%">
                <div class="cell size-x200" id="cell-sidebar" style="background-color: #71b1d1; height: 100%">
                    <ul class="sidebar">
                        <li <?php if($action=="dashboard") echo 'class="active"'; ?>><a href="gitmin.php?action=dashboard">
                            <span class="mif-apps icon"></span>
                            <span class="title">Dashboard</span>
                            <span class="counter"></span>
                        </a></li>
                        <li <?php if($action=="user") echo 'class="active"'; ?>><a href="gitmin.php?action=user">
                            <span class="mif-users icon"></span>
                            <span class="title">Benutzer</span>
                            <span class="counter"><?php echo sizeof(User()); ?></span>
                        </a></li>
                        <li <?php if($action=="repos") echo 'class="active"'; ?>><a href="gitmin.php?action=repos">
                            <span class="mif-database icon"></span>
                            <span class="title">Git Repos</span>
                            <span class="counter"><?php echo sizeof(repoArray()); ?></span>
                        </a></li>
                    </ul>
                </div>
				
				<?php 
				// Action leer = Dashbaord	
				if($action=="dashboard") { 
				?>
                <div class="cell auto-size padding20 bg-white" id="cell-content">
                    <h1 class="text-light">Dashboard <span class="mif-apps place-right"></span></h1>
                    <hr class="thin bg-grayLighter">
                    <button class="button primary" onclick="showDialog('#RepoAddDialog','')"><span class="mif-plus"></span> Neues Git Repo</button>
                    <hr class="thin bg-grayLighter">
                    
					<div class="grid">
						<div class="row cells3">
							<div class="cell">
								<div class="panel" style="width: 300px">
									<div class="heading">
										<span class="icon mif-users"></span>
										<span class="title"> Letzte Benutzer</span>
									</div>
									<div class="content">
										<?php 
											$userArray = User();
											for($i=sizeof($userArray);$i>=0;$i--)
											{
												$user_split = explode(":",$userArray[$i]);
												if(!empty($user_split[0])) echo $user_split[0]."</br>";
											}
										?>
									</div>
								</div>
							</div>
						</div>
						<div class="row cells3">
							<div class="cell">
								<div class="panel" style="width: 300px">
									<div class="heading">
										<span class="icon mif-database"></span>
										<span class="title"> Letzten Git Repos:</span>
									</div>
									<div class="content">
										<?php 
											
											$repoArray = repoArray();
											if(!empty($repoArray))
											{
												$repoArray = array_orderby($repoArray, 'date', SORT_DESC, 'name', SORT_ASC);
												
												for($i=0;$i<sizeof($repoArray);$i++)
												{
													echo date("d.m.Y H:i:s",$repoArray[$i]['date'])." - ".$repoArray[$i]['name']."</br>";
												}
											}
										?>
									</div>
								</div>
							</div>
							<div class="cell">	
								<div class="panel" style="width: 300px">
									<div class="heading">
										<span class="icon mif-database"></span>
										<span class="title"> Gr&ouml;&szlig;ten Git Repos:</span>
									</div>
									<div class="content">
										<?php 
											$repoArray = repoArray();
											if(!empty($repoArray))
											{
												$repoArray = array_orderby($repoArray, 'size', SORT_DESC, 'name', SORT_ASC);
												
												for($i=0;$i<sizeof($repoArray);$i++)
												{
													echo round($repoArray[$i]['size']/1024/1024,3)." MB - ".$repoArray[$i]['name']."</br>";
												}
											}
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
                </div>
				<?php 
				}
				else if($action=="user") 
				{
				// action user = Benutzer auflistung
				?>
				<div class="cell auto-size padding20 bg-white" id="cell-content">
                    <h1 class="text-light">Benutzer <span class="mif-users place-right"></span></h1>
                    <hr class="thin bg-grayLighter">
                    <button class="button primary" onclick="showDialog('#RepoAddDialog','')"><span class="mif-plus"></span> Neues Git Repo</button>
                    <hr class="thin bg-grayLighter">
                    
					<div style="width: 600px">
					<table class="dataTable border bordered"  data-role="datatable" data-auto-width="true">
                        <thead>
                        <tr>
                            <td style="width: 20px">
                            </td>
                            <td class="sortable-column" style="width: 400px">Name</td>
                            <td class="sortable-column" style="width: 100px">Passwort &auml;ndern</td>
                            <td class="sortable-column" style="width: 100px">L&ouml;schen</td>
                        </tr>
                        </thead>
                        <tbody>
                        
						<?php 
							$UserArray = User();
							for($i=0;$i<sizeof($UserArray);$i++)
							{
								$user_split = explode(":",$UserArray[$i]);
								?>
								<tr>
									<td>
										<label class="input-control checkbox small-check no-margin">
											<input type="checkbox"><span class="check"></span>
										</label>
									</td>
									<td><?php echo $user_split[0];?></td>
									<td><button class="button" onclick="showDialog('#HTAccessUserPasswordDialog','<?php echo $user_split[0];?>')"><span class="mif-key fg-cobalt mif-lg"></span></button></td>
									<!-- <td><button class="button" onclick="pushMessage('success','User-Verwaltung|Benutzer wurde erfolgreich hinzugefügt')"><span class="mif-minus fg-red mif-lg"></span></button></td> -->
									<td><button class="button" onclick="showDialog('#HTAccessUserDeleteDialog','<?php echo $user_split[0];?>')"><span class="mif-minus fg-red mif-lg"></span></button></td>
								</tr>
								<?php
							}
						?>
						
                        </tbody>
                    </table>
					</div>
					
                </div>
				<?php 
				}
				else if($action=="repos") 
				{
				// action repos = Repo auflistung
				?>
				<div class="cell auto-size padding20 bg-white" id="cell-content">
                    <h1 class="text-light">Git Repos <span class="mif-database place-right"></span></h1>
                    <hr class="thin bg-grayLighter">
                    <button class="button primary" onclick="showDialog('#RepoAddDialog','')"><span class="mif-plus"></span> Neues Git Repo</button>
                    <hr class="thin bg-grayLighter">
					
					<table class="dataTable border bordered" data-role="datatable" data-auto-width="false">
                        <thead>
                        <tr>
                            <td style="width: 20px">
                            </td>
                            <td class="sortable-column sort-asc" style="width: 200px">Name</td>
                            <td class="sortable-column" style="width: 200px">Datum</td>
                            <td class="sortable-column" style="width: 200px">Größe</td>
                            <td class="sortable-column">Adresse</td>
							<td style="width: 80px">Löschen</td>
                        </tr>
                        </thead>
                        <tbody>
						
						<?php 
							$repoArray = repoArray();
							for($i=0;$i<sizeof($repoArray);$i++)
							{
								$repouser = str_replace(".git","",$repoArray[$i]['name']);
								$URLPfad = str_replace("/gitmin.php","",$_SERVER["SCRIPT_NAME"]);
								?>
								<tr>
									<td>
										<label class="input-control checkbox small-check no-margin">
											<input type="checkbox"><span class="check"></span>
										</label>
									</td>
									<td><?php echo $repoArray[$i]['name'];?></td>
									<td><?php echo date("d.m.Y H:i:s",$repoArray[$i]['date']); ?></td>
									<td><?php echo round($repoArray[$i]['size']/1024/1024,3)." MB"; ?></td>
									<td><?php echo "http://".$repouser."@".$_SERVER["SERVER_NAME"].$URLPfad."/".$repoArray[$i]['name']; ?></td>
									<td><button class="button" onclick="showDialog('#RepoDeleteDialog','<?php echo $repoArray[$i]['name'];?>')"><span class="mif-minus fg-red mif-lg"></span></button></td>
								</tr>
								<?php
							}
						?>
                        </tbody>
                    </table>
					
					
                </div>
				<?php 
				}
				else if($action=="info") 
				{
				// action info = Allgemeine Informationen
				?>
				<div class="cell auto-size padding20 bg-white" id="cell-content">
                    <h1 class="text-light">GitMIN Info <span class="mif-info place-right"></span></h1>
                    <hr class="thin bg-grayLighter">
                    
					<h4 class="no-margin-top">Über GitMIN</h4>
					<hr class="bg-green">
					<div>
						GitMin - Ein PHP Script um private und öffentliche git Repositorys auf Shared Webspace zur Verfügung zu stellen.<br/>
						Entwickelt von <a href="http://www.frickelblog.de">frickelblog.de</a> mit Hilfe des <a href="http://metroui.org.ua">Metro UI CSS 3</a><br/>
						GitMIN Version: <?php echo GITMIN_VERSION;?><br/>
						<br/>
						<a class="button success" href="http://www.frickelblog.de">frickelblog.de</a> 
						<a class="button info" href="https://github.com/frickelblog/GitMIN">Github</a>
						<br/><br/>
					</div>
					
					<h4 class="no-margin-top">Metro UI CSS</h4>
					<hr class="bg-blue">
					<div>
						The front-end framework for developing projects on the web in Windows Metro Style.
						<br>
						<br>
						<a class="button primary" href="http://metroui.org.ua" >Metro UI CSS</a> 
						<a class="button info" href="https://github.com/olton/Metro-UI-CSS">Github</a>
					</div>
                </div>
				<?php 
				}
				?>
            </div>
        </div>
    </div>
	
	
	<!-- ===== Dialog Benutzer-Passwort ändern ===== -->
	<div data-role="dialog" id="HTAccessUserPasswordDialog" class="padding10" data-close-button="false">
    <h1 class="text-light">Passwort ändern &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="mif-key fg-grayLight place-right"></span></h1>
    <hr class="thin bg-grayLighter">
    <p>
		<div class="align-center">
			<form action="gitmin.php" method="post">
				<h6>Neues Passwort für Benutzer <b>[[1]]</b>:</h6>
				<div class="input-control text">
					<span class="mif-key prepend-icon"></span>
					<input type="text" name="userpwd_password">
				</div>
				<br/>
				<input type="hidden" name="userpwd_username" value="[[1]]">
				<input type="hidden" name="action" value="user">
				<input type="hidden" name="action2" value="action2_htaccessPassChange">
				<button class="button primary" type="submit">OK</button>	
				<a href="gitmin.php?action=user">Abbrechen</a>				
			</form>
			
		</div>
    </p>
	</div>

	<!-- ===== Dialog Benutzer löschen ===== -->
	<div data-role="dialog" id="HTAccessUserDeleteDialog" class="padding10" data-close-button="false" data-type="alert">
    <h1 class="text-light">Benutzer löschen &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="mif-key fg-white place-right"></span></h1>
    <p>     
		<div class="auto-size align-center">
			Soll Der benutzer [[1]] wirklich gelöscht werden?</br></br>
			<form action="gitmin.php" method="post">
				<input type="hidden" name="del_username" value="[[1]]">
				<input type="hidden" name="action" value="user">
				<input type="hidden" name="action2" value="action2_htaccessUserdel">
				<button class="button" type="submit">Ja</button>  
				<!-- <button class="button" onclick="showDialog('#RepoDeleteDialog','[[1]]')">Nein</button> -->
				<a class="fg-white" href="gitmin.php?action=user"> Nein</a>
			</form>
		</div>
    </p>
	</div>	
	
	<!-- ===== Allgemeiner Success-Dialog ===== -->
	<div data-role="dialog" id="SuccessDialog" class="padding10 align-center" data-close-button="false" data-type="success">
    <p>
		<div class="align-center">
        [[1]]
		<br/><br/>
		<button class="button" onclick="showDialog('#SuccessDialog','')">OK</button>
		</div>
    </p>
	</div>
	
	<!-- ===== Dialog Repos löschen ===== -->
	<div data-role="dialog" id="RepoDeleteDialog" class="padding10" data-close-button="false" data-type="alert">
    <h1 class="text-light">Repository löschen &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="mif-database fg-white place-right"></span></h1>
    <p>
		<div class="auto-size align-center">
			Soll das Repository [[1]] wirklich gelöscht werden?</br></br>
			<form action="gitmin.php" method="post">
				<input type="hidden" name="del_reponame" value="[[1]]">
				<input type="hidden" name="action" value="repos">
				<input type="hidden" name="action2" value="action2_delrepo">
				<button class="button" type="submit">Ja</button>
				<a class="fg-white" href="gitmin.php?action=repos"> Nein</a>
			</form>
		</div>
    </p>
	</div>
	
	<!-- ===== Dialog Neues Repo erstellen ===== -->
	<div data-role="dialog" id="RepoAddDialog" class="padding20" data-close-button="false">
	<h1 class="text-light">Neues Git Repo &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="mif-database fg-grayLight place-right"></span></h1>
    <hr class="thin bg-grayLighter">
    <p>
		<div class="auto-size align-center padding20">
			<form action="gitmin.php" method="post">
			<div class="input-control text">
				<label>Repo Name</label>
				<span class="mif-database prepend-icon"></span>
				<input type="text" name="newrepo_reponame">
			</div>
			<br/><br/>
			<div class="input-control text">
				<label>Benutzername</label>
				<span class="mif-user prepend-icon"></span>
				<input type="text" name="newrepo_username">
			</div>
			<br/><br/>
			<div class="input-control text">
				<label>Passwort</label>
				<span class="mif-key prepend-icon"></span>
				<input type="text" name="newrepo_password">
			</div>
			<br/><br/>
			
				<input type="hidden" name="action" value="repos">
				<input type="hidden" name="action2" value="action2_newrepo">
				<button class="button primary" type="submit">Erstellen</button>
				<button class="button" onclick="showDialog('#RepoAddDialog','[[1]]')">Abbrechen</button>
			</form>
		</div>
    </p>
	</div>
	
	<!-- ===== Dialog Repos erstellen Fehler ===== -->
	<div data-role="dialog" id="RepoAddErrorDialog" class="padding10" data-close-button="false" data-type="alert">
    <h1>Fehler</h1>
    <p>
		<div class="align-center">
		Das Repository [[1]] existiert bereits!<br/><br/>
		<button class="button" onclick="showDialog('#RepoAddErrorDialog','[[1]]')">Abbrechen</button>
		</div>
	</p>
		
    
	</div>

</body>
</html>