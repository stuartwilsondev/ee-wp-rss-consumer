<?php
/**
 * Plugin Name: EE RSS Consumer
 * Plugin URI: http://editoreye.com/
 * Description: RSS consumer
 * Version: 1.0
 * Author: Stuart Wilson
 * Author URI: www.stuartwilsondev.com
 **/
if(!function_exists('wp_get_current_user')) {
    include(ABSPATH . "wp-includes/pluggable.php");
}


function consume_rss() {

    $labels = array(
        'name' => _x( 'RSS Consumer', 'rss_consumer' )
    );

}
add_action( 'init', 'consume_rss' );


function setUpRss()
{
    // Get the XML RSS feed data
    $rssDoc = new DOMDocument('1.0', 'utf-8');
    $rssDoc->load(
        'https://demo.editoreye.com/feed/rss/h/9e6fd6006857c54b7e333e32a5ab052d6d2abc4ed1da'
    );

    return $rssDoc;
}

function getItem(\DOMNode $itemDomNode)
{
    $item = new \stdClass();
    $item->title = $itemDomNode->getElementsByTagName('title')->item(0)->firstChild->data;
    $item->link = $itemDomNode->getElementsByTagName('link')->item(0)->firstChild->data;
    $item->description = $itemDomNode->getElementsByTagName('description')->item(0)->firstChild->data;
    $authorText = $itemDomNode->getElementsByTagName('author')->item(0)->firstChild->data;

    $arr = explode(' ',trim($authorText));
    $item->authorEmail = trim($arr[0]);

    $item->guid = $itemDomNode->getElementsByTagName('guid')->item(0)->firstChild->data;
    $item->pubDate = $itemDomNode->getElementsByTagName('pubDate')->item(0)->firstChild->data;
    $item->viaUrl = $itemDomNode->getElementsByTagNameNS('http://www.editoreye.com/rss/via','link')->item(0)->getAttribute('url');
    $item->via = $itemDomNode->getElementsByTagNameNS('http://www.editoreye.com/rss/via','link')->item(0)->getAttribute('name');
    $item->authorName = $itemDomNode->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/','creator')->item(0)->firstChild->data;
    $item->content = $itemDomNode->getElementsByTagName('content')->item(0)->firstChild->data;

    return $item;
}

function getChannel($rss)
{
    return $rss->getElementsByTagName('channel')->item(0);
}

function getItems($rss)
{
        $channel = getChannel($rss);
        $items=array();
        foreach ($channel->getElementsByTagName('item') as $domItem) {
            $items[] = getItem($domItem);
        }
    return $items;
}

function writePost($item)
{

    //create or get post author
    $author = getOrCreateAuthor($item->authorEmail, $item->authorName);

    if($postId = wp_exist_page_by_title($item->title)) {

       /* //update
        $my_post = array(
            'ID' => $postId,
            'post_title' => $item->title,
            'post_date' => $_SESSION['cal_startdate'],
            'post_content' => $item->description,
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $author
        );

        $the_post_id = wp_update_post( $my_post );*/

    }else{

        $externalLink = "<br/><br/><a target='_blank' href='{$item->viaUrl}' title='Read more on {$item->via}' >Read more on {$item->via}</a>";
        $description = $item->description;



        $my_post = array(
            'post_title' => $item->title,
            'post_date' => $_SESSION['cal_startdate'],
            'post_content' => $description.$externalLink,
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $author
        );
        $the_post_id = wp_insert_post( $my_post );

        add_post_meta($the_post_id,  'from Rss', 'from-rss', true);
    }

}

function getOrCreateAuthor($email,$name)
{
    $user_id = email_exists($email);
    if (!$user_id and email_exists($email) == false) {
        $random_password = wp_generate_password($length = 12, $include_standard_special_chars = false);
        $user_id = wp_create_user($name, $random_password, $email);
    }

    return $user_id;
}

function wp_exist_page_by_title($title_str) {

    $post =  get_page_by_title($title_str,'OBJECT','post');

    if($post) {
        return $post->ID;
    }
}


// add custom interval
function cron_add_minute( $schedules ) {
    // Adds once every minute to the existing schedules.
    $schedules['everyminute'] = array(
        'interval' => 60,
        'display' => __( 'Once Every Minute' )
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'cron_add_minute' );

// create a scheduled event (if it does not exist already)
function cronstarter_activation() {
    if( !wp_next_scheduled( 'mycronjobrss' ) ) {
        wp_schedule_event( time(), 'everyminute', 'mycronjobrss' );
    }
}
// and make sure it's called whenever WordPress loads
add_action('wp', 'cronstarter_activation');

// unschedule event upon plugin deactivation
function cronstarter_deactivate() {
    // find out when the last event was scheduled
    $timestamp = wp_next_scheduled ('mycronjobrss');
    // unschedule previous event if any
    wp_unschedule_event ($timestamp, 'mycronjobrss');
}
register_deactivation_hook (__FILE__, 'cronstarter_deactivate');

// here's the function we'd like to call with our cron job
function my_repeat_function_rss() {

    // do here what needs to be done automatically as per your schedule
    $rss = setUpRss();
    $items = getItems($rss);

    $items = array_reverse($items);
    foreach($items as $item) {
        writePost($item);
    }
}

// hook that function onto our scheduled event:
add_action ('mycronjobrss', 'my_repeat_function_rss');


/*$rss = setUpRss();
$items = getItems($rss);

foreach($items as $item) {
    writePost($item);
}*/