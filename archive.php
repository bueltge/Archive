<?php
/**
 * Plugin Name: Archive
 * Description: Archive your post types, also with cron.
 * Version:     1.0.1
 * Plugin URI:  https://github.com/bueltge/Archive
 * Text Domain: archive
 * Domain Path: /languages
 * Author:      Frank Bültge
 * Author URI:  http://bueltge.de/
 * Licence:     GPLv2+
 * License URI: ./license.txt
 *
 * Php Version 5.3
 *
 * @package WordPress
 * @author  Frank Bültge <f.bueltge@inpsyde.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version 2015-03-12
 */

/**
 * Don't call this file directly.
 */
defined( 'ABSPATH' ) || die();

add_action( 'plugins_loaded', array( 'FB_Archive', 'get_object' ) );
register_activation_hook( __FILE__, array( 'FB_Archive', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'FB_Archive', 'on_deactivate' ) );
register_uninstall_hook( __FILE__, array( 'FB_Archive', 'on_deactivate' ) );

/**
 * Class FB_Archive
 */
class FB_Archive {

	static protected $classobj = NULL;

	/*
	 * Key for textdomain
	 *
	 * @var string
	 */
	static public $textdomain = 'archive';

	/*
	 * Key for custom post type
	 *
	 * @var string
	 */
	static public $post_type_1 = 'archiv';

	/*
	 * Key for custom taxonomy
	 *
	 * @var string
	 */
	static public $taxonomy_type_1 = 'archive_structure';

	/**
	 * Roles for edit, create custom post type
	 *
	 * @var array
	 */
	static public $todo_roles = array(
		'administrator',
		'editor',
	);

	/**
	 * Roles to read post type
	 *
	 * @var array
	 */
	static public $read_roles = array(
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
	);

	/**
	 * Key for save the original post type
	 *
	 * @var string
	 */
	public $post_meta_key = '_archived_post_type'; // use underline for don't see in custom fields

	/**
	 * Keys for view archive-link on defined screens
	 * Add Screen Id or not an array for view link on all screens
	 *
	 * @see http://codex.wordpress.org/Plugin_API/Admin_Screen_Reference
	 * @var array
	 */
	public $def_archive_screens = array( 'edit-post', 'edit-page' );

	/**
	 * Keys for view undo-archive-link on defined screens
	 *
	 * @var array
	 */
	public $def_unset_screens = array( 'edit-archiv' );

	/**
	 * Key for active Scheduling posts
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
	 * points the class
	 *
	 * @access public
	 * @since  0.0.1
	 * @return object
	 */
	public static function get_object() {

		if ( NULL === self::$classobj ) {
			self::$classobj = new self;
		}

		return self::$classobj;
	}

