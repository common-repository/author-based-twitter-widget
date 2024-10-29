<?php

/*
  Plugin Name: Author Based Twitter Widget
  Plugin URI: http://blog.tommyolsen.net/category/programming/wp-prog/author-based-twitter-widget/
  Description: Lets individual blog authors have their own tweets on their own site.
  Version: 0.1.1
  Author: Tommy Stigen Olsen
  Author URI: http://blog.tommyolsen.net
  License: BSD
 */
?>
<?php
/*
 *
 *
 *
 */
function getCurlData($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
function makeClickableLinks($text) {

    $text = eregi_replace('(((f|ht){1}tp://)[-a-zA-Z0-9@:%_\+.~#?&//=]+)',
                    '<a href="\\1">\\1</a>', $text);
    $text = eregi_replace('([[:space:]()[{}])(www.[-a-zA-Z0-9@:%_\+.~#?&//=]+)',
                    '\\1<a href="http://\\2">\\2</a>', $text);
    $text = eregi_replace('([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,3})',
                    '<a href="mailto:\\1">\\1</a>', $text);
    $text = ereg_replace("@([a-zA-Z0-9_]+)",'<a href="http://www.twitter.com/\\1">@\\1</a>', $text);
    return $text;
}
function domNodeToArray($DOMNode){
    $ret = array();
    $i = 0;


    while($i < $DOMNode->childNodes->length)
    {
        $child = $DOMNode->childNodes->item($i);

        if($child->childNodes->length > 1)
            $ret[$child->nodeName] = domNodeToArray($child);
        else
            $ret[$child->nodeName] = $child->nodeValue;
        $i++;
    }
    return $ret;
}
function getUserDataFromDOMDocument($dom) {
    $user_info = array();

    $u_inf = $dom->getElementsByTagName("user");
    $u_inf = $u_inf->item(0);

    for ($i = 0; $i < $u_inf->childNodes->length; $i++)
        $user_info[$u_inf->childNodes->item($i)->nodeName] = $u_inf->childNodes->item($i)->nodeValue;

    return $user_info;
}
function getTweetsFromDOMDocument($dom = null, $num_tweets = 5, $user_info_status_count = null) {
    if ($dom == null)
        return -1;

    // If user has less than $num_tweets, we set it to max.
    if ($user_info_status_count <= 4)
        $num_tweets = $user_info_status_count;

    //get all status messages
    $statuses = $dom->getElementsByTagName("status");
    $tweets = array();

    for ($i = 0; $i < $num_tweets; $i++) {
        $kai = domNodeToArray($statuses->item($i));
        $tweets[$i] = $kai;
    }

    return $tweets;
}
function TwitterAPICallSuccess($dom){
    $status = $dom->getElementsByTagName("error");
            if ($status->length != 0) {
                return false;
            }
            else
                return true;
}
function getTwitterAPILink($username){
    return "http://api.twitter.com/1/statuses/user_timeline/" . $username . ".xml";
}
function getFullLocationUrl($filename){
    return ABSPATH . 'wp-content/plugins/author-based-twitter-widget/' . $filename;
}
function alternateNumber(){
    static $globalt = 1;
    
    if($globalt == 1)
    {
        $globalt = 2;
        return 1;
    }
    else
    {
        $globalt = 1;
        return 2;
    }
}

function abt_widget() {
    // Fetching Author Information and Widget Options
    global $authordata;
    $options = get_option('abt_widget');

    $twitter_username = get_the_author_meta('abt_twitter', $authordata->ID);

    if (is_single ()) {
        if ($twitter_username != "") {
            $time_start = microtime(true);
            $template = file_get_contents(getFullLocationUrl('abt_widget_template.html'));

            //
            //
            // Fetching and parsing twitter data
            // Checking if the user exists and loading was complete.
            $time_before_twitterfetch = microtime(true);
            $data = getCurlData(getTwitterAPILink($twitter_username));
            $time_after_twitterfetch = microtime(true);
            $dom = new DOMDocument();
            $dom->loadXML($data);
            unset($data);   // no longer needed
            if(!TwitterAPICallSuccess($dom))
            {
                print "Username the Author supplied is wrong!";
                return;
            }
            
            // Parsing the data
            // Getting the important stuff!
            $user_info = getUserDataFromDOMDocument($dom);
            $tweets = getTweetsFromDOMDocument($dom, $options['max_tweets'], $user_info['statuses_count']);
            unset($dom);



            // Creating replacement variables from
            // User information and recovered tweets
            $parg = array();
            $std_template = file_get_contents(getFullLocationUrl('abt_twitterelement_template.html'));
            
            foreach ($tweets as $tweet) {
                $created_at = explode("+", $tweet['created_at']);
                $temp = $std_template;

                $temp_arg['alt'] = alternateNumber();
                $temp_arg['text'] = makeClickableLinks($tweet['text']);
                $temp_arg['date'] = $created_at[0];

                if($tweet['in_reply_to_screen_name'] != "") $temp_arg['reply'] = "In reply to: " . $tweet['in_reply_to_screen_name'];
                else $temp_arg['reply'] = "";

                if($tweet['retweeted'] == "true")
                    $temp_arg['retweet'] = "RT";
                else
                    $temp_arg['retweet'] = "";

                if($temp_arg['retweet'] == "RT")
                {
                    $temp_arg['reply'] .= "<br />Retweet of " . $tweet['user']['name'];
                }
                // Using the Replacement Tags
                $key_ar = array_keys($temp_arg);
                foreach($key_ar as $key)
                    $temp = str_replace ("[". strtoupper ($key) . "]", $temp_arg[$key], $temp);

                $parg['data'] .= $temp;
            }
            unset($std_template);

            //
            // Creating Replacement variables for the most important data
            $parg['title'] = $options['title'];
            $parg['name'] = $user_info['name'];
            $parg['screen_name'] = $user_info['screen_name'];
            $parg['friends_count'] = $user_info['friends_count'];
            $parg['followers_count'] = $user_info['followers_count'];
            $parg['profile_image_url'] = $user_info['profile_image_url'];
            $parg['location'] = $user_info['location'];
            $key_ar = array_keys($parg);

            // Using the Replacement Tags
            foreach ($key_ar as $key)
                $template = str_replace("[" . strtoupper($key) . "]", $parg[$key], $template);
            print $template;

            $after_time = microtime(TRUE);
            return;
        }
    }
}
function abt_widget_control() {
    $content = file_get_contents(getFullLocationUrl('abt_control_template.html'));

    $options = get_option('abt_widget');

    // Updating the Options
    if($_POST['abt-submit'] == "1")
    {
        $newoptions = $options;
        $newoptions['title'] = strip_tags(stripslashes($_POST['abt-title']));
        $newoptions['max_tweets'] = strip_tags(stripslashes($_POST['abt-max_tweets']));

        if($options != $newoptions)
        {
            $options = $newoptions;
            update_option('abt_widget', $newoptions);
        }
    }

    // Replacement variable
    $parg = array();
    $parg['control_title'] = $options['title'];
    $parg['control_max_tweets'] = $options['max_tweets'];

    $key_ar = array_keys($parg);
    foreach($key_ar as $key)
        $content = str_replace("[". strtoupper ($key) . "]", $parg[$key], $content);

    echo $content;


    return;
}


//
// Activation and Deactivation functions
function abt_activate() {
    // Setting Initial Option Values
    $options = array(
        'widget' => array(
            'title' => 'About Author',
            'max_tweets' => 5
        )
    );


    add_option('abt_widget', $options['widget']);
    return;
}
function abt_deactivate() {
    delete_option('abt_widget');

    return;
}
function abt_init() {
    $class['classname'] = 'abt_widget';
    wp_register_sidebar_widget('tommy_abt_widget', __('Author Based Twitter Widget'), 'abt_widget', $class);
    wp_register_widget_control('tommy_abt_widget', __('Author Based Twitter Widget'), 'abt_widget_control', 'width=200&height=200');


    return;
}
// Styles and Extra profile fields
function abt_addstyles(){
    $style = WP_PLUGIN_URL . '/author-based-twitter-widget/abt_style.css';
    $location = WP_PLUGIN_DIR . '/author-based-twitter-widget/abt_style.css';

    if(is_single())
    {
        if (file_exists($location))
        {
            wp_register_style('abt_template', $style);
            wp_enqueue_style('abt_template');
        }
    }
}
function abt_extra_profile_fields($user) {
    $template_path = ABSPATH . 'wp-content/plugins/author-based-twitter-widget/abt_extra_field_template.html';
    require($template_path);

    return;
}
function abt_save_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id))
        return false;

    update_usermeta($user_id, 'abt_twitter', $_POST['abt_twitter']);
    return;
}


//
// Plugin Add Actions
add_action('activate_' . plugin_basename(__FILE__), 'abt_activate');
add_action('deactivate_' . plugin_basename(__FILE__), 'abt_deactivate');
add_action('init', 'abt_init');

add_action('show_user_profile', 'abt_extra_profile_fields');
add_action('edit_user_profile', 'abt_extra_profile_fields');

add_action('personal_options_update', 'abt_save_profile_fields');
add_action('edit_user_profile_update', 'abt_save_profile_fields');
add_action('wp_print_styles', 'abt_addstyles');

?>
