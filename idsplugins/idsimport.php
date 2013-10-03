<?php
/*
Plugin Name: IDS Import
Plugin URI: http://api.ids.ac.uk/category/plugins/
Description: Imports content from the IDS collection via the IDS Knowledge Services API.
Version: 1.0
Author: Pablo Accuosto for the Institute of Development Studies (IDS)
Author URI: http://api.ids.ac.uk/
License: GPLv3

    Copyright 2012  Institute of Development Studies (IDS)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once('idsimport.default.inc');
require_once(IDS_API_LIBRARY_PATH . 'idsapi.wrapper.inc');

require_once('idsplugins.customtypes.inc');
require_once('idsimport.interface.inc');
require_once('idsimport.metadata.inc');
require_once('idsimport.admin.inc');
require_once('idsimport.importer.inc');

//-------------------------------- Set-up hooks ---------------------------------

register_activation_hook(__FILE__, 'idsimport_activate');

add_action('init', 'idsimport_init');
add_action('admin_init', 'idsimport_admin_init');
add_action('admin_menu', 'idsimport_add_options_page');
add_action('admin_menu', 'idsimport_add_menu', 9);
add_action('admin_menu', 'idsimport_remove_submenu_pages');
add_action('admin_notices', 'idsimport_admin_notices');
add_action('wp_enqueue_scripts', 'idsimport_add_stylesheet');
add_action('admin_enqueue_scripts', 'idsimport_add_admin_stylesheet');
add_action('admin_enqueue_scripts', 'idsimport_add_javascript');
add_filter('plugin_action_links', 'idsimport_plugin_action_links', 10, 2);
add_filter('manage_posts_columns', 'idsimport_posts_columns');
add_action('manage_posts_custom_column', 'idsimport_populate_posts_columns');
add_filter('cron_schedules', 'idsimport_cron_intervals'); 
add_action('idsimport_scheduled_events', 'idsimport_run_periodic_imports', 10, 1);
add_filter('wp_get_object_terms', 'idsimport_filter_get_object_terms');
add_filter('the_category', 'idsimport_filter_the_category');
add_filter('list_cats', 'idsimport_filter_list_cats');
add_filter('request', 'idsimport_filter_imported_tags');
add_filter('query_vars', 'idsimport_query_vars');
add_action('pre_get_posts', 'idsimport_include_idsassets_loop');
add_filter('pre_get_posts', 'idsimport_filter_posts');

//Uncomment to filter category names when editing the categories, too.
//add_filter('get_term', 'idsimport_filter_get_term');

add_action('delete_term', 'idsimport_delete_term', 10, 3);

//--------------------------- Set-up / init functions ----------------------------

// Just in case, we make sure to delete the cache on activation.
function idsimport_activate() {
  $idsapi = new IdsApiWrapper;
  $idsapi->cacheFlush();  
}

// Register custom types, taxonomies and importer.
function idsimport_init() {
  // Register post types
  ids_post_types_init();
  // Register taxonomies.
  idsimport_taxonomies_init();
  // Register importer
  if (class_exists('IDS_Importer')) {
    $ids_importer = new IDS_Importer();
    register_importer('idsimport_importer', __('IDS Importer', 'idsimport-importer'), __('Import posts from the IDS collection (Eldis and Bridge).', 'idsimport-importer'), array ($ids_importer, 'dispatch'));
  }
  idsimport_check_permalinks_changed();
}

// Clean up on deactivation
function idsimport_deactivate() {
  global $wp_rewrite;
  $idsapi = new IdsApiWrapper;
  idsimport_delete_plugin_options();
  idsimport_unschedule_imports();
  $idsapi->cacheFlush();
  $wp_rewrite->flush_rules();
}

// Clean up on uninstall
function idsimport_uninstall() {
  idsimport_delete_taxonomy_metadata();
}

// Delete options entries
function idsimport_delete_plugin_options() {
	delete_option('idsimport_options');
}

// Initialize the plugin's admin options
function idsimport_admin_init(){
  register_setting('idsimport', 'idsimport_options', 'idsimport_validate_options');
  $options = get_option('idsimport_options');
  if(!is_array($options)) { // The options are corrupted.
    idsimport_delete_plugin_options();
  }
  idsimport_edit_post_form();
  register_deactivation_hook(__FILE__, 'idsimport_deactivate');
  register_uninstall_hook(__FILE__, 'idsimport_uninstall');
}

//------------------------ New post types and taxonomies -------------------------

// Create new custom taxonomies for the IDS categories.
function idsimport_taxonomies_init() {
  $ids_taxonomies = array('countries' => 'Country', 'regions' => 'Region', 'themes' => 'Theme');
  //$datasets = idsimport_get_datasets();
  global $ids_datasets;
  idsimport_create_taxonomy_metadata();
  foreach ($ids_datasets as $dataset) {
    foreach ($ids_taxonomies as $taxonomy => $singular_name) {
      $taxonomy_name = $dataset . '_' . $taxonomy;
      $taxonomy_label = ucfirst($dataset) . ' ' . ucfirst($taxonomy);
      $singular_name = ucfirst($dataset) . ' ' . $singular_name;
      idsimport_new_taxonomy($taxonomy_name, $taxonomy_label, $singular_name, TRUE);
      // Add additional fields in the imported categories' 'edit' page.
      $form_fields_hook = $dataset . '_' . $taxonomy . '_' . 'edit_form_fields';
      $new_metabox = 'idsimport_' . $taxonomy . '_metabox_edit';
      add_action($form_fields_hook, $new_metabox, 10, 1);
      // Add additional columns in edit-tags.php.
      $manage_columns_filter = 'manage_edit-' . $taxonomy_name . '_columns';
      $manage_columns_action = 'manage_' . $taxonomy_name . '_custom_column';
      add_filter($manage_columns_filter, 'idsimport_edit_categories_header', 10, 2);
      add_action($manage_columns_action, 'idsimport_edit_categories_populate_rows', 10, 3);
    }
    $countries_taxonomy = $dataset . '_countries';
    $regions_taxonomy = $dataset . '_regions';
    if ((taxonomy_exists($countries_taxonomy)) && (taxonomy_exists($regions_taxonomy))) {
      register_taxonomy_for_object_type($regions_taxonomy, $countries_taxonomy);
    }
  }
}

// When a custom term is deleted, it also deletes it's metadata.
function idsimport_delete_term($term_id, $tt_id, $taxonomy) {
  if ($taxonomy == 'eldis_countries' || $taxonomy == 'eldis_regions' || $taxonomy == 'eldis_themes' || $taxonomy == 'bridge_countries' || $taxonomy == 'bridge_regions' || $taxonomy == 'bridge_themes') {
    idsimport_delete_all_term_meta($term_id);
  }
}

// Deletes all imported terms of IDS categories.
function idsimport_delete_taxonomy_terms($taxonomy) {
  $res = TRUE;
  $terms = get_categories(array('taxonomy' => $taxonomy, 'hide_empty' => FALSE));
	foreach ($terms as $term) {
    if (isset($term->term_id)) {
      $res = ($res && wp_delete_term($term->term_id, $taxonomy));
    }
  }
  return $res;
}

// Create a new taxonomy.
function idsimport_new_taxonomy($taxonomy_name, $taxonomy_label, $singular_name, $is_hierarchical) {
  global $wp_rewrite;
  if (!taxonomy_exists($taxonomy_name)) {
    $labels = array(
      'name' => _x( $taxonomy_label, 'taxonomy general name' ),
      'singular_name' => _x( $singular_name, 'taxonomy singular name' ),
      'search_items' =>  __( 'Search ') . __( $taxonomy_label ),
      'all_items' => __( 'All ') . __( $taxonomy_label ),
      'parent_item' => __( 'Parent ') . __( $singular_name ),
      'parent_item_colon' => __( 'Parent ') . __( $singular_name ) . ':',
      'edit_item' => __( 'Edit ') . __( $singular_name ), 
      'update_item' => __( 'Update ') . __( $singular_name ),
      'add_new_item' => __( 'Add New ') . __( $singular_name ),
      'new_item_name' => __( 'New ') . __( $singular_name ) . __( ' Name' ),
    );
    $args = array(
        'hierarchical' => $is_hierarchical,
        'labels' => $labels,
        'query_var' => true,
        'show_ui' => true,
        'show_in_nav_menus' => false,
        'show_in_menu' => false,
        'rewrite' => array('slug' => $taxonomy_name)
      );
    register_taxonomy(
      $taxonomy_name,
      // Uncomment to make IDS taxonomies available to regular posts, too.
      // array ('post', 'ids_documents', 'ids_organisations'),
      array ('ids_documents', 'ids_organisations'),
      $args
    );
    $wp_rewrite->flush_rules();
  }
}

// Mark posts as pending.
function idsimport_unpublish_assets($post_type, $dataset) {
  $res = TRUE;
  $posts = get_posts(array('numberposts' => -1, 'post_type' => $post_type, 'meta_key' => 'site', 'meta_value' => $dataset));
  $new_post = array();
	foreach ($posts as $post) {
    $new_post['ID'] = $post->ID;
    $new_post['post_status'] = 'pending';
    $res  = $res && wp_update_post($new_post);
  }
  return $res;
}

// If selected, include imported documents and organisations in the loop.
function idsimport_include_idsassets_loop($query) {
  if ((is_home() && $query->is_main_query()) || is_category() || is_feed() ) {
    $idsimport_include_imported_documents = idsapi_variable_get('idsimport', 'include_imported_documents', IDS_IMPORT_INCLUDE_IMPORTED_DOCUMENTS);
    $idsimport_include_imported_organisations = idsapi_variable_get('idsimport', 'include_imported_organisations', IDS_IMPORT_INCLUDE_IMPORTED_ORGANISATIONS);

    // Type of IDS assets to show by default, according to the plugin settings.
    $all_post_types = get_post_types();
    if (!$idsimport_include_imported_documents) {
      unset($all_post_types['ids_documents']);
    }
    if (!$idsimport_include_imported_organisations) {
      unset($all_post_types['ids_organisations']);
    }   
    // If the post type is filtered in the URL.
    $post_type = get_query_var('post_type');
    if (empty($post_type)) {
      $post_types = $all_post_types;
    }
    elseif (is_string($post_type)) {
      $post_types = array($post_type);
    }
    elseif (is_array($post_type)) {
      $post_types = array_merge($post_type, $all_post_types);
    }
    $query->set('post_type', $post_types);
  }
}

// Removes "(object_id") from the category names before displaying them.
function idsimport_filter_get_object_terms($categories) {
  $new_categories = array();
  foreach ($categories as $category) {
    if (is_object($category)) {
      if (preg_match('/[eldis][bridge]/', $category->taxonomy)) {
        $category->name = idsimport_filter_the_category($category->name);
      }
    }
    $new_categories[] = $category;
  }
  return $new_categories;
}

function idsimport_filter_get_term($term) {
  if (is_object($term)) {
    if (preg_match('/[eldis][bridge]/', $term->taxonomy)) {
      $term->name = idsimport_filter_the_category($term->name);
    }
  }
  return $term;
}

function idsimport_filter_list_cats($cat_name) {
  return idsimport_filter_the_category($cat_name);
}

function idsimport_filter_the_category($category_name) {
  $category_name = preg_replace( '/\s*\([AC](\d+)\)/', '', $category_name);
  return $category_name;
} 

function idsimport_filter_imported_tags($request) {
    if ( isset($request['tag']) && !isset($request['post_type']) )
    $request['post_type'] = 'any';
    return $request;
} 

function idsimport_query_vars($query_vars) {
  $query_vars[] = 'ids_site';
  return $query_vars;
}

function idsimport_filter_posts($query) {
  $display_sites = idsimport_display_datasets('public');
  if(isset($_GET['ids_site'])) {
    $site = $_GET['ids_site'];
    if (($site == 'eldis') || ($site == 'bridge')) {
      $query->set('meta_key', 'site');
      $query->set('meta_value', $site);
    }
	}
	return $query;  
}

//---------------------- Admin interface (links, menus, etc -----------------------

// Add settings link
function idsimport_add_options_page() {
  add_options_page('IDS Import Settings Page', 'IDS Import', 'manage_options', 'idsimport', 'idsimport_admin_main');
}

// Add menu
function idsimport_add_menu() {
  global $ids_categories;
  global $ids_assets;
  $idsimport_menu_title = idsapi_variable_get('idsimport', 'menu_title', 'IDS Import');
  $idsimport_new_categories = idsapi_variable_get('idsimport', 'import_new_categories', IDS_IMPORT_NEW_CATEGORIES);
  $datasets = idsimport_display_datasets('admin');

  add_menu_page('IDS API', $idsimport_menu_title, 'manage_options', 'idsimport_menu', 'idsimport_general_page', plugins_url('images/ids.png', __FILE__));
  add_submenu_page( 'idsimport_menu', 'Administration', 'Administration', 'manage_options', 'idsimport_menu');
  add_submenu_page( 'idsimport_menu', 'Settings', 'Settings', 'manage_options', 'options-general.php?page=idsimport');
  add_submenu_page( 'idsimport_menu', 'IDS Importer', 'IDS Importer', 'manage_options', 'admin.php?import=idsimport_importer');

  if (in_array('eldis', $datasets) && in_array('bridge', $datasets)) {
    add_submenu_page( 'idsimport_menu', 'All IDS Documents', 'All IDS Documents', 'manage_options', 'edit.php?post_type=ids_documents');
  }

  if (in_array('eldis', $datasets) && in_array('bridge', $datasets)) {
    add_submenu_page( 'idsimport_menu', 'All IDS Organisations', 'All IDS Organisations', 'manage_options', 'edit.php?post_type=ids_organisations');
  }

  foreach ($datasets as $dataset) {
    foreach ($ids_assets as $asset) {
      if (!idsapi_exclude($dataset, $asset)) {
        $name = ucfirst($dataset) . ' ' . ucfirst($asset);
        $function = 'edit.php?post_type=ids_' . $asset . '&ids_site=' . $dataset;
        add_submenu_page( 'idsimport_menu', $name, $name, 'manage_options', $function);
      }
    }
    if ($idsimport_new_categories) {
      foreach ($ids_categories as $category) {
        if (!idsapi_exclude($dataset, $category)) {
          $slug = $dataset . '_' . $category;
          $name = ucfirst($dataset) . ' ' . ucfirst($category);
          $function = 'idsimport_' . $dataset . '_' . $category . '_page';
          add_submenu_page( 'idsimport_menu', $name, $name, 'manage_options', $slug, $function);
        }
      }
    }
  }

  add_submenu_page( 'idsimport_menu', 'Help', 'Help', 'manage_options', 'idsimport_help', 'idsimport_help_page');
}

// Remove links to IDS categories from the posts admin menu.
// Not necessary if taxonomies are not registered for regular posts.
function idsimport_remove_submenu_pages() {
  global $ids_categories;
  global $ids_datasets;
  foreach ($ids_datasets as $dataset) {
    foreach ($ids_categories as $category) {
      $link = 'edit-tags.php?taxonomy=' . $dataset . '_' . $category;
      remove_submenu_page( 'edit.php', $link );
    }
  }
}

// Display a 'Settings' link on the main Plugins page
function idsimport_plugin_action_links($links, $file) {
	if ($file == plugin_basename(__FILE__)) {
		$idsapi_links = '<a href="' . get_admin_url() . 'options-general.php?page=idsimport">' . __('Settings') . '</a>';
		array_unshift($links, $idsapi_links);
	}
	return $links;
}

// Enqueue stylesheet
function idsimport_add_stylesheet() {
    wp_register_style('idsimport_style', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'idsplugins.css', __FILE__));
    wp_enqueue_style('idsimport_style');
}

// Enqueue stylesheet
function idsimport_add_admin_stylesheet() {
    wp_register_style('idsimport_chosen_style', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'chosen/chosen.css', __FILE__));
    wp_enqueue_style('idsimport_chosen_style');
    wp_register_style('idsimport_jqwidgets_style', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/styles/jqx.base.css', __FILE__));
    wp_enqueue_style('idsimport_jqwidgets_style');
}

// Enqueue javascript
function idsimport_add_javascript($hook) {
  global $ids_datasets;
  if ($hook == 'settings_page_idsimport') { // Only in the admin page.
    wp_print_scripts( 'jquery' );
    wp_print_scripts( 'jquery-ui-tabs' );

    wp_register_script('idsimport_chosen_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'chosen/chosen.jquery.js', __FILE__));
    wp_enqueue_script('idsimport_chosen_javascript');

    wp_register_script('idsimport_jqwidgets_jqxcore_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/jqwidgets/jqxcore.js', __FILE__));
    wp_enqueue_script('idsimport_jqwidgets_jqxcore_javascript');

    wp_register_script('idsimport_jqwidgets_jqxbuttons_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/jqwidgets/jqxbuttons.js', __FILE__));
    wp_enqueue_script('idsimport_jqwidgets_jqxbuttons_javascript');

    wp_register_script('idsimport_jqwidgets_jqxdropdownbutton_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/jqwidgets/jqxdropdownbutton.js', __FILE__));
    wp_enqueue_script('idsimport_jqwidgets_jqxdropdownbutton_javascript');

    wp_register_script('idsimport_jqwidgets_jqxscrollbar_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/jqwidgets/jqxscrollbar.js', __FILE__));
    wp_enqueue_script('idsimport_jqwidgets_jqxscrollbar_javascript');

    wp_register_script('idsimport_jqwidgets_jqxpanel_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/jqwidgets/jqxpanel.js', __FILE__));
    wp_enqueue_script('idsimport_jqwidgets_jqxpanel_javascript');

    wp_register_script('idsimport_jqwidgets_jqxtree_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/jqwidgets/jqxtree.js', __FILE__));
    wp_enqueue_script('idsimport_jqwidgets_jqxtree_javascript');

    wp_register_script('idsimport_jqwidgets_jqxcheckbox_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/jqwidgets/jqxcheckbox.js', __FILE__));
    wp_enqueue_script('idsimport_jqwidgets_jqxcheckbox_javascript');

    wp_register_script('idsimport_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'idsplugins.js', __FILE__));
    wp_enqueue_script('idsimport_javascript');

    $api_key = idsapi_variable_get('idsimport', 'api_key', '');
    $api_key_validated = idsapi_variable_get('idsimport', 'api_key_validated', FALSE);
    $default_dataset = idsapi_variable_get('idsimport', 'default_dataset', 'eldis');
    foreach ($ids_datasets as $dataset) {
      $countries[$dataset] = idsapi_variable_get('idsimport', $dataset . '_countries_assets', array());
      $regions[$dataset] = idsapi_variable_get('idsimport', $dataset . '_regions_assets', array());
      $themes[$dataset] = idsapi_variable_get('idsimport', $dataset . '_themes_assets', array());
      $countries_mappings[$dataset] = idsapi_variable_get('idsimport', $dataset . '_countries_mappings', array());
      $regions_mappings[$dataset] = idsapi_variable_get('idsimport', $dataset . '_regions_mappings', array());
      $themes_mappings[$dataset] = idsapi_variable_get('idsimport', $dataset . '_themes_mappings', array());
    }
    $categories = array('countries' => $countries, 'regions' => $regions, 'themes' => $themes);
    $categories_mappings = array('countries' => $countries_mappings, 'regions' => $regions_mappings, 'themes' => $themes_mappings);
    $default_user = idsapi_variable_get('idsimport', 'import_user', IDS_IMPORT_IMPORT_USER);
    ids_init_javascript($api_key, $api_key_validated, $default_dataset, $categories, $categories_mappings, $default_user);
  }
}







