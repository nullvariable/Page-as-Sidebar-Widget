<?php
/*
Plugin Name: Page as Sidebar Widget
Plugin URI: 
Description: Allows pages to be selected for display in a sidebar widget
Version: 0.0.1
Author: Doug Cone
Author URI: http://www.nullvariable.com/
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
    
  } else {
    print __("No sub pages found, add a subpage to use it as a widget");
  }
}

/**
 * Implementaiton of Wordpress action hook, save_post
 */
add_action('save_post', 'pasw_save_post');
function pasw_save_post() {
  if ($_POST['action'] == 'editpost') {
    update_post_meta($_POST['post_ID'], 'pasw_widget_page_id', $_POST['pasw_select']);
    //we need to notify child pages that they're not to be displayed
    delete_post_meta($_POST['pasw_last_subpage'], 'pasw_is_subpage'); //unset the old child page from being filtered
    if (!empty($_POST['pasw_select'])) { update_post_meta($_POST['pasw_select'], 'pasw_is_subpage', TRUE); } //set the new child page to be filtered
    
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
        if ($issubpage) {
          unset($pages[$key]);
        }
      }
    }
  }
    //print '<pre>';
    //print_r(get_defined_vars());die();
  return $pages;
}