	/**
	 * construct
	 *
	 * @uses   add_filter, add_action, localize_plugin, register_activation_hook, register_uninstall_hook
	 * @access public
	 * @since  0.0.1
	 * @return \FB_Archive
	 */
	public function __construct() {

		// include settings on profile
		//require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'inc/class.settings.php';
		//$fb_archive_settings = FB_Archive_Settings :: get_object();

		// load language file
		$this->localize_plugin();

		// add post type
		add_action( 'init', array( $this, 'build_post_type' ) );

		// add scheduled archive
		add_action( 'init', array( $this, 'schedule_archived_check' ) );
		if ( (bool) $this->scheduled_archiving ) {
			add_action( 'scheduled_archiving', array( $this, 'scheduled_archiving' ) );
		}

		add_action( 'admin_init', array( $this, 'add_settings_error' ) );

		// admin interface init
		add_action( 'admin_init', array( &$this, 'on_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'remove_menu_entry' ) );
		// include js
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ), 10, 1 );

		// help on snippet-pages in next version
		add_action( 'contextual_help', array( &$this, 'add_help_text' ), 10, 3 );

		// add to query loop
		if ( $this->add_to_query ) {
			add_action( 'pre_get_posts', array( $this, 'add_to_query' ) );
		}
		// add shortcode for list items of archive
		add_shortcode( 'archive', array( $this, 'add_shortcode' ) );
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
	 * localize_plugin function.
	 *
	 * @uses   load_plugin_textdomain, plugin_basename
	 * @access public
	 * @since  0.0.1
	 * @return void
	 */
	public function localize_plugin() {

		load_plugin_textdomain(
			self::$textdomain,
			FALSE,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * return plugin comment data
	 *
	 * @uses   get_plugin_data
	 * @access public
	 * @since  0.0.1
	 *
	 * @param  $value string, default = 'Version'
	 *                Name, PluginURI, Version, Description, Author, AuthorURI, TextDomain, DomainPath, Network, Title
	 *
	 * @return string
	 */
	public static function get_plugin_data( $value = 'Version' ) {

		static $plugin_data = array();

		// fetch the data just once.
		if ( isset( $plugin_data[ $value ] ) ) {

			return $plugin_data[ $value ];
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		$plugin_data = get_plugin_data( __FILE__ );
		$plugin_data = empty( $plugin_data[ $value ] ) ? '' : $plugin_data[ $value ];

		return $plugin_data;
	}

	/**
	 * On activate plugin
	 *
	 * @uses   deactivate_plugins, get_plugin_data, wp_sprintf, flush_rules
	 * @access public
	 * @since  0.0.1
	 * @return void
	 */
	static public function on_activate() {

		global $wp_roles, $wp_version;

		// check wp version
		if ( ! version_compare( $wp_version, '3.0', '>=' ) ) {
			deactivate_plugins( __FILE__ );
			die(
			wp_sprintf(
				'<strong>%s:</strong> ' .
				esc_attr__( 'Sorry, This plugin requires WordPress 3.0+', self::$textdomain )
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
				esc_attr__(
					'Sorry, This plugin has taken a bold step in requiring PHP 5.0+, Your server is currently running PHP %2s, Please bug your host to upgrade to a recent version of PHP which is less bug-prone. At last count, <strong>over 80%% of WordPress installs are using PHP 5.2+</strong>.',
					self::$textdomain
				)
				, self::get_plugin_data( 'Name' ), PHP_VERSION
			)
			);
		}

		foreach ( self::$todo_roles as $role ) {
			$wp_roles->add_cap( $role, 'edit_' . self::$post_type_1 );
			$wp_roles->add_cap( $role, 'read_' . self::$post_type_1 );
			$wp_roles->add_cap( $role, 'delete_' . self::$post_type_1 );
			$wp_roles->add_cap( $role, 'edit_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'edit_others_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'publish_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'read_private_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'delete_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'delete_private_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'delete_published_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'delete_others_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'edit_private_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'edit_published_' . self::$post_type_1 . 's' );
			$wp_roles->add_cap( $role, 'manage_' . self::$taxonomy_type_1 );
			$wp_roles->add_cap( $role, 'edit_' . self::$taxonomy_type_1 );
			$wp_roles->add_cap( $role, 'delete_' . self::$taxonomy_type_1 );
			$wp_roles->add_cap( $role, 'assign_' . self::$taxonomy_type_1 );
		}

		foreach ( self::$read_roles as $role ) {
			$wp_roles->add_cap( $role, 'read_' . self::$post_type_1 );
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
	static public function on_deactivate() {

		global $wp_roles;

		foreach ( self::$todo_roles as $role ) {
			$wp_roles->remove_cap( $role, 'edit_' . self::$post_type_1 );
			$wp_roles->remove_cap( $role, 'read_' . self::$post_type_1 );
			$wp_roles->remove_cap( $role, 'delete_' . self::$post_type_1 );
			$wp_roles->remove_cap( $role, 'edit_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'edit_others_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'publish_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'read_private_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'delete_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'delete_private_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'delete_published_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'delete_others_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'edit_private_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'edit_published_' . self::$post_type_1 . 's' );
			$wp_roles->remove_cap( $role, 'manage_' . self::$taxonomy_type_1 );
			$wp_roles->remove_cap( $role, 'edit_' . self::$taxonomy_type_1 );
			$wp_roles->remove_cap( $role, 'delete_' . self::$taxonomy_type_1 );
			$wp_roles->remove_cap( $role, 'assign_' . self::$taxonomy_type_1 );
		}

		foreach ( self::$read_roles as $role ) {
			$wp_roles->remove_cap( $role, 'read_' . self::$post_type_1 );
		}

		flush_rewrite_rules();
	}

	/**
	 * On admin init
	 *
	 * @uses   wp_register_style, wp_enqueue_style, add_action, add_filter, add_meta_box
	 * @access public
	 * @since  0.0.1
	 * @return void
	 */
	public function on_admin_init() {

		add_filter( 'post_row_actions', array( $this, 'add_archive_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_archive_link' ), 10, 2 );
		add_action( 'admin_action_archive', array( $this, 'archive_post_type' ) );
		add_action( 'admin_notices', array( $this, 'get_admin_notices' ) );
		// modify bulk actions - current not possible in WP
		//add_filter( 'bulk_actions-edit-' . self::$post_type_1, array( $this, 'filter_bulk_actions' ) );

		add_filter( 'post_row_actions', array( $this, 'add_unset_archive_link' ), 10, 2 );
		add_action( 'admin_action_unset_archive', array( $this, 'unset_archive_post_type' ) );

		$this->add_value_to_row();

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		add_action( 'admin_head-edit.php', array( $this, 'add_custom_style' ) );
	}

	/**
	 * Enqueue scripts in WP
	 *
	 * @uses   wp_enqueue_script
	 * @access public
	 * @since  0.0.1
	 *
	 * @param  $pagehook
	 *
	 * @return void
	 */
	public function enqueue_script( $pagehook ) {

		if ( 'edit.php' !== $pagehook ) {
			return NULL;
		}

		$screen = get_current_screen();
		if ( self::$post_type_1 !== $screen->post_type ) {
			return NULL;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';

		wp_register_script(
			'jquery-archive-script',
			WP_PLUGIN_URL . '/' . dirname( plugin_basename( __FILE__ ) ) . '/js/script' . $suffix . '.js',
			array( 'jquery' )
		);
		wp_enqueue_script( 'jquery-archive-script' );

	}

	/**
	 * Add meta box to post type Archive with information about ID and old post type
	 *
	 * @since  2015-01-16
	 * @return void
	 */
	public function add_meta_boxes() {

		// add meta box with ID
		add_meta_box(
			'id',
			esc_attr__( 'Archive Info', self::$textdomain ),
			array( $this, 'additional_meta_box' ),
			self::$post_type_1,
			'side', 'high'
		);
	}

	/**
	 * Add style value in head for ID column
	 *
	 * @since  2015-03-12
	 * @return null
	 */
	public function add_custom_style() {

		$screen = get_current_screen();

		if ( 'edit-archiv' !== $screen->id )
			return NULL;

		$style = '<style type="text/css">#aid { width: 10%; }</style>';
		echo $style;
	}
	/**
	 * Schedule check
	 *
	 * @uses   wp_schedule_event, wp_next_scheduled
	 * @access public
	 * @since  0.0.1
	 * @return void
	 */
	public function schedule_archived_check() {

		if ( ! wp_next_scheduled( 'scheduled_archiving' ) && (bool) $this->scheduled_archiving ) {
			wp_schedule_event( time(), 'twicedaily', 'scheduled_archiving' ); // hourly, daily and twicedaily
		} elseif ( ! (bool) $this->scheduled_archiving && wp_next_scheduled( 'scheduled_archiving' ) ) {
			wp_clear_scheduled_hook( 'scheduled_archiving' );
		}
	}

	/**
	 * Add link on archive
	 *
	 * @uses   get_post_type_object, get_archive_post_link, current_user_can, esc_attr
	 * @access public
	 * @since  0.0.1
	 *
	 * @param          array string $actions
	 * @param  integer $id
	 *
	 * @return array $actions
	 */
	public function add_archive_link( $actions, $id ) {

		$screen = get_current_screen();

		if ( ! isset( $screen->post_type ) ) {
			return $actions;
		}

		// Not enough rights
		$post_type_object = get_post_type_object( $screen->post_type );
		if ( ! current_user_can( $post_type_object->cap->delete_post, $id->ID ) ) {
			return $actions;
		}

		// Not on the right screen
		if ( ! in_array( $screen->id, $this->def_archive_screens ) ) {
			return $actions;
		}

		$actions[ 'archive' ] = '<a href="' . $this->get_archive_post_link( $id->ID )
			. '" title="'
			. esc_attr__( 'Move this item to the Archive', self::$textdomain )
			. '">' . esc_attr__( 'Archive', self::$textdomain ) . '</a>';

		return $actions;
	}

	/**
	 * Add undo-link on archive
	 *
	 * @uses   get_post_type_object, current_user_can, get_post_meta, esc_attr
	 * @access public
	 * @since  0.0.1
	 *
	 * @param          array string $actions
	 * @param  integer $id
	 *
	 * @return array $actions
	 */
	public function add_unset_archive_link( $actions, $id ) {

		$screen = get_current_screen();

		if ( ! isset( $screen->post_type ) ) {
			return $actions;
		}

		// Not enough rights
		$post_type_object = get_post_type_object( $screen->post_type );
		if ( ! current_user_can( $post_type_object->cap->delete_post, $id->ID ) ) {
			return $actions;
		}

		// Not on the right screen
		if ( ! in_array( $screen->id, $this->def_unset_screens ) ) {
			return $actions;
		}

		$archived_post_type   = get_post_meta( $id->ID, $this->post_meta_key, TRUE );
		$actions[ 'archive' ] = '<a href="' . $this->get_unset_archive_post_link( $id->ID )
			. '&on_archive=1" title="'
			. esc_attr__( 'Move this item to the archived post type', self::$textdomain )
			. ': ' . $archived_post_type
			. '">' . esc_attr__( 'Restore to', self::$textdomain ) . ' <code>' . $archived_post_type . '</code></a>';

		return $actions;
	}

	/**
	 * Return link for archive post type
	 * Use filter get_archive_post_link for custom link
	 *
	 * @uses   get_post, get_post_type_object, current_user_can, apply_filters, admin_url, wp_nonce_url
	 * @access public
	 * @since  0.0.1
	 *
	 * @param  integer $id , Default is 0
	 *
	 * @return string
	 */
	public function get_archive_post_link( $id = 0 ) {

		if ( ! $post = get_post( $id ) ) {
			return NULL;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return NULL;
		}

		if ( ! current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) {
			return NULL;
		}

		$action       = NULL;
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
	 *
	 * @param  integer $id , Default is 0
	 *
	 * @return string
	 */
	public function get_unset_archive_post_link( $id = 0 ) {

		if ( ! $post = get_post( $id ) ) {
			return NULL;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return NULL;
		}

		if ( ! current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) {
			return NULL;
		}

		$action             = NULL;
		$archive_link       = admin_url( 'admin.php?post=' . $post->ID . '&action=unset_archive' );
		$archived_post_type = get_post_meta( $id, $this->post_meta_key, TRUE );

		return apply_filters(
			'get_unset_archive_post_link',
			wp_nonce_url( $archive_link, "$action-{$archived_post_type}_{$post->ID}" ),
			$post->ID
		);
	}

	public function filter_bulk_actions( $actions ) {

		$actions[ 'restore_archive' ] = esc_attr__( 'Restore to Post Type', self::$textdomain );

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
	public function archive_post_type() {

		if ( ! (
			isset( $_GET[ 'post' ] ) || ( isset( $_REQUEST[ 'action' ] ) && 'archive' == $_REQUEST[ 'action' ] )
		)
		) {
			wp_die( esc_attr__( 'No post to archive has been supplied!', self::$textdomain ) );
		}

		$id = (int) ( isset( $_GET[ 'post' ] ) ? $_GET[ 'post' ] : $_REQUEST[ 'post' ] );

		if ( $id ) {
			$redirect_post_type = '';
			$archived_post_type = get_post_type( $id );
			if ( ! empty( $archived_post_type ) ) {
				$redirect_post_type = 'post_type=' . $archived_post_type . '&';
			}
			// change post type
			set_post_type( $id, self::$post_type_1 );
			// add old post_type to post meta
			add_post_meta( $id, $this->post_meta_key, $archived_post_type, TRUE );
			wp_redirect( admin_url( 'edit.php?' . $redirect_post_type . 'archived=1&ids=' . $id ) );
			exit;
		} else {
			wp_die( esc_attr__( "Sorry, I can't find the post-id", self::$textdomain ) );
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
	public function unset_archive_post_type() {

		if ( ! (
			isset( $_GET[ 'post' ] ) || ( isset( $_REQUEST[ 'action' ] ) && 'unset_archive' == $_REQUEST[ 'action' ] )
		)
		) {
			wp_die( esc_attr__( 'No item to undo archive has been supplied!', self::$textdomain ) );
		}

		$id = (int) ( isset( $_GET[ 'post' ] ) ? $_GET[ 'post' ] : $_REQUEST[ 'post' ] );

		if ( $id ) {
			$redirect_post_type = '';
			// get archived post type
			$archived_post_type = get_post_meta( $id, $this->post_meta_key, TRUE );
			if ( ! empty( $archived_post_type ) ) {
				$redirect_post_type = 'post_type=' . $archived_post_type . '&';
			}
			// change post type to archived post type
			set_post_type( $id, $archived_post_type );
			// remove archived post type on post meta
			delete_post_meta( $id, $this->post_meta_key );
			// redirect to edit-page od post type
			wp_redirect( admin_url( 'edit.php?' . $redirect_post_type . 'unset_archived=1&ids=' . $id ) );
			exit;
		} else {
			wp_die( esc_attr__( 'Sorry, i cant find the post-id', self::$textdomain ) );
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
	public function scheduled_archiving() {

		global $wpdb;

		$current = get_site_transient( 'archivise_posts' );
		if ( ! is_object( $current ) ) {
			$current = new stdClass;
		}

		// Update last_checked for current to prevent multiple blocking requests if request hangs
		$current->last_checked = time();
		set_site_transient( 'archivise_posts', $current );

		// convert post type array to string
		$scheduled_archiving_post_types = NULL;
		foreach ( $this->scheduled_archiving_post_type as $item ) {
			$scheduled_archiving_post_types .= "'" . $item . "'" . ', ';
		}
		$scheduled_archiving_post_types = substr( $scheduled_archiving_post_types, 0, - strlen( ', ' ) );
		$archived                       = $wpdb->get_results(
			"SELECT ID
				 FROM $wpdb->posts
				 WHERE post_type IN ( $scheduled_archiving_post_types)
				 AND post_date < '" . date(
				'Y-m-d', strtotime( '-' . (int) $this->scheduled_archiving_days . ' days' )
			) . "'",
			ARRAY_A
		);

		if ( is_wp_error( $archived ) ) {
			return NULL;
		}

		if ( ! $archived ) {
			return NULL;
		}

		foreach ( $archived as $value ) {
			if ( $value[ 'ID' ] ) {
				$archived_post_type = get_post_type( $value[ 'ID' ] );
				// change post type
				set_post_type( $value[ 'ID' ], self::$post_type_1 );
				// add old post_type to post meta
				add_post_meta( $value[ 'ID' ], $this->post_meta_key, $archived_post_type, TRUE );
			}
		}

		$updates               = new stdClass();
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
	public function add_settings_error() {

		$message_archived       = NULL;
		$message_unset_archived = NULL;

		if ( isset( $_REQUEST[ 'archived' ] ) ) {
			$message_archived = sprintf(
				_n(
					'Item moved to the Archive.',
					'%s items moved to the Archive.',
					$_REQUEST[ 'archived' ],
					'', self::$textdomain
				),
				number_format_i18n( $_REQUEST[ 'archived' ] )
			);
			$ids              = isset( $_REQUEST[ 'ids' ] ) ? $_REQUEST[ 'ids' ] : 0;
			$message_archived .= ' <a href="' . $this->get_unset_archive_post_link( $ids ) . '">'
				. esc_attr__( 'Undo' ) . '</a>';
		}

		if ( isset( $_REQUEST[ 'unset_archived' ] ) ) {
			$message_unset_archived = sprintf(
				_n(
					'Item moved to the Post Type: %2$s.',
					'%1$s items moved to the Post Types: %2$s.',
					$_REQUEST[ 'unset_archived' ],
					'', self::$textdomain
				),
				number_format_i18n( $_REQUEST[ 'unset_archived' ] ),
				'<code>' . get_post_type( $_REQUEST[ 'ids' ] ) . '</code>'
			);
		}

		if ( isset( $_REQUEST[ 'archived' ] ) && (int) $_REQUEST[ 'archived' ] ) {
			add_settings_error(
				'archived_message',
				'archived',
				$message_archived,
				'updated'
			);
		}

		if ( isset( $_REQUEST[ 'unset_archived' ] ) && (int) $_REQUEST[ 'unset_archived' ] ) {
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
	public function get_admin_notices() {

		settings_errors( 'archived_message' );
		settings_errors( 'unset_archived_message' );
	}

	/**
	 * Admin post meta contents
	 *
	 * @uses   get_post_meta
	 * @access  public
	 * @since  0.0.1
	 *
	 * @param  array $data
	 *
	 * @return string markup with post-id
	 */
	public function additional_meta_box( $data ) {

		if ( $data->ID ) {
			echo '<p>' . esc_attr__( 'ID of this archived item:', self::$textdomain )
				. ' <code>' . $data->ID . '</code></p>';
			echo '<p>' . esc_attr__( 'Archived Post Type:', self::$textdomain )
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
	public function build_post_type() {

		// labels for return post type Snippet
		$labels = array(
			'name'               => esc_attr__( 'Archive', self::$textdomain ),
			'singular_name'      => esc_attr__( 'Archive', self::$textdomain ),
			'add_new'            => esc_attr__( 'Add New', self::$textdomain ),
			'add_new_item'       => esc_attr__( 'Add New Item', self::$textdomain ),
			'edit_item'          => esc_attr__( 'Edit Item', self::$textdomain ),
			'new_item'           => esc_attr__( 'New Item in Archive', self::$textdomain ),
			'view_item'          => esc_attr__( 'View Item', self::$textdomain ),
			'search_items'       => esc_attr__( 'Search in Archive', self::$textdomain ),
			'not_found'          => esc_attr__( 'No item found in Archive', self::$textdomain ),
			'not_found_in_trash' => esc_attr__( 'No item found in Archive-Trash', self::$textdomain ),
			'parent_item_colon'  => esc_attr__( 'Parent item in Archive', self::$textdomain )
		);

		/*
		 * capabilities array
		 *
		 * [edit_post]              => "edit_{$capability_type}"
		 * [read_post]              => "read_{$capability_type}"
		 * [delete_post]            => "delete_{$capability_type}"
		 * [edit_posts]             => "edit_{$capability_type}s"
		 * [edit_others_posts]      => "edit_others_{$capability_type}s"
		 * [publish_posts]          => "publish_{$capability_type}s"
		 * [read_private_posts]     => "read_private_{$capability_type}s"
		 * [delete_posts]           => "delete_{$capability_type}s"
		 * [delete_private_posts]   => "delete_private_{$capability_type}s"
		 * [delete_published_posts] => "delete_published_{$capability_type}s"
		 * [delete_others_posts]    => "delete_others_{$capability_type}s"
		 * [edit_private_posts]     => "edit_private_{$capability_type}s"
		 * [edit_published_posts]   => "edit_published_{$capability_type}s"
		 *
		 */
		$capabilities = array(
			'edit_post'              => 'edit_' . self::$post_type_1,
			'read_post'              => 'read_' . self::$post_type_1,
			'delete_post'            => 'delete_' . self::$post_type_1,
			'edit_posts'             => 'edit_' . self::$post_type_1 . 's',
			'edit_others_posts'      => 'edit_others_' . self::$post_type_1 . 's',
			'publish_posts'          => 'publish_' . self::$post_type_1 . 's',
			'read_private_posts'     => 'read_private_' . self::$post_type_1 . 's',
			'delete_posts'           => 'delete_' . self::$post_type_1 . 's',
			'delete_private_posts'   => 'delete_private_' . self::$post_type_1 . 's',
			'delete_published_posts' => 'delete_published_' . self::$post_type_1 . 's',
			'delete_others_posts'    => 'delete_others_' . self::$post_type_1 . 's',
			'edit_private_posts'     => 'edit_private_' . self::$post_type_1 . 's',
			'edit_published_posts'   => 'edit_published_' . self::$post_type_1 . 's',
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
		$args = array(
			'labels'              => $labels,
			'description'         => esc_attr__(
				'Archive post, pages and other post types to a Archive.', self::$textdomain
			),
			'public'              => TRUE,
			'exclude_from_search' => TRUE,
			'publicly_queryable'  => TRUE,
			'show_in_nav_menus'   => FALSE,
			'menu_position'       => 22,
			'menu_icon'           => 'dashicons-archive',
			//'capability_type'     => 'post',
			'capabilities'        => $capabilities,
			'supports'            => array(
				'title',
				'editor',
				'comments',
				'revisions',
				'trackbacks',
				'author',
				'excerpt',
				'page-attributes',
				'thumbnail',
				'custom-fields',
				'post-formats',
				'page-attributes',
			),
			'taxonomies'          => array( 'category', 'post_tag', self::$taxonomy_type_1 ),
			'has_archive'         => TRUE
		);

		/**
		 * Filter to change the default values to create the custom post type for the Archive
		 */
		$args = apply_filters( 'archive_post_type_arguments', $args );

		register_post_type( self::$post_type_1, $args );
	}

	/**
	 * Remove entry in submenu to add enw archive post type
	 *
	 * @access public
	 * @since  0.0.1
	 * @return void
	 */
	public function remove_menu_entry() {

		global $submenu;

		unset( $submenu[ 'edit.php?post_type=archiv' ][ 10 ] );
		unset( $submenu[ 'edit.php?post_type=archiv' ][ 15 ] );
		unset( $submenu[ 'edit.php?post_type=archiv' ][ 16 ] );
	}

	/**
	 * Retunr taxonmoie strings
	 *
	 * @uses    get_object_term_cache, wp_cache_add, wp_get_object_terms, _make_cat_compat
	 * @access  public
	 * @since   0.0.1
	 *
	 * @param   string   $taxonomy key
	 * @param   bool|int $id       , Default is FALSE
	 *
	 * @return  array string $categories
	 */
	public function get_the_taxonomy( $taxonomy, $id = FALSE ) {

		global $post;

		$id = (int) $id;
		if ( ! $id ) {
			$id = (int) $post->ID;
		}

		$categories = get_object_term_cache( $id, $taxonomy );
		if ( FALSE === $categories ) {
			$categories = wp_get_object_terms( $id, $taxonomy );
			wp_cache_add( $id, $categories, $taxonomy . '_relationships' );
		}

		if ( ! empty( $categories ) ) {
			usort( $categories, '_usort_terms_by_name' );
		} else {
			$categories = array();
		}

		foreach ( (array) array_keys( $categories ) as $key ) {
			_make_cat_compat( $categories[ $key ] );
		}

		return $categories;
	}

	/**
	 * Add raw in table od custom post type 'snippet'
	 *
	 * @uses    array_insert
	 * @access  public
	 * @since   0.0.1
	 *
	 * @param   string $columns
	 *
	 * @return  array string $columns
	 */
	public function add_columns( $columns ) {

		// add id list
		$columns[ 'aid' ] = esc_attr__( 'ID', self::$textdomain );

		/*
		// remove author list
		//unset( $columns['author']);
		// add structure tax
		$this->array_insert( $columns,
			2,
			array( self::$taxonomy_type_1 => esc_attr__( 'Structures', self::$textdomain ) )
		); */

		return $columns;
	}

	/**
	 * Return content of new raw in the table
	 *
	 * @uses    get_the_term_list
	 * @access  public
	 * @since   0.0.1
	 *
	 * @param  string  $column_name
	 * @param  integer $id
	 *
	 * @return integer $id
	 */
	public function return_custom_columns( $column_name, $id ) {

		$id = (int) $id;

		switch ( $column_name ) {
			case self::$taxonomy_type_1:
				$taxonomys = get_the_term_list( $id, self::$taxonomy_type_1, '', ', ', '' );
				if ( isset( $taxonomys[ 0 ] ) ) {
					$structure = $taxonomys;
				} else {
					$structure = esc_attr__( 'No', self::$textdomain ) . self::$taxonomy_type_1;
				}

				$value = $structure;
				break;
			case 'aid':
				$value = $id;
				break;
		}

		if ( isset( $value ) ) {
			echo $value;
		}
	}

	/**
	 * Turn strings into boolean values.
	 *
	 * This is needed because user input when using shortcodes
	 * is automatically turned into a string. So, we'll take those
	 * values and convert them.
	 *
	 * Taken from Justin Tadlock’s Plugin “Template Tag Shortcodes”
	 *
	 * @see    http://justintadlock.com/?p=1539
	 * @author Justin Tadlock
	 *
	 * @param string $value String to convert to a boolean.
	 *
	 * @return bool|string
	 */
	public static function string_to_bool( $value ) {

		if ( is_numeric( $value ) ) {
			return '0' == $value ? FALSE : TRUE;
		}

		// Neither 'true' nor 'false' nor 'null'
		if ( ! isset ( $value[ 3 ] ) ) {
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
	 * @uses     get_post_type_object
	 * @access   public
	 * @since    0.0.1
	 *
	 * @internal param string $array $actions
	 * @internal param int $id
	 *
	 * @return array $actions
	 */
	public function add_value_to_row() {

		// on screen: edit-snippets
		add_action( 'manage_edit-' . self::$post_type_1 . '_columns', array( $this, 'add_columns' ) );
		add_filter( 'manage_posts_custom_column', array( $this, 'return_custom_columns' ), 10, 3 );
	}

	/**
	 * Insert array on position
	 *
	 * @uses
	 * @since  0.0.1
	 *
	 * @param  $array
	 * @param  $position
	 * @param  $insert_array
	 *
	 * @return void
	 */
	public function array_insert( &$array, $position, $insert_array ) {

		$first_array = array_splice( $array, 0, $position );
		$array       = array_merge( $first_array, $insert_array, $array );
	}

	/**
	 * Get the help
	 *
	 * @uses
	 * @since    0.0.1
	 *
	 * @param $contextual_help
	 * @param $screen_id
	 * @param $screen
	 *
	 * @internal param $array
	 * @internal param $position
	 * @internal param $insert_array
	 *
	 * @return string $contextual_help
	 */
	public function add_help_text( $contextual_help, $screen_id, $screen ) {

		if ( ! isset( $screen->post_type ) || self::$post_type_1 !== $screen->post_type ) {
			return $contextual_help;
		}

		$contextual_help =
			'<p>' . esc_attr__( 'Archive your post types, also possible via cron; but only active via variable inside the php-file.', self::$textdomain )
			. '<br>' . esc_attr__( 'Use the shortcode [archive] to list all posts from Archive with status publish to a page or post.', self::$textdomain )
			. '<br>' . esc_attr__( 'The shortcode can use different params and use the follow defaults.', self::$textdomain )
			. '</p><p><pre><code>' . "
'count'         => -1, // count or -1 for all posts
'category'      => '', // Show posts associated with certain categories.
'tag'           => '', // Show posts associated with certain tags.
'post_status'   => 'publish', // status or all for all posts
'echo'          => 'true', // echo or give an array for use external
'return_markup' => 'ul', // markup before echo title, content
'title_markup'  => 'li', // markup before item
'content'       => 'false', // view also content?
'debug'         => 'false' // debug mor vor view an array
" . '</code></pre></p>'
			. '<p>' . esc_attr__( 'An example for use shortcode with params:', self::$textdomain )
			. '<code>[archive count="10" content="true"]</code>'
			. '</p>';

		return $contextual_help;
	}

	/**
	 * Add to Archive to query
	 *
	 * @uses   query_vars, is_admin, is_preview
	 * @since  0.0.4
	 *
	 * @param  array $query
	 *
	 * @return string $query
	 */
	public function add_to_query( $query ) {

		if ( is_admin() || is_preview() ) {
			return NULL;
		}

		if ( ! isset( $query->query_vars[ 'suppress_filters' ] ) || FALSE == $query->query_vars[ 'suppress_filters' ] ) {
			$query->set( 'post_type', array( 'post', self::$post_type_1 ) );
		}

		return $query;
	}

	/**
	 * Add shortcode, example: [archive]
	 *
	 * @uses   shortcode_atts
	 * @since  0.0.1
	 *
	 * @param  array  $atts
	 * @param  string $content
	 *
	 * @return string | array $archived_posts
	 */
	public function add_shortcode( $atts, $content = NULL ) {

		extract(
			$a = shortcode_atts(
				array(
					'count'         => - 1, // count or -1 for all posts
					'category'      => '', // Show posts associated with certain categories.
					'tag'           => '', // Show posts associated with certain tags.
					'post_status'   => 'publish', // status or all for all posts
					'echo'          => 'true', // echo or give an array for use external
					'return_markup' => 'ul', // markup before echo title, content
					'title_markup'  => 'li', // markup before item
					'content'       => 'false', // view also content?
					'debug'         => 'false', // debug mor vor view an array
				), $atts
			)
		);

		$args = array(
			'post_type'      => self::$post_type_1,
			'post_status'    => $a[ 'post_status' ],
			'posts_per_page' => $a[ 'count' ],
			'cat'            => $a[ 'category' ],
			'tag'            => $a[ 'tag' ],
		);

		$archived_posts = '';

		$posts = new WP_Query( $args );
		if ( $posts->have_posts() ) {

			while ( $posts->have_posts() ) {

				$posts->the_post();
				$post_id = get_the_ID();

				if ( 'true' === $a[ 'echo' ] ) {
					$archived_posts .= '<' . $a[ 'title_markup' ] . '><a href="' .
						get_permalink( $post_id ) . '" title="' . get_the_title() . '" >' .
						get_the_title() . '</a>';
					if ( 'true' === $a[ 'content' ] ) {
						$archived_posts .= apply_filters( 'the_content', get_the_content() );
					}
					$archived_posts .= '</' . $a[ 'title_markup' ] . '>';
				} else {
					//(array) $archived_post = new stdClass();
					$archived_post            = array();
					$archived_post->post_id   = $post_id;
					$archived_post->title     = get_the_title();
					$archived_post->permalink = get_permalink( $post_id );
					$archived_post->content   = apply_filters( 'the_content', get_the_content() );
					$archived_posts[ ]        = $archived_post;
				}
			}

		}

		wp_reset_postdata();
		wp_reset_query();

		$archived_posts = '<' . $a[ 'return_markup' ] . '>' . $archived_posts . '</' . $a[ 'return_markup' ] . '>';
		$archived_posts = apply_filters( 'fb_get_archive', $archived_posts );

		if ( 'true' === $a[ 'debug' ] ) {
			echo '<h1>Debug Archived Posts</h1><pre>';
			var_dump( $archived_posts );
			echo '</pre>';
		}

		return $archived_posts;
	}

} // end class