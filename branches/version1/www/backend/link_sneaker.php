<?
// The source code packaged with this file is Free Software, Copyright (C) 2005 by
// Ricardo Galli <gallir at uib dot es>.
// It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
// You can get copies of the licenses here:
// 		http://www.affero.org/oagpl.html
// AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".

include('../config.php');
include(mnminclude.'link.php');


// The client requests version number
if (!empty($_GET['getv'])) {
	echo $sneak_version;
	die;
}

$link_id = intval($_GET['link']);

if (! $link_id > 0 ) {
	die;
}

$now = time();

$linkdb = $db->get_row("select link_votes, link_negatives, link_karma, link_comments, unix_timestamp(link_date) as date from links where link_id = $link_id");

if (! $linkdb || $now - $linkdb->date > $globals['time_enabled_comments']) {
	error(_('noticia antigua o no existente'));
	die;
}



if(!($time=check_integer('time')) > 0) {
	$time = 0;
	$dbtime = date("YmdHis", $time-86400);
}  else {
	$dbtime = date("YmdHis", $time);
}

$last_timestamp = $time;

if(!empty($_GET['items']) && intval($_GET['items']) > 0) {
	$max_items = intval($_GET['items']);
} else {
	$max_items = 10;
}

header('Content-Type: text/html; charset=utf-8');

$client_version = $_GET['v'];
if (empty($client_version) || ($client_version != -1 && $client_version != $sneak_version)) {
	echo "window.location.reload(true);";
	exit();
}


get_votes($dbtime, $link_id);
get_comment($dbtime, $link_id);


if($last_timestamp == 0) $last_timestamp = $now;

echo "ts=$last_timestamp;\n";

if(count($events) < 1) exit;
echo "link_votes=".$linkdb->link_votes.";link_negatives=".$linkdb->link_negatives.";link_karma=".intval($linkdb->link_karma).";\n";

krsort($events);

$counter=0;
echo "new_data = ([";
foreach ($events as $key => $val) {
	if ($counter>0) 
		echo ",";
	echo "{" . $val . "}";
	$counter++;
	if($counter>=$max_items) {
		echo "]);";
		exit();
	}
}
echo "]);";

// Check last votes
function get_votes($dbtime, $link_id) {
	global $db, $events, $last_timestamp, $max_items, $current_user;

	$res = $db->get_results("select vote_id, unix_timestamp(vote_date) as timestamp, vote_value, INET_NTOA(vote_ip_int) as vote_ip, vote_user_id, link_id, link_date, link_votes, link_status, link_comments from votes, links where vote_type='links' and vote_link_id = $link_id and vote_date > $dbtime and link_id = vote_link_id and vote_user_id != link_author order by vote_date desc limit $max_items");
	if (!$res) return;
	foreach ($res as $event) {
		$id=$event->vote_id;
		$uid = $event->vote_user_id;
		if($uid > 0) {
			$res = $db->get_row("select user_login from users where user_id = $uid");
			$user = $res->user_login;
		} else {
			$user= preg_replace('/\.[0-9]+$/', '', $event->vote_ip);
		}
		if ($event->vote_value >= 0) {
			$type = 'vote';
			$who = $user;
		} else { 
			$type = 'problem';
			$who = get_negative_vote($event->vote_value);
		}
		$status =  get_status($event->link_status);
		$key = $event->timestamp . ':votes:'.$id;
		$events[$key] = 'ts:"'.$event->timestamp.'",type:"'.$type.'",votes:"'.$event->link_votes.'", com:"'.$event->link_comments.'",who:"'.addslashes($who).'",uid:"'.$uid.'",status:"'.$status.'"';
		if($event->timestamp > $last_timestamp) $last_timestamp = $event->timestamp;
	}
}

function get_comment($dbtime, $linkid) {
	global $db, $events, $last_timestamp, $max_items;
	$res = $db->get_results("select unix_timestamp(comment_date) as timestamp, user_id, user_login, comment_user_id, comment_order, link_id, link_date, link_votes, link_status, link_comments from comments, links, users where link_id = $linkid and comment_link_id =$linkid and user_id = comment_user_id and comment_date > $dbtime order by comment_date desc limit $max_items");
	if (!$res) return;
	foreach ($res as $event) {
		$who = $event->user_login;
		$status =  get_status($event->link_status);
		$key = $event->timestamp . ':'.$type.':'.$commentid;
		$events[$key] = 'ts:"'.$event->timestamp.'",type:"comment",votes:"'.$event->link_votes.'",com:"'.$event->link_comments.'",who:"'.addslashes($who).'",uid:"'.$event->user_id.'", status:"'.$status.'"';
		if($event->timestamp > $last_timestamp) $last_timestamp = $event->timestamp;
	}
}

function error($mess) {
	header('Content-Type: text/plain; charset=UTF-8');
	echo "ERROR: $mess";
	die;
}

function get_status($status) {
	switch ($status) {
		case 'published':
			$status = _('publicada');
			break;
		case 'queued':
			$status = _('pendiente');
			break;
		case 'discard':
			$status = _('descartada');
			break;
	}
	return $status;
}
?>
