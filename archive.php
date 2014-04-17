<?php
/**
 * Plugin Name: Archive
 * Plugin URI: http://premium.bueltge.de/
 * Text Domain: archive
 * Domain Path: /languages
 * Description: Archive your post types, also with cron and customize all via Settings page.
 * Author: Frank Bültge
 * Version: 0.0.5
 * Licence: GPLv2
 * Author URI: http://bueltge.de
 * Last Change: 15.07.2011
 */

/**
License:
==============================================================================
Copyright Frank Bueltge  (email : frank@bueltge.de)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

Requirement
==============================================================================
This plugin requires WordPress >= 3.0 and tested with PHP Interpreter >= 5.3.1
*/

if ( ! class_exists( 'FB_Archive' ) ) {
	
	class FB_Archive {
		
		protected static $classobj;
		
		/*
		 * Key for textdomain
		 * 
		 * @var string
		 */
		public static $textdomain = 'archive';
		
		/*
		 * Key for custom post type
		 * 
		 * @var string
		 */
		public $post_type_1 = 'archiv';
		
		/*
		 * Key for custom taxonomy
		 * 
		 * @var string
		 */
		public $taxonomy_type_1 = 'archive_structure';
		
		// set capabilities on roles
		public $todo_roles = array( 
			'administrator'
			, 'editor' 
		);
		
		public $read_roles = array( 
			'author'
			, 'contributor' 
			, 'subscriber'
		);
		
		/**
		 * Key for save the original post type
		 * 
		 * @var string
		 */
		public $post_meta_key = '_archived_post_type'; // use underline for dont see in custom fields
		
		/**
		 * Keys for view archive-link on defined screens
		 * Add Screen Id or not an array for view link on all screens
		 * @see http://codex.wordpress.org/Plugin_API/Admin_Screen_Reference
		 */
		public $def_archive_screens = ''; //array( 'edit-post', 'edit-page' );
		
		/**
		 * Keys for view undo-archive-link on defined screens
		 */
		public $def_unset_screens = array( 'edit-archiv' );
		
		/**
		 * Key for active Schedluling posts
		 * 
		 * @var boolean
		 */
		public $scheduled_archiving = FALSE; // true or false
		
		/**
		 * Key for days, there we archived posts
		 * 
		 * @var integer
		 */
		public $scheduled_archiving_days = 21; // in days
		
		/**
		 * Keys for scheduled posts types
		 * 
		 * @var array
		 */
		public $scheduled_archiving_post_type = array( 'post' ); // example: 'post', 'page'
		
		/**
		 * Bool for add to query loop
		 * 
		 * @var boolean
		 */
		public $add_to_query = FALSE; // true or false
		
		/**
		 * construct
		 * 
		 * @uses add_filter, add_action, localize_plugin, register_activation_hook, register_uninstall_hook
		 * @access public
		 * @since 0.0.1
		 * @return void
		 */
		public function __construct () {
			
			// include settings on profile
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'inc/class.settings.php';
			$fb_archive_settings = FB_Archive_Settings :: get_object();
			
			// for  WP 3.1 and higher
			//add_filter( 'site_transient_update_plugins', array( &$this, 'remove_update_nag' ) );
			
			// load language file
			$this->localize_plugin();
			
			// on activation of the plugin add cap to roles
			register_activation_hook( __FILE__, array( &$this, 'on_activate' ) );
			// on uninstall remove capability from roles
			register_uninstall_hook( __FILE__, array('FB_Archive', 'on_deactivate' ) );
			
			// add post type
			add_action( 'init', array( &$this, 'build_post_type' ) );
			
			// add scheduled archive
			add_action( 'init', array( $this, 'schedule_archived_check' ) );
			if ( (bool) $this->scheduled_archiving )
				add_action( 'scheduled_archiving', array( $this, 'scheduled_archiving' ) );
			
			add_action( 'admin_init', array( $this, 'add_settings_error' ) );
			
			// admin interface init
			add_action( 'admin_init', array( &$this, 'on_admin_init' ) );
			add_action( 'admin_menu', array( $this, 'remove_menu_entry' ) );
			// include js
			add_action( 'admin_enqueue_scripts',	array( $this, 'enqueue_script' ), 10, 1 );
			
			// help on snippet-pages in next version
			add_action( 'contextual_help', array( &$this, 'add_help_text' ), 10, 3 );
			
			// add to query loop
			if ( $this -> add_to_query )
				add_action( 'pre_get_posts', array( $this, 'add_to_query' ) );
			// add shortcode for list items of archive
			add_shortcode( 'archive', array(&$this, 'add_shortcode') );
		}
		
		
		/**
		 * Return Textdomain string
		 * 
		 * @access  public
		 * @since   0.0.2
		 * @return  string
		 */
		public static function get_textdomain() {
			
			return self::$textdomain;
		}
		
		
		/**
		 * points the class
		 * 
		 * @access public
		 * @since 0.0.1
		 * @return object
		 */
		public static function get_object () {
			
			if ( FALSE === self :: $classobj )
				self :: $classobj = new self;
			
			return self :: $classobj;
		}
		
		
		/**
		 * localize_plugin function.
		 *
		 * @uses   load_plugin_textdomain, plugin_basename
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function localize_plugin () {
			
			load_plugin_textdomain( self::$textdomain, FALSE, dirname( plugin_basename(__FILE__) ) . '/languages' );
		}
		
		
		/**
		 * return plugin comment data
		 * 
		 * @uses   get_plugin_data
		 * @access public
		 * @since  0.0.1
		 * @param  $value string, default = 'Version'
		 *         Name, PluginURI, Version, Description, Author, AuthorURI, TextDomain, DomainPath, Network, Title
		 * @return string
		 */
		public function get_plugin_data( $value = 'Version' ) {
			
			static $plugin_data = array ();
			
			// fetch the data just once.
			if ( isset( $plugin_data[ $value ] ) )
				return $plugin_data[ $value ];
			
			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			
			$plugin_data  = get_plugin_data( __FILE__ );
			$plugin_value = $plugin_data[$value];
			
			return empty ( $plugin_data[ $value ] ) ? '' : $plugin_data[ $value ];
		}
		
		
		/**
		 * On activate plugin
		 *
		 * @uses   deactivate_plugins, get_plugin_data, wp_sprintf, flush_rules
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function on_activate () {
			global $wp_roles, $wp_version;
			
			// check wp version
			if ( ! version_compare( $wp_version, '3.0', '>=' ) ) {
				deactivate_plugins(__FILE__);
				die( 
					wp_sprintf( 
						'<strong>%s:</strong> ' . 
						__( 'Sorry, This plugin requires WordPress 3.0+', self::$textdomain )
						, self::get_plugin_data( 'Name' )
					)
				);
			}
			
			// check php version
			if ( version_compare( PHP_VERSION, '5.2.0', '<' ) ) {
				deactivate_plugins( __FILE__ ); // Deactivate ourself
				die( 
					wp_sprintf(
						'<strong>%1s:</strong> ' . 
						__( 'Sorry, This plugin has taken a bold step in requiring PHP 5.0+, Your server is currently running PHP %2s, Please bug your host to upgrade to a recent version of PHP which is less bug-prone. At last count, <strong>over 80%% of WordPress installs are using PHP 5.2+</strong>.', self::$textdomain )
						, self::get_plugin_data( 'Name' ), PHP_VERSION 
					)
				);
			}
			
			foreach ( $this->todo_roles as $role ) {
				$wp_roles->add_cap( $role, 'edit_'			. $this->post_type_1 );
				$wp_roles->add_cap( $role, 'edit_'			. $this->post_type_1 . 's' );
				$wp_roles->add_cap( $role, 'edit_others_'	. $this->post_type_1 . 's' );
				$wp_roles->add_cap( $role, 'publish_'		. $this->post_type_1 . 's' );
				$wp_roles->add_cap( $role, 'read_'			. $this->post_type_1 );
				$wp_roles->add_cap( $role, 'read_private_'	. $this->post_type_1 . 's' );
				$wp_roles->add_cap( $role, 'delete_'		. $this->post_type_1 );
				$wp_roles->add_cap( $role, 'manage_'		. $this->taxonomy_type_1 );
			}
			
			foreach ( $this->read_roles as $role ) {
				$wp_roles->add_cap( $role, 'read_' . $this->post_type_1 );
				$wp_roles->add_cap( $role, 'read_' . $this->post_type_1 );
				$wp_roles->add_cap( $role, 'read_' . $this->post_type_1 );
			}
			
			flush_rewrite_rules();
		}
		
		
		/**
		 * On deactivate plugin remove capabilities
		 *
		 * @uses   get_object, remove_cap, read_roles, flush_rules
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		static function on_deactivate () {
			
			$obj = FB_Archive::get_object();
			
			global $wp_roles;
			
			foreach ( $obj->todo_roles as $role ) {
				$wp_roles->remove_cap( $role, 'edit_'			. $obj->post_type_1 );
				$wp_roles->remove_cap( $role, 'edit_'			. $obj->post_type_1 . 's' );
				$wp_roles->remove_cap( $role, 'edit_others_'	. $obj->post_type_1 . 's' );
				$wp_roles->remove_cap( $role, 'publish_'		. $obj->post_type_1 . 's' );
				$wp_roles->remove_cap( $role, 'read_'			. $obj->post_type_1 );
				$wp_roles->remove_cap( $role, 'read_private_'	. $obj->post_type_1 . 's' );
				$wp_roles->remove_cap( $role, 'delete_'			. $obj->post_type_1 );
				$wp_roles->remove_cap( $role, 'manage_'			. $obj->taxonomy_type_1 );
			}
			
			foreach ( $obj->read_roles as $role ) {
				$wp_roles->remove_cap( $role, 'read_' . $obj->post_type );
				$wp_roles->remove_cap( $role, 'read_' . $obj->post_type );
				$wp_roles->remove_cap( $role, 'read_' . $obj->post_type );
			}
			
			flush_rewrite_rules();
		}
		
		
		/**
		 * Disable plugin update notifications
		 * 
		 * @param unknown_type $value
		 * @since 0.0.1
		 * @link http://dd32.id.au/2011/03/01/disable-plugin-update-notification-for-a-specific-plugin-in-wordpress-3-1/
		 * @param array string $value
		 * @return array string $value
		 */
		public function remove_update_nag( $value) {
			
			if ( isset( $value) && is_object( $value) )
				unset( $value->response[ plugin_basename(__FILE__) ] );
			
			return $value;
		}
		
		
		/**
		 * Return post type
		 *
		 * @uses   get_post_type_object, get_post
		 * @access public
		 * @since  0.0.1
		 * @return string $post_type
		 */
		private function get_post_type () {
			
			if ( !function_exists('get_post_type_object' ) )
				return NULL;
				
			if ( isset( $_GET['post']) )
				$post_id = (int) $_GET['post'];
			elseif ( isset( $_POST['post_ID']) )
				$post_id = (int) $_POST['post_ID'];
			else
				$post_id = 0;
			
			$post = NULL;
			$post_type_object = NULL;
			$post_type = NULL;
			if ( $post_id ) {
				$post = get_post( $post_id);
				if ( $post ) {
				$post_type_object = get_post_type_object( $post->post_type );
					if ( $post_type_object ) {
						$post_type = $post->post_type;
						$current_screen->post_type = $post->post_type;
						$current_screen->id = $current_screen->post_type;
					}
				}
			} elseif ( isset( $_POST['post_type']) ) {
				$post_type_object = get_post_type_object( $_POST['post_type'] );
				if ( $post_type_object ) {
					$post_type = $post_type_object->name;
					$current_screen->post_type = $post_type;
					$current_screen->id = $current_screen->post_type;
				}
			} elseif ( isset( $_SERVER['QUERY_STRING'] ) ) {
				$post_type = esc_attr( $_SERVER['QUERY_STRING'] );
				$post_type = str_replace( 'post_type=', '', $post_type );
			}
			
			return $post_type;
		}
		
		
		/**
		 * On admin init
		 *
		 * @uses   wp_register_style, wp_enqueue_style, add_action, add_filter, add_meta_box
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function on_admin_init () {
			
			$post_type = $this->get_post_type();
			
			wp_register_style( 'archive-page', plugins_url( 'css/settings.css', __FILE__ ) );
			wp_register_style( 'archive-structure-page', plugins_url( 'css/structures.css', __FILE__ ) );
			wp_register_style( 'archive-menu', plugins_url( 'css/menu.css', __FILE__ ) );
			wp_enqueue_style( 'archive-menu' );
			
			add_filter( 'post_row_actions', array( $this, 'add_archive_link' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'add_archive_link' ), 10, 2 );
			add_action( 'admin_action_archive', array( $this, 'archive_post_type' ) );
			add_action( 'admin_notices', array( $this, 'get_admin_notices' ) );
			// modify bulk actions - current not possible in WP
			//add_filter( 'bulk_actions-edit-' . $this->post_type_1, array( $this, 'filter_bulk_actions' ) );
			
			add_filter( 'post_row_actions', array( $this, 'add_unset_archive_link' ), 10, 2 );
			add_action( 'admin_action_unset_archive', array( $this, 'unset_archive_post_type' ) );
			
			$this->add_value_to_row();
			
			if ( $this->post_type_1 == $post_type ) {
				// add meta box with ID
				add_meta_box( 'id',
					__( 'Archive Info', self::$textdomain ),
					array( &$this, 'additional_meta_box' ),
					$this->post_type_1, 'side', 'high'
				);
			}
			
			$defined_pages = array( 
				'archiv&amp;page=archive_settings_group&amp;settings-updated=true', 
				'archiv&amp;page=archive_settings_group', 
				$this -> post_type_1 
			);
			if ( in_array( $post_type, $defined_pages ) )
				wp_enqueue_style( 'archive-page' );
			elseif ( 'taxonomy=' . $this->taxonomy_type_1 . '&amp;' . $this->post_type_1 == $post_type )
				wp_enqueue_style( 'archive-structure-page' );
		}
		
		
		/**
		 * Enqueue scripts in WP
		 * 
		 * @uses wp_enqueue_script
		 * @access public
		 * @since 0.0.1
		 * @param unknown_type $pagehook
		 * @return void
		 */
		public function enqueue_script( $pagehook ) {
			
			if ( defined('WP_DEBUG') && WP_DEBUG && isset( $_GET['debug']) && $_GET['debug'] === 'true' )
				echo '<br><br>Pagehook: <code>' . $pagehook .$post_type . '</code>';
			
			$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
			
			$archive_pages = array( 'edit.php' );
			$archive_post_type = array( $this->post_type_1, $this->post_type_1 . '&debug =true' );
			
			if ( in_array( $pagehook, $archive_pages ) && in_array( $this->get_post_type(), $archive_post_type ) ) {
				wp_enqueue_script( 
					'jquery-archive-script', 
					WP_PLUGIN_URL . '/' . dirname( plugin_basename(__FILE__) ) . '/js/script' . $suffix. '.js', 
					array( 'jquery' )
				);
			}
		}
		
		
		/**
		 * Schedule check
		 *
		 * @uses   wp_schedule_event, wp_next_scheduled
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function schedule_archived_check () {
			
			if ( ! wp_next_scheduled('scheduled_archiving') && (bool) $this->scheduled_archiving ) {
				wp_schedule_event( time(), 'twicedaily', 'scheduled_archiving' ); // hourly, daily and twicedaily
			} elseif ( ! (bool) $this->scheduled_archiving && wp_next_scheduled('scheduled_archiving') ) {
				wp_clear_scheduled_hook( 'scheduled_archiving' );
			}
		}
		
		
		/**
		 * Add link on archive
		 *
		 * @uses   get_post_type_object, get_archive_post_link, current_user_can, esc_attr
		 * @access public
		 * @since  0.0.1
		 * @param  array string $actions
		 * @param  integer $id
		 * @return array $actions
		 */
		public function add_archive_link( $actions, $id ) {
			global $post, $current_screen, $mode;
			
			$post_type_object = get_post_type_object( $post->post_type );
			//var_dump( $current_screen);
			if ( is_array( $this->def_archive_screens ) && ! in_array( $current_screen->id, $this->def_archive_screens ) )
				return $actions;
			if ( ! current_user_can( $post_type_object->cap->delete_post, $post->ID ) )
				return $actions;
			
			$actions['archive'] = '<a href="' . $this->get_archive_post_link( $post->ID ) 
				. '" title="'
				. esc_attr( __( 'Move this item to the Archive', self::$textdomain  ) ) 
				. '">' . __( 'Archive', self::$textdomain  ) . '</a>';
			
			return $actions;
		}
		
		
		/**
		 * Add undo-link on archive
		 *
		 * @uses   get_post_type_object, current_user_can, get_post_meta, esc_attr
		 * @access public
		 * @since  0.0.1
		 * @param  array string $actions
		 * @param  integer $id
		 * @return array $actions
		 */
		public function add_unset_archive_link( $actions, $id ) {
			global $post, $current_screen, $mode;
			
			$post_type_object = get_post_type_object( $post->post_type );
			
			if ( in_array( $current_screen->id, $this->def_unset_screens ) 
				 && current_user_can( $post_type_object->cap->delete_post, $post->ID )
				) {
				$archived_post_type = get_post_meta( $id->ID, $this->post_meta_key, TRUE );
				$actions['archive'] = '<a href="' . $this->get_unset_archive_post_link( $post->ID ) 
					. '&on_archive=1" title="'
					. esc_attr( __( 'Move this item to the archived post type', self::$textdomain ) ) 
					. ': ' . $archived_post_type
					. '">' . __( 'Restore to', self::$textdomain  ) . ' <code>' . $archived_post_type . '</code></a>';
			}
			
			return $actions;
		}
		
		
		/**
		 * Return link for archive post type
		 * Use filter get_archive_post_link for custom link
		 *
		 * @uses   get_post, get_post_type_object, current_user_can, apply_filters, admin_url, wp_nonce_url
		 * @access public
		 * @since  0.0.1
		 * @param  integer $id, Default is 0
		 * @return string
		 */
		public function get_archive_post_link( $id = 0 ) {
			
			if ( ! $post = get_post( $id ) )
				return;
			
			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object )
				return;
		
			if ( ! current_user_can( $post_type_object->cap->delete_post, $post->ID ) )
				return;
			
			$action = NULL;
			$archive_link = admin_url( 'admin.php?post=' . $post->ID . '&action=archive' );
			return apply_filters( 
				'get_archive_post_link', 
				wp_nonce_url( $archive_link, "$action-{$post->post_type}_{$post->ID}" ), 
				$post->ID
			);
		}
		
		
		/**
		 * Return link for undo-archive post type
		 * Use filter get_unset_archive_post_link for custom link
		 *
		 * @uses   get_post, get_post_type_object, current_user_can, apply_filters, admin_url, wp_nonce_url
		 * @access public
		 * @since  0.0.1
		 * @param  integer $id, Default is 0
		 * @return string
		 */
		public function get_unset_archive_post_link( $id = 0 ) {
			
			if ( ! $post = get_post( $id ) )
				return;
		
			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object )
				return;
			
			if ( ! current_user_can( $post_type_object->cap->delete_post, $post->ID ) )
				return;
			
			$action = NULL;
			$archive_link = admin_url( 'admin.php?post=' . $post->ID . '&action=unset_archive' );
			$archived_post_type = get_post_meta( $id, $this->post_meta_key, TRUE );
			return apply_filters( 
				'get_unset_archive_post_link', 
				wp_nonce_url( $archive_link, "$action-{$archived_post_type}_{$post->ID}" ), 
				$post->ID
			);
		}
		
		
		public function filter_bulk_actions( $actions ) {
			
			$actions['restore_archive'] = __( 'Restore to Post Type', self::$textdomain );
			return $actions;
		}
		
		
		/**
		 * Archive post type
		 *
		 * @uses   wp_die, set_post_type, add_post_meta, wp_redirect, admin_url
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function archive_post_type () {
			
			if ( ! (
				isset( $_GET['post']) || 
				( isset( $_REQUEST['action']) && 'archive' == $_REQUEST['action'] ) 
			) ) {
				wp_die( __( 'No post to archive has been supplied!', self::$textdomain ) );
			}
			
			$id = (int) ( isset( $_GET['post']) ? $_GET['post'] : $_REQUEST['post']);
			
			if ( $id ) {
				$redirect_post_type = '';
				$archived_post_type = get_post_type( $id );
				if ( ! empty( $archived_post_type ) )
					$redirect_post_type = 'post_type=' . $archived_post_type . '&';
				// change post type
				set_post_type( $id, $this->post_type_1 );
				// add old post_type to post meta
				add_post_meta( $id, $this->post_meta_key, $archived_post_type, TRUE );
				wp_redirect( admin_url( 'edit.php?' . $redirect_post_type . 'archived=1&ids=' . $id ) );
				exit;
			} else {
				wp_die( __( 'Sorry, i cant find the post-id', self::$textdomain ) );
			}
		
		}
		
		
		/**
		 * Undo archive post type
		 *
		 * @uses   wp_die, set_post_type, delete_post_meta, wp_redirect, admin_url
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function unset_archive_post_type () {
			
			if ( ! (
				isset( $_GET['post']) || 
				( isset( $_REQUEST['action']) && 'unset_archive' == $_REQUEST['action'] ) 
			) ) {
				wp_die( __('No item to undo archive has been supplied!', self::$textdomain ) );
			}
			
			$id = (int) ( isset( $_GET['post']) ? $_GET['post'] : $_REQUEST['post']);
			
			if ( $id ) {
				$redirect_post_type = '';
				// get archived post type
				$archived_post_type = get_post_meta( $id, $this->post_meta_key, TRUE );
				if ( ! empty( $archived_post_type ) )
					$redirect_post_type = 'post_type=' . $archived_post_type . '&';
				// change post type to archived post type
				set_post_type( $id, $archived_post_type );
				// remove archived post type on post meta
				delete_post_meta( $id, $this->post_meta_key );
				// redirect to edit-page od post type
				wp_redirect( admin_url( 'edit.php?' . $redirect_post_type . 'unset_archived=1&ids=' . $id ) );
				exit;
			} else {
				wp_die( __( 'Sorry, i cant find the post-id', self::$textdomain ) );
			}
		
		}
		
		
		/**
		 * For the scheduled archiving
		 *
		 * @uses   wp_die, set_post_type, delete_post_meta, wp_redirect, admin_url
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function scheduled_archiving () {
			global $wpdb;
			
			$current = get_site_transient( 'archivise_posts' );
			if ( ! is_object( $current) ) {
				$current = new stdClass;
			}
			
			// Update last_checked for current to prevent multiple blocking requests if request hangs
			$current->last_checked = time();
			set_site_transient( 'archivise_posts', $current );
			
			// convert post type array to string
			$scheduled_archiving_post_types = NULL;
			foreach( $this->scheduled_archiving_post_type as $item ){
				$scheduled_archiving_post_types .= "'" . $item . "'" . ', ';
			}
			$scheduled_archiving_post_types = substr( $scheduled_archiving_post_types, 0, -strlen(', ') );
			$archived = $wpdb->get_results(
				"SELECT ID
				 FROM $wpdb->posts
				 WHERE post_type IN ( $scheduled_archiving_post_types)
				 AND post_date < '" . date( 'Y-m-d', strtotime('-' . (int) $this->scheduled_archiving_days .' days') ) . "'",
				 ARRAY_A
			);
			//var_dump( $archived);exit;
			if ( is_wp_error( $archived ) )
				return FALSE;
			
			if ( ! $archived )
				return FALSE;
			
			foreach ( $archived as $value ) {
				if ( $value['ID'] ) {
					$archived_post_type = get_post_type( $value['ID'] );
					// change post type
					set_post_type( $value['ID'], $this->post_type_1 );
					// add old post_type to post meta
					add_post_meta( $value['ID'], $this->post_meta_key, $archived_post_type, TRUE );
				}
			}
			
			$updates = new stdClass();
			$updates->last_checked = time();
			set_site_transient( 'archivise_posts', $updates );
		}
		
		
		/**
		 * Add Messages for admin notice
		 * 
		 * @uses   number_format_i18n, get_post_type, add_settings_error
		 * @access public
		 * @since  0.0.2
		 * @return void
		 */
		public function add_settings_error () {
			
			$message_archived = NULL;
			$message_unset_archived = NULL;
			
			if ( isset( $_REQUEST['archived'] ) ) {
				$message_archived = sprintf( 
					_n( 'Item moved to the Archive.', 
					'%s items moved to the Archive.', 
					$_REQUEST['archived'], 
					'', self::$textdomain ), 
					number_format_i18n( $_REQUEST['archived'] ) 
				);
				$ids = isset( $_REQUEST['ids']) ? $_REQUEST['ids'] : 0;
				$message_archived .= ' <a href="' . $this->get_unset_archive_post_link( $ids) . '">' . __( 'Undo' ) . '</a>';
			}
			
			if ( isset( $_REQUEST['unset_archived'] ) ) {
				$message_unset_archived = sprintf( 
					_n( 'Item moved to the Post Type: %2$s.', 
					'%1$s items moved to the Post Types: %2$s.', 
					$_REQUEST['unset_archived'], 
					'', self::$textdomain ), 
					number_format_i18n( $_REQUEST['unset_archived'] ),
					'<code>' . get_post_type( $_REQUEST['ids'] ) . '</code>'
				);
			}
			
			if ( isset( $_REQUEST['archived'] ) && (int) $_REQUEST['archived'] ) {
				add_settings_error( 
					'archived_message',
					'archived',
					$message_archived,
					'updated'
				);
			}
			
			if ( isset( $_REQUEST['unset_archived'] ) && (int) $_REQUEST['unset_archived'] ) {
				add_settings_error( 
					'unset_archived_message',
					'unset_archived',
					$message_unset_archived,
					'updated'
				);
			}
		}
		
		
		/**
		 * Return Admin Notice for inform about actions
		 *
		 * @uses   settings_errors
		 * @access public
		 * @since  0.0.1
		 * @return string
		 */
		public function get_admin_notices () {
			
			settings_errors( 'archived_message' );
			settings_errors( 'unset_archived_message' );
		}
		
		/**
		 * Admin post meta contents
		 * 
		 * @uses   get_post_meta
		 * @ccess  public
		 * @since  0.0.1
		 * @param  array $data
		 * @return string markup with post-id
		 */
		public function additional_meta_box( $data ) {
				
			if ( $data->ID ) {
				echo '<p>' . __( 'ID of this archived item:', self::$textdomain ) 
					. ' <code>' . $data->ID . '</code></p>';
				echo '<p>' . __( 'Archived Post Type:', self::$textdomain ) 
					. ' <code>' . get_post_meta( $data->ID, $this->post_meta_key, TRUE ) . '</code></p>';
				
			}
		}
		
		
		/**
		 * Register post type 'snippet'
		 * 
		 * @uses    register_post_type
		 * @access  public
		 * @since   0.0.1
		 * @return  void
		 */
		public function build_post_type () {
			
			// labels for return post type Snippet
			$labels = array(
				'name'               => __( 'Archive', self::$textdomain ),
				'singular_name'      => __( 'Archive', self::$textdomain ),
				'add_new'            => __( 'Add New', self::$textdomain ),
				'add_new_item'       => __( 'Add New Item', self::$textdomain ),
				'edit_item'          => __( 'Edit Item', self::$textdomain ),
				'new_item'           => __( 'New Item in Archive', self::$textdomain ),
				'view_item'          => __( 'View Item', self::$textdomain ),
				'search_items'       => __( 'Search in Archive', self::$textdomain ),
				'not_found'          => __( 'No item found in Archive', self::$textdomain ),
				'not_found_in_trash' => __( 'No item found in Archive-Trash', self::$textdomain ),
				'parent_item_colon'  => __( 'Parent item in Archive', self::$textdomain )
			);
			
			 /**
			 * - label - Name of the post type shown in the menu. Usually plural. If not set, labels['name'] will be used.
			 * - description - A short descriptive summary of what the post type is. Defaults to blank.
			 * - public - Whether posts of this type should be shown in the admin UI. Defaults to false.
			 * - exclude_from_search - Whether to exclude posts with this post type from search results. Defaults to true if the type is not public, false if the type is public.
			 * - publicly_queryable - Whether post_type queries can be performed from the front page.  Defaults to whatever public is set as.
			 * - show_ui - Whether to generate a default UI for managing this post type. Defaults to true if the type is public, false if the type is not public.
			 * - menu_position - The position in the menu order the post type should appear. Defaults to the bottom.
			 * - menu_icon - The url to the icon to be used for this menu. Defaults to use the posts icon.
			 * - capability_type - The post type to use for checking read, edit, and delete capabilities. Defaults to "post".
			 * - capabilities - Array of capabilities for this post type. You can see accepted values in {@link get_post_type_capabilities()}. By default the capability_type is used to construct capabilities.
			 * - hierarchical - Whether the post type is hierarchical. Defaults to false.
			 * - supports - An alias for calling add_post_type_support() directly. See add_post_type_support() for Documentation. Defaults to none.
			 * - register_meta_box_cb - Provide a callback function that will be called when setting up the meta boxes for the edit form.  Do remove_meta_box() and add_meta_box() calls in the callback.
			 * - taxonomies - An array of taxonomy identifiers that will be registered for the post type.  Default is no taxonomies. Taxonomies can be registered later with register_taxonomy() or register_taxonomy_for_object_type().
			 * - labels - An array of labels for this post type. You can see accepted values in {@link get_post_type_labels()}. By default post labels are used for non-hierarchical types and page labels for hierarchical ones.
			 * - permalink_epmask - The default rewrite endpoint bitmasks.
			 * - rewrite - false to prevent rewrite, or array('slug'=>$slug) to customize permastruct; default will use $post_type as slug.
			 * - query_var - false to prevent queries, or string to value of the query var to use for this post type
			 * - can_export - true allows this post type to be exported.
			 * - show_in_nav_menus - true makes this post type available for selection in navigation menus.
			 * - _builtin - true if this post type is a native or "built-in" post_type.  THIS IS FOR INTERNAL USE ONLY!
			 * - _edit_link - URL segement to use for edit link of this post type.  Set to 'post.php?post=%d'.  THIS IS FOR INTERNAL USE ONLY!
			 */
			$capabilities = array(
				'edit_post'          => 'edit_' . $this->post_type_1,
				'edit_posts'         => 'edit_' . $this->post_type_1 . 's',
				'edit_others_posts'  => 'edit_others_' . $this->post_type_1 . 's',
				'publish_posts'      => 'publish_' . $this->post_type_1 . 's',
				'read_post'          => 'read_' . $this->post_type_1,
				'read_private_posts' => 'read_private_' . $this->post_type_1 . 's',
				'delete_post'        => 'delete_' . $this->post_type_1
			);
			
			$args = array(
				'labels'             => $labels,
				'description'        => __( 'Archive post, pages and other post types to a Archive.', self::$textdomain ),
				'public'             => TRUE,
				'publicly_queryable' => TRUE, 
				'exclude_from_search'=> TRUE, 
				'show_in_nav_menus'  => FALSE,
				'menu_position'      => 22,
				'capabilities'       => $capabilities,
				'supports'           => array( 
					'title', 'editor', 'comments',
					'revisions', 'trackbacks', 'author',
					'excerpt', 'page-attributes', 
					'thumbnail', 'custom-fields',
					'post-formats', 'page-attributes'
				),
				'taxonomies'         => array( 'category','post_tag' , $this->taxonomy_type_1),
				'has_archive'        => TRUE
			);
			
			register_post_type( $this->post_type_1, $args );
		}
		
		
		/**
		 * Remove entry in submenu to add enw archive post type
		 * 
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public function remove_menu_entry () {
			global $submenu;
			
			unset( $submenu['edit.php?post_type=archiv'][10] );
			unset( $submenu['edit.php?post_type=archiv'][15] );
			unset( $submenu['edit.php?post_type=archiv'][16] );
		}
		
		
		/**
		 * Retunr taxonmoie strings
		 * 
		 * @uses    get_object_term_cache, wp_cache_add, wp_get_object_terms, _make_cat_compat
		 * @access  public
		 * @since   0.0.1
		 * @param   string $taxonomy key
		 * @param   integer $id, Default is FALSE
		 * @return  array string $categories
		 */
		public function get_the_taxonomy( $taxonomy, $id = FALSE ) {
			global $post;
		
			$id = (int) $id;
			if ( !$id )
				$id = (int) $post->ID;
		
			$categories = get_object_term_cache( $id, $taxonomy );
			if ( FALSE === $categories ) {
				$categories = wp_get_object_terms( $id, $taxonomy );
				wp_cache_add( $id, $categories, $taxonomy . '_relationships' );
			}
		
			if ( !empty( $categories ) )
				usort( $categories, '_usort_terms_by_name' );
			else
				$categories = array();
		
			foreach ( (array) array_keys( $categories ) as $key ) {
				_make_cat_compat( $categories[$key] );
			}
		
			return $categories;
		}
		
		
		/**
		 * Add raw in table od custom post type 'snippet'
		 *
		 * @uses    array_insert
		 * @access  public
		 * @since   0.0.1
		 * @param   string $columns
		 * @return  array string $columns
		 */
		public function add_columns( $columns) {
			// add id list
			$columns['aid'] = __( 'ID', self::$textdomain );
			/*
			// remove author list
			//unset( $columns['author']);
			// add structure tax
			$this->array_insert( $columns, 
				2, 
				array( $this->taxonomy_type_1 => __( 'Structures', self::$textdomain ) )
			); */
			
			return $columns;
		}
		
		
		/**
		 * Return content of new raw in the table
		 * 
		 * @uses   get_the_term_list
		 * @acces  public
		 * @since  0.0.1
		 * @param  string $column_name
		 * @param  interer $id
		 * @return integer $id
		 */
		public function return_custom_columns( $column_name, $id) {
			
			$id = (int) $id;
			
			switch( $column_name ) {
				case $this->taxonomy_type_1:
					$structure = '';
					$taxonomys = get_the_term_list( $id, $this->taxonomy_type_1, '', ', ', '' );
					if ( isset( $taxonomys[0]) )
						$structure = $taxonomys;
					else
						$structure = __( 'No', self::$textdomain ) . $this->taxonomy_type_1;
					$value = $structure;
					break;
				case 'aid':
					$value = $id;
					break;
			}
			
			if ( isset( $value) )
				echo $value;
		}
		
		
		/**
		* Turn strings into boolean values.
		*
		* This is needed because user input when using shortcodes
		* is automatically turned into a string. So, we'll take those
		* values and convert them.
		*
		* Taken from Justin Tadlock’s Plugin “Template Tag Shortcodes”
		* @see http://justintadlock.com/?p=1539
		* @author Justin Tadlock
		* @param string $value String to convert to a boolean.
		* @return bool|string
		*/
		public static function string_to_bool( $value ) {
			if ( is_numeric( $value ) ) {
				return '0' == $value ? FALSE : TRUE;
			}
			
			// Neither 'true' nor 'false' nor 'null'
			if ( ! isset ( $value[3] ) ) {
				return $value;
			}
			
			$lower = strtolower( $value );
			
			if ( 'true' == $lower ) {
				return TRUE;
			}
			
			if ( ( 'false' == $lower ) or ( 'null' == $lower ) ) {
				return FALSE;
			}
			
			return $value;
		}
		
		
		/**
		 * hook inside the rows of wp
		 *
		 * @uses   get_post_type_object
		 * @access public
		 * @since  0.0.1
		 * @param  array string $actions
		 * @param  integer $id
		 * @return array $actions
		 */
		public function add_value_to_row () {
			
			// on screen: edit-snippets
			add_action( 'manage_edit-' . $this->post_type_1 . '_columns', array( &$this, 'add_columns' ) );
			add_filter( 'manage_posts_custom_column', array( &$this, 'return_custom_columns' ), 10, 3 );
		}
		
		
		/**
		 * Insert array on position
		 * 
		 * @uses   
		 * @since  0.0.1
		 * @param  $array
		 * @param  $position
		 * @param  $insert_array
		 * @return void
		 */
		public function array_insert( &$array, $position, $insert_array ) {
			
			$first_array = array_splice( $array, 0, $position );
			$array = array_merge( $first_array, $insert_array, $array );
		}
		
		
		/**
		 * Get the help
		 * 
		 * @uses   
		 * @since  0.0.1
		 * @param  $array
		 * @param  $position
		 * @param  $insert_array
		 * @return string $contextual_help
		 */
		public function add_help_text( $contextual_help, $screen_id, $screen ) {
			
			if ( ! isset( $screen->post_type ) || $this->post_type_1 !== $screen->post_type )
				return $contextual_help;
			
			$contextual_help = 
				'<p>' . 
				__( 'Archive - maybe later an help for this plugin', self::$textdomain ) . 
				'</p>' . "\n";
			
			return $contextual_help;
		}
		
		
		/**
		 * Add to Archive to qury
		 * 
		 * @uses   query_vars, is_admin, is_preview
		 * @since  0.0.4
		 * @param  array  $query
		 * @return string $query
		 */
		public function add_to_query( $query ) {
		
			if ( is_admin() || is_preview() )
				return;
			
			if ( ! isset( $query -> query_vars['suppress_filters'] ) || FALSE == $query -> query_vars['suppress_filters'] )
				$query -> set( 'post_type', array( 'post', $this -> post_type_1 ) );
			
			return $query;
		}
		
		
		/**
		 * Add shortcode, example: [snippet id=12]
		 * 
		 * @uses   shortcode_atts
		 * @since  0.0.1
		 * @param  array  $atts
		 * @param  string $content
		 * @return string | array $archived_posts
		 */
		public function add_shortcode( $atts, $content = NULL ) {
			global $wpdb;
			
			extract( 
				shortcode_atts( array(
					'count'         => -1, // count or -1 for all posts
					'post_status'   => 'publish', // status or all for all posts
					'echo'          => TRUE, // echo or give an array for use external
					'return_markup' => 'ul', // markup before echo title, content
					'title_markup'  => 'li', // markup before item
					'content'       => FALSE, // view also content?
					'debug'         => FALSE // debug mor vor view an array
				), $atts
			) );
			
			if ( ! is_numeric($count) )
				$message = wp_sprintf( __( 'The Snippet %s is non integer value or the title of this Snippet!', self::$textdomain ), esc_html($id) );
			
			if ( ! empty($message) && current_user_can('read') ) {
				$message = '<div id="message" class="error fade" style="background:red;"><p>' . $message . '</p></div>';
				add_action( 'wp_footer', create_function( '', "echo '$message';" ) );
			}
			
			$args = array(
				'post_type' => $this -> post_type_1,
				'post_status' => $post_status,
				'posts_per_page' => $count
			);
			
			$archived_posts = '';
			
			$posts = new WP_Query( $args );
			if ( $posts -> have_posts() ) {
				
				while ( $posts -> have_posts() ) {
						$posts -> the_post();
						$post_id  = get_the_ID();
					if ( $echo ) {
						$archived_posts .= '<' . $title_markup . '><a href="' . 
						get_permalink($post_id) . '" title="' . get_the_title() . '" >' . 
						get_the_title() . '</a>';
						if ( $content )
							$archived_posts .= apply_filters( 'the_content', get_the_content() );
						$archived_posts .= '</' . $title_markup . '>';
					} else {
						(array) $archived_post = new stdClass();
						$archived_post -> post_id   = $post_id;
						$archived_post -> title     = get_the_title();
						$archived_post -> permalink = get_permalink($post_id);
						$archived_post -> content   = apply_filters( 'the_content', get_the_content() );
						
						$archived_posts[] = $archived_post;
					}
				}
				
			}
			
			wp_reset_query();
			
			$archived_posts = '<' . $return_markup . '>' . $archived_posts . '</' . $return_markup . '>';
			$archived_posts = apply_filters( 'fb_get_archive', $archived_posts ) ;
			
			if ($debug)
				var_dump( $archived_posts );
			else
				return $archived_posts;
		}
		
		
	} // end class
	
	/**
	 * init class with plugin
	 */
	$fb_archive = new FB_Archive();
	
} // end if class
