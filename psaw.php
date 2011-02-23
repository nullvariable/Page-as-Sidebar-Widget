<?php
/*
Plugin Name: Page as Sidebar Widget
Plugin URI: 
Description: Allows pages to be selected for display in a sidebar widget
Version: 0.0.2
Author: Doug Cone
Author URI: http://www.nullvariable.com/
Change log
  02/23/2011 - fixed bug on page listing filter
  02/23/2011 - removed menu item for update action since we've run this on our sites
  02/22/2011 - added filtering and page forwarding so that sidebar pages can't be accessed directly
*/

register_sidebar_widget('Page as Widget', 'widget_pasw');
function widget_pasw($args) {
  if (is_page()) {
    $pageid = $GLOBALS['post']->ID;
    $subpageid = get_post_meta($pageid, 'pasw_widget_page_id', true);
    if ($subpageid) {
      extract($args);
      $subpage = get_post($subpageid);
      print $before_widget;
      print $before_title . $subpage->post_title . $after_title;
      print $subpage->post_content;
      edit_post_link('Edit', '<p>','</p>', $subpageid);
      print $after_widget;
    }
  }
}

/**
 * Implementation of WordPress action hook, add_meta_box
 * add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
 */
add_action('add_meta_boxes', 'pasw_add_meta_box');
function pasw_add_meta_box() {
  add_meta_box('paswmetabox', 'Page as Widget', 'pasw_meta_box', 'page', 'side', 'high');
}
function pasw_meta_box($page) {
  $children = get_pages('child_of='.$page->ID); //load all the kids
  if ( count($children) != 0 ) { //display the select box only if we have child pages
    print '<p>';
    print '<strong>'.__("Page").': </strong>';
    print '<label for="pasw_select" class="screen-reader-text">'.__("Page to display as Widget").'</label>';
    $default = (get_post_meta($page->ID, 'pasw_widget_page_id', true));
    wp_dropdown_pages('child_of='.$page->ID.'&name=pasw_select&show_option_none=None&selected='.$default);
    print '</p>';
    print '<input type="hidden" id="pasw_last_subpage" name="pasw_last_subpage" value="'.$default.'" />';
    if ($default) {
      $dtitle = get_the_title($default);
      edit_post_link('edit '. $dtitle, '<p>', '</p>', $default);
    }
  } else {
    $parentid = get_post_meta($page->ID, 'pasw_is_subpage');
    if (is_numeric($parentid[0])) {
      $title = get_the_title($parentid[0]);
      $link = get_page_link($parentid[0]);
      $editlink = get_edit_post_link($parentid[0]);
      print __("This page is a sidebar of '<a href=\"".$link."\">".$title."</a>', you can edit '".$title."' <a href=\"".$editlink."\">here</a>.");
    } else {
      print __("No sub pages found, add a subpage to use it as a widget");
    }
  }
}

/**
 * Implementation of Wordpress action hook, save_post
 */
add_action('save_post', 'pasw_save_post');
function pasw_save_post() {
  if ($_POST['action'] == 'editpost') {
    $sidebarid = $_POST['pasw_select'];
    $parentid = $_POST['post_ID'];
    update_post_meta($parentid, 'pasw_widget_page_id', $sidebarid);
    //we need to notify child pages that they're not to be displayed
    delete_post_meta($_POST['pasw_last_subpage'], 'pasw_is_subpage'); //unset the old child page from being filtered
    if (!empty($_POST['pasw_select'])) { update_post_meta($sidebarid, 'pasw_is_subpage', $parentid); } //set the new child page to be filtered
    
  }
}


/**
 * Implementation of WordPress filter hook, get_pages
 * we need to filter any of our chosen widget pages out of all the rest of the site.
 */
add_filter('get_pages', 'pasw_filter_get_pages', '', 2);
function pasw_filter_get_pages($pages, $r) {
  if (!is_admin()) {
    foreach ($pages as $key=>$page) {
      if ($page->post_type == 'page') {
        $issubpage = get_post_meta($page->ID, 'pasw_is_subpage');
        if (is_numeric($issubpage[0])) {
          unset($pages[$key]);
        }
      }
    }
  }
    //print '<pre>';
    //print_r(get_defined_vars());die();
  return $pages;
}

/**
 * redirect pages that are used as sidebars to their parent page
 */
add_action('send_headers', 'pasw_redirect_sidebars_to_main');
function pasw_redirect_sidebars_to_main() {
  if (stristr($_SERVER['QUERY_STRING'], 'page_id=')) {
    //$parts = explode('=', $_SERVER['QUERY_STRING']);
    $pageid = url_to_postid('http://'.$_SERVER['HTTP_HOST'].'/'.$_SERVER['REQUEST_URI']);//$parts[1];
    $issubpage = get_post_meta($pageid, 'pasw_is_subpage');
    if (is_numeric($issubpage[0])) { //if we're a subpage, figure out the parent page and redirect there
      $link = get_permalink($issubpage[0]);
      if (is_user_logged_in()) {
        $title = get_the_title($pageid);
        update_post_meta(
          $issubpage[0],
          'pasw_message',
          'redirected from sidebar post: "'.$title.'" | <a href="'.get_edit_post_link($pageid).'">edit</a>.'
        );
      }
      wp_redirect($link, 302 ); exit;
    }
  }
}
/**
 * Let logged in users know that they've been forwarded to help avoid potential confusion.
 * logged out users will not see this message.
 */
add_action('loop_start', 'pasw_message');
function pasw_message($q) {
  if ($q->is_singular == 1) {
    $m = get_post_meta($q->post->ID, 'pasw_message');
    if ($m[0]) {
      print '<div id="message">'.$m[0].'</div>';
      delete_post_meta($q->post->ID, 'pasw_message');
    }
  }
}

function dpr($value) {
  print '<pre>';
  print_r($value);
  die();
}

/**
 * Previously we just told subpages true or false if they were a sidebar
 * now we tell them the parent's id. Pages using the old style need to be checked
 * and updated. So anything that equals one must be double checked to ensure that
 * it is indeed page/post 1 that is the intended parent. Otherwise we update the
 * stored value accordingly.
 */
function fix_old_pasw() {
  $pages = get_pages(array('number'=>9999)/*array('meta_key'=>'pasw_widget_page_id')*/);
  print "<pre>";
  foreach ($pages as $page) {
    //update the subpage with this pages id so we have a backwards reference
    $parentid = $page->ID;
    $sidebarid = get_post_meta($parentid, 'pasw_widget_page_id');//$page->meta_value;
    if (is_numeric($sidebarid[0])) {
      $old = get_post_meta($sidebarid[0], 'pasw_is_subpage');
      update_post_meta($sidebarid[0], 'pasw_is_subpage', $parentid);
      print __($sidebarid[0]." updated sidebar parent from ". $old[0] ." to ". $parentid ."\n");
      if (!$sidebarid[0]) { print_r($page); }
    }
  }
}
//register_activation_hook(__FILE__, 'fix_old_pasw');
//add_action('admin_menu', 'pasw_menu');

function pasw_menu() {
  add_options_page('Fix PASW','Fix PASW', 'manage_options', 'pasw-slug', 'fix_old_pasw');
}

