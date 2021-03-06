<?
include('../config.php');
header('Content-Type: text/javascript; charset=UTF-8');
header('Cache-Control: max-age=3600');
?>

var ts=<? echo (time()-86400); ?>; // just due a freaking IE cache problem
var server_name = '<? echo get_server_name(); ?>';
var base_url = '<? echo $globals['base_url']; ?>';
var sneak_base_url = 'http://'+'<? echo get_server_name().$globals['base_url'];?>'+'backend/link_sneaker.php';


var default_gravatar = 'http://'+server_name+'/img/common/no-gravatar-2-20.jpg';
var do_animation = true;
var animating = false;
var animation_colors = Array("#ffc387", "#ffc891", "#ffcd9c", "#ffd2a6", "#ffd7b0", "#ffddba", "#ffe7cf", "#ffecd9", "#fff1e3", "#fff6ed", "#fffbf7", "transparent");
var colors_max = animation_colors.length - 1;
var current_colors = Array();
var animation_timer;

var items = Array();
var new_items = 0;
var max_items = 10;
var request_timer;
var min_update = 30000;
var next_update = 7000;
var sneak_xmlhttp;
var requests = 0;
var max_requests = 500;

var play = true;


function start() {
	sneak_xmlhttp = new myXMLHttpRequest ();
	for (i=0; i<max_items; i++) {
		items[i] = document.getElementById('sneaker-'+i);
	}
	get_data();
	return false;
}


function is_busy() {
    switch (sneak_xmlhttp.readyState) {
        case 1:
        case 2:
        case 3:
            return true;
        break;
        // Case 4 and 0
        default:
            return false;
        break;
    }
}

function abort_request () {
	clearTimeout(timer);
	clearTimeout(request_timer);
	if (is_busy()) {
		sneak_xmlhttp.abort();
		// Bug in konqueror, it forces to create a new object after the abort
		sneak_xmlhttp = new myXMLHttpRequest();
		//alert("timeout");
	}
}

function handle_timeout () {
	abort_request();
	//alert("handle_timeout");
	timer = setTimeout('get_data()', next_update/2);
}

function get_data() {
	if (is_busy()) {
		handle_timeout();
		return false;
	}
	url=sneak_base_url+'?time='+ts+'&v=-1&r='+requests+'&link='+link_id;
	sneak_xmlhttp.open("GET",url,true);
	sneak_xmlhttp.onreadystatechange=received_data;
	sneak_xmlhttp.send(null);
	request_timer = setTimeout('handle_timeout()', 10000);  // wait for 10 seconds
	requests++;
	return false;
}

function received_data() {
	if (sneak_xmlhttp.readyState != 4) return;
	if (sneak_xmlhttp.status == 200 && sneak_xmlhttp.responseText.length > 10) {
		clearTimeout(request_timer);
		// We get new_data array
		var new_data = Array();
		if (sneak_xmlhttp.responseText.match(/^ERROR/)) {
			alert(sneak_xmlhttp.responseText);
			return false;
		}
		eval (sneak_xmlhttp.responseText);
		if (link_votes_0 != link_votes || link_negatives_0 != link_negatives || link_karma_0 != link_karma) {
			updateLinkValues (link_id, link_votes, link_negatives, link_karma, 0);
			link_votes_0 = link_votes;
			link_negatives_0 = link_negatives;
			link_karma_0 = link_karma;
		}
		new_items= new_data.length;
		if(new_items > 0) {
			if (do_animation) clearInterval(animation_timer);
			next_update = Math.round(0.5*next_update + 0.5*min_update/(new_items*2));
			shift_items(new_items);
			for (i=0; i<new_items && i<max_items; i++) {
				items[i].innerHTML = to_html(new_data[i]);
				if (do_animation) set_initial_color(i);
			}
			if (do_animation) {
				animation_timer = setInterval('animate_background()', 100);
				animating = true;
			}
		} else next_update = Math.round(next_update*1.25);
	}
	if (next_update < 10000) next_update = 10000;
	if (next_update > min_update) next_update = min_update;
	if (requests > max_requests) {
		if ( !confirm('<? echo _('Fisgón: ¿desea continuar conectado?');?>') ) {
			return;
		}
		requests = 0;
		next_update = 100;
	}
	timer = setTimeout('get_data()', next_update);
}

