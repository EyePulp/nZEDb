<?php

require_once(dirname(__FILE__)."/../../../../www/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/tmux.php");
require_once(WWW_DIR."/lib/site.php");

$db = new DB();
$DIR = WWW_DIR."..";

$tmux = new Tmux;
$session = $tmux->get()->TMUX_SESSION;
$seq = $tmux->get()->SEQUENTIAL;

$site = New Sites();
$patch = $site->get()->sqlpatch;
$hashcheck = $site->get()->hashcheck;

if ( $hashcheck != '1' )
{
    echo "\033[1;33mWe have updated the way collections are created, the collection table has to be updated to use the new changes.\n";
    echo "php ${DIR}/misc/testing/DB_scripts/reset_Collections.php true\033[0m\n";
    exit(1);
}

if ( $patch < '46' )
{
	echo "\033[1;33mYour database is not up to date. Please update.\n";
	echo "php ${DIR}/misc/testing/DB_scripts/patchmysql.php\033[0m\n";
	exit(1);
}

#remove folders from tmpunrar
$tmpunrar = $site->get()->tmpunrarpath;
passthru("clear");
echo "Removing dead folders from ".$tmpunrar."\n";
exec("rm -r ".$tmpunrar."/*");

function command_exist($cmd) {
	$returnVal = shell_exec("which $cmd");
	return (empty($returnVal) ? false : true);
}

//check for apps
$apps = array("time", "tmux", "nice", "python", "tee");
foreach ($apps as &$value)
{
	if (!command_exist($value)) {
		echo "I require ".$value." but it's not installed. Aborting.\n";
		exit(1);
	}
}

shell_exec("if ! $(python -c \"import MySQLdb\" &> /dev/null); then echo \"ERROR: not installed not usable\" >&2; exit 2; fi");

//reset collections dateadded to now
print("Resetting expired collections dateadded to now. This could take a minute or two. Really.\n");
$db->query("update collections set dateadded = now() WHERE dateadded > (now() - interval 1 hour)");

function start_apps($session)
{
	$tmux = new Tmux;
	$htop = $tmux->get()->HTOP;
	$vnstat = $tmux->get()->VNSTAT;
	$vnstat_args = $tmux->get()->VNSTAT_ARGS;
	$tcptrack = $tmux->get()->TCPTRACK;
	$tcptrack_args = $tmux->get()->TCPTRACK_ARGS;
	$nmon = $tmux->get()->NMON;
	$bwmng = $tmux->get()->BWMNG;
	$mytop = $tmux->get()->MYTOP;
	$console_bash = $tmux->get()->CONSOLE;

	if (( $htop == "TRUE" ) && (command_exist("htop")))
	{
		shell_exec("tmux new-window -t$session -n htop 'printf \"\033]2;htop\033\" && htop'");
	}

	if (( $nmon == "TRUE" ) && (command_exist("nmon")))
	{
		shell_exec("tmux new-window -t$session -n nmon 'printf \"\033]2;nmon\033\" && nmon -t'");
	}

	if (( $vnstat == "TRUE" ) && (command_exist("vnstat")))
	{
		shell_exec("tmux new-window -t$session -n vnstat 'printf \"\033]2;vnstat\033\" && watch -n10 \"vnstat ${vnstat_args}\"'");
	}

	if (( $tcptrack == "TRUE" ) && (command_exist("tcptrack")))
	{
		shell_exec("tmux new-window -t$session -n tcptrack 'printf \"\033]2;tcptrack\033\" && tcptrack ${tcptrack_args}'");
	}

	if (( $bwmng == "TRUE" ) && (command_exist("bwm-ng")))
	{
		shell_exec("tmux new-window -t$session -n bwm-ng 'printf \"\033]2;bwm-ng\033\" && bwm-ng'");
	}

	if (( $mytop == "TRUE" ) && (command_exist("mytop")))
	{
		shell_exec("tmux new-window -t$session -n mytop 'printf \"\033]2;mytop\033\" && mytop -u'");
	}

	if ( $console_bash == "TRUE" )
	{
		shell_exec("tmux new-window -t$session -n bash 'printf \"\033]2;Bash\033\" && bash -i'");
	}
}

function window_utilities($session)
{
	shell_exec("tmux new-window -t$session -n utils 'printf \"\033]2;fixReleaseNames\033\"'");
	shell_exec("tmux splitw -t$session:1 -v -p 50 'printf \"\033]2;postprocessing_additional\033\"'");
	shell_exec("tmux splitw -t$session:1 -h -p 50 'printf \"\033]2;updateTVandTheaters\033\"'");
	shell_exec("tmux selectp -t 0 && tmux splitw -t$session:1 -h -p 50 'printf \"\033]2;removeCrapReleases\033\"'");
}

function window_post($session)
{
	shell_exec("tmux new-window -t$session -n post 'printf \"\033]2;postprocessing_nfos\033\"'");
	shell_exec("tmux splitw -t$session:2 -v -p 50 'printf \"\033]2;postprocessing_movies_tv\033\"'");
	shell_exec("tmux splitw -t$session:2 -h -p 50 'printf \"\033]2;postprocessing_books_games\033\"'");
	shell_exec("tmux selectp -t 0 && tmux splitw -t$session:2 -h -p 50 'printf \"\033]2;postproccessing_music_anidb\033\"'");
}


function attach($DIR, $session)
{
	if (command_exist("php5"))
		$PHP = "php5";
	else
		$PHP = "php";
	shell_exec("tmux respawnp -t $session:0.0 '$PHP $DIR/misc/update_scripts/nix_scripts/tmux/monitor.php'");
	shell_exec("tmux select-window -t$session:0 && tmux attach-session -d -t$session");
}

//create tmux
if ( $seq == "TRUE" )
{
	shell_exec("tmux -f $DIR/misc/update_scripts/nix_scripts/tmux/tmux.conf new-session -d -s $session -n Monitor 'printf \"\033]2;Monitor\033\"'");
	shell_exec("tmux selectp -t$session:0.0 && tmux splitw -t$session:0 -h -p 67 'printf \"\033]2;update_releases\033\"'");
	shell_exec("tmux selectp -t$session:0.0 && tmux splitw -t$session:0 -v -p 50 'printf \"\033]2;nzb-import-bulk\033\"'");

	window_utilities($session);
	//window_post($session);
	start_apps($session);
	attach($DIR, $session);
}
else
{
	shell_exec("tmux -f $DIR/misc/update_scripts/nix_scripts/tmux/tmux.conf new-session -d -s $session -n Monitor 'printf \"\033]2;Monitor\033\"'");
	shell_exec("tmux selectp -t$session:0.0 && tmux splitw -t$session:0 -h -p 67 'printf \"\033]2;update_binaries\033\"'");
	shell_exec("tmux selectp -t$session:0.0 && tmux splitw -t$session:0 -v -p 50 'printf \"\033]2;nzb-import-bulk\033\"'");
	shell_exec("tmux selectp -t$session:0.2 && tmux splitw -t$session:0 -v -p 67 'printf \"\033]2;backfill\033\"'");
	shell_exec("tmux splitw -t$session:0 -v -p 50 'printf \"\033]2;update_releases\033\"'");

	window_utilities($session);
	//window_post($session);
	start_apps($session);
	attach($DIR, $session);
}
?>

