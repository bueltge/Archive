<?php
/**
 * Archive - Settings
 * @license GPLv2
 * @package Archive
 * @subpackage Archive Settings
 */

class FB_Archive_Settings {
	
	protected static $classobj = NULL;
	// string for translation
	public $textdomain;
	
	
	/**
	 * Handler for the action 'init'. Instantiates this class.
	 * 
	 * @access public
	 * @since 0.0.2
	 * @return $classobj
	 */
	public static function get_object() {
		
		if ( NULL === self :: $classobj ) {
			self :: $classobj = new self;
		}
		
		return self :: $classobj;
	}
	
	
	/**
	 * Construvtor, init on defined hooks of WP and include second class
	 * 
	 * @access  public
	 * @since   0.0.2
	 * @uses    register_activation_hook, register_uninstall_hook, add_action
	 * @return  void
	 */
	public function __construct() {
		
		// textdomain from parent class
		$this -> textdomain = FB_Archive :: get_textdomain();
		
		register_uninstall_hook( __FILE__, 	array( 'FB_Archive_Settings', 'unregister_settings' ) );
		
		add_action( 'admin_menu',			array( $this, 'add_settings_page' ) );
		add_action( 'admin_init',			array( $this, 'register_settings' ) );
	}
	
	
	/**
	 * return plugin comment data
	 * 
	 * @since 0.0.2
	 * @access public
	 * @param $value string, default = 'Version'
	 *		Name, PluginURI, Version, Description, Author, AuthorURI, TextDomain, DomainPath, Network, Title
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
	 * Return Textdomain string
	 * 
	 * @access  public
	 * @since   0.0.2
	 * @return  string
	 */
	public function get_textdomain() {
		
		return $this -> textdomain;
	}
	
	
	/**
	 * Add settings link on plugins.php in backend
	 * 
	 * @uses plugin_basename
	 * @access public
	 * @param array $links, string $file
	 * @since 0.0.2
	 * @return string $links
	 */
	public function plugin_action_links( $links, $file ) {
		
		if ( plugin_basename( dirname(__FILE__).'/archive.php' ) == $file )
			$links[] = '<a href="options-general.php?page=archive_settings_group">' . __('Settings') . '</a>';
	
		return $links;
	}
	
	
	/**
	 * Add settings page in WP backend
	 * 
	 * @uses add_options_page
	 * @access public
	 * @since 0.0.2
	 * @return void
	 */
	public function add_settings_page() {
		
		add_submenu_page( 
			'edit.php?post_type=archiv',
			'Archive Settings', 
			'Settings', 
			'manage_options', 
			'archive_settings_group', 
			array( $this, 'get_settings_page' )
		);
		
		add_action( 'contextual_help', array( $this, 'contextual_help' ), 10, 3 );
	}
	
	/**
	 * Return form and markup on settings page
	 * 
	 * @uses settings_fields, normalize_whitespace
	 * @access public
	 * @since 0.0.2
	 * @return void
	 */
	public function get_settings_page() {
		
		screen_icon('archive-settings'); ?>
		<div class="wrap">
		<h2><?php echo FB_Archive :: get_plugin_data( 'Name' ); ?></h2>
		
		<form method="post" action="options.php">
			<?php
			settings_fields( 'archive_settings_group' );
			$options = get_option( 'archive_settings' );
			?>
			
			<table class="form-table">
				<tr valign="top">
					<td scope="row">
						<label for="def_archive_screens_all"><?php _e( 'Show Archive link', $this -> get_textdomain() ) ?></label>
					</td>
					<td>
						<input type="checkbox" id="def_archive_screens_all" name="archive_settings[def_archive_screens_all]" value="1" 
						<?php if ( isset( $options['def_archive_screens_all'] ) ) checked( '1', $options['def_archive_screens_all'] ); ?> />
						<span class="description"><?php _e( 'View Archive-Possibility on all post types or put behind the post types. One type per line.', $this -> get_textdomain() ) ?></span>
						<textarea id="def_archive_screens" name="archive_settings[def_archive_screens]" cols="80" rows="10" 
						aria-required="true" class="all-options" ><?php if ( isset($options['def_archive_screens']) ) echo $options['def_archive_screens']; ?></textarea>
						<br /><span class="description"><?php _e( 'Your install uses the following post types:', $this -> get_textdomain() ) ?><br /><code><?php echo implode( ', ', get_post_types() ); ?></code></span>
					</td>
				</tr>
			</table>
			
			<h3><?php _e( 'Scheduled Archiving', $this -> get_textdomain() ) ?></h3>
			<table class="form-table">
				<tr valign="top">
					<td scope="row">
						<label for="scheduled_archiving"><?php _e( 'Scheduled Archiving Settings', $this -> get_textdomain() ) ?></label>
					</td>
					<td>
						<input type="checkbox" id="scheduled_archiving" name="archive_settings[scheduled_archiving]" value="1" 
						<?php if ( isset( $options['scheduled_archiving'] ) ) checked( '1', $options['scheduled_archiving'] ); ?> />
						<span class="description"><?php _e( 'Automatically archive items older than', $this -> get_textdomain() ) ?></span>
						<input name="scheduled_archiving_days" type="text" id="scheduled_archiving_days" value="" class="small-text">
						<span class="description"><?php _e( 'days', $this -> get_textdomain() ) ?></span>
					</td>
				</tr>
			</table>
		
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
	
		</form>
		</div>
		<?php
	}
	
	/**
	 * Validate settings for options
	 * 
	 * @uses normalize_whitespace
	 * @access public
	 * @param array $value
	 * @since 0.0.2
	 * @return string $value
	 */
	public function validate_settings( $value ) {
		
		if ( isset($value['checkbox']) && 1 == $value['checkbox'] )
			$value['checkbox'] = 1;
		else 
			$value['checkbox'] = 0;
		$value['notice']          = normalize_whitespace( $value['notice'] );
		
		return $value;
	}
	
	/**
	 * Register settings for options
	 * 
	 * @uses register_setting
	 * @access public
	 * @since 0.0.2
	 * @return void
	 */
	public function register_settings() {
		
		register_setting( 'archive_settings_group', 'archive_settings', array( $this, 'validate_settings' ) );
	}
	
	/**
	 * Unregister and delete settings; clean database
	 * 
	 * @uses unregister_setting, delete_option
	 * @access public
	 * @since 0.0.2
	 * @return void
	 */
	public function unregister_settings() {
		
		unregister_setting( 'archive_settings_group', 'archive_settings' );
		delete_option( 'archive_settings' );
	}
	
	/**
	 * Add help text
	 * 
	 * @uses normalize_whitespace
	 * @param string $contextual_help
	 * @param string $screen_id
	 * @param string $screen
	 * @since 0.0.2
	 * @return string $contextual_help
	 */
	public function contextual_help($contextual_help, $screen_id, $screen) {
			
		if ( 'settings_page_archive_settings_group' !== $screen_id )
			return $contextual_help;
			
		$contextual_help = 
			'<p>' . __( '' ) . '</p>';
			
		return normalize_whitespace( $contextual_help );
	}
	
}