function shift_items(n) {
	//for (i=n;i<max_items;i++) {
	for (i=max_items-1;i>=n;i--) {
		items[i].innerHTML = items[i-n].innerHTML;
		//items.shift();
	}
}

function clear_items() {
	for (i=0;i<max_items;i++) {
		items[i].innerHTML = '&nbsp;';
	}
}


///////////////////////
///// HTML functions

function set_initial_color(i) {
	var j;
	if (i >= colors_max)
		j = colors_max - 1;
	else j = i;
	current_colors[i] = j;
	items[i].style.backgroundColor = animation_colors[j];
}

function animate_background() {
	if (current_colors[0] == colors_max) {
		clearInterval(animation_timer);
		animating = false;
		return;
	}
	for (i=new_items-1; i>=0; i--) {
		if (current_colors[i] < colors_max) {
			current_colors[i]++;
			items[i].style.backgroundColor = animation_colors[current_colors[i]];
		} else 
			new_items--;
	}
}


function to_html(data) {
	var tstamp=new Date(data.ts*1000);
	var timeStr;

	var hours = tstamp.getHours();
	var minutes = tstamp.getMinutes();
	var seconds = tstamp.getSeconds();

	timeStr  = ((hours < 10) ? "0" : "") + hours;
	timeStr  += ((minutes < 10) ? ":0" : ":") + minutes;
	timeStr  += ((seconds < 10) ? ":0" : ":") + seconds;

	html = '<div class="mini-sneaker-ts">'+timeStr+'<\/div>';

	if (data.type == 'vote')
		if (data.status == '<? echo _('publicada');?>')
			html += '<div class="mini-sneaker-type"><img src="'+base_url+'img/common/sneak-vote-published01.png" width="20" height="16" alt="<?echo _('voto');?>" title="<?echo _('voto');?>" /><\/div>';
		else
			html += '<div class="mini-sneaker-type"><img src="'+base_url+'img/common/sneak-vote01.png" width="20" height="16" alt="<?echo _('voto');?>" title="<?echo _('voto');?>" /><\/div>';
	else if (data.type == 'problem')
		html += '<div class="mini-sneaker-type"><img src="'+base_url+'img/common/sneak-problem01.png" width="20" height="16" alt="<?echo _('problema');?>" title="<?echo _('problema');?>" /><\/div>';
	else if (data.type == 'comment')
		html += '<div class="mini-sneaker-type"><img src="'+base_url+'img/common/sneak-comment01.png" width="20" height="16" alt="<?echo _('comentario');?>" title="<?echo _('comentario');?>" /><\/div>';
	else
		html += '<div class="mini-sneaker-type">'+data.type+'<\/div>';

	html += '<div class="mini-sneaker-votes">'+data.votes+'/'+data.com+'<\/div>';
	if (data.type == 'problem')
		html += '<div class="mini-sneaker-who"><span class="sneaker-problem">&nbsp;'+data.who+'<\/span><\/div>';
	else if (data.uid > 0)  {
		html += '<div class="mini-sneaker-who">';
		html += '<a href="'+base_url+'user.php?login='+data.who+'"><img src="'+base_url+'backend/get_avatar_url.php?id='+data.uid+'&amp;size=20" width=20 height=20 /><\/a>';
		html += '&nbsp;<a href="'+base_url+'user.php?login='+data.who+'">'+data.who.substring(0,15)+'<\/a><\/div>';
	} else 
		html += '<div class="mini-sneaker-who">&nbsp;'+data.who.substring(0,15)+'<\/div>';


	if (data.status == '<? echo _('publicada');?>')
		html += '<div class="mini-sneaker-status"><a href="'+base_url+'"><span class="sneaker-published">'+data.status+'<\/span><\/a><\/div>';
	else if (data.status == '<? echo _('descartada');?>')
		html += '<div class="mini-sneaker-status"><a href="'+base_url+'shakeit.php?view=discarded"><span class="sneaker-discarded">'+data.status+'<\/span><\/a><\/div>';
	else 
		html += '<div class="mini-sneaker-status"><a href="'+base_url+'shakeit.php">'+data.status+'<\/a><\/div>';

	return html;
}

