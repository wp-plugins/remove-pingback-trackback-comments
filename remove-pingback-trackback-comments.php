<?php
/*
Plugin Name: Remove Pingback-Trackback Comments
Plugin URI: http://blogestudio.com
Description: A tool to remove pingbacks and trackbacks comments from database and force posts default pingback status to "close".
Version: 1.0
Author: Pau Iglesias, Blogestudio
License: GPLv2 or later
Text Domain: remove-pingback-trackback-comments
Domain Path: /languages
*/

// Avoid script calls via plugin URL
if (!function_exists('add_action'))
	die;

// Quick context check
if (!is_admin())
	return;

// Avoid network admin
if (function_exists('is_network_admin') && is_network_admin())
	return;

/**
 * Remove Pingback-Trackback Comments plugin class
 *
 * @package WordPress
 * @subpackage Remove Pingback-Trackback Comments
 */

// Avoid declaration plugin class conflicts
if (!class_exists('BE_Remove_Pingback_Trackback_Comments')) {
	
	// Create object plugin
	add_action('init', array('BE_Remove_Pingback_Trackback_Comments', 'instance'));
	
	// Main class
	class BE_Remove_Pingback_Trackback_Comments {



		// Constants and properties
		// ---------------------------------------------------------------------------------------------------



		// Plugin menu
		private $plugin_url;
		private $parent_slug;
		
		// Plugin title
		const title = 				'Remove Pingback-Trackback Comments';
		
		// Admin page settings
		const parent = 				'tools.php';
		const slug = 				'remove-pingback-trackback-comments';
		
		// Key prefix
		const key = 				'be_rptc_';
		
		// Role
		const capability = 			'edit_others_posts';
		
		// Post types avoided
		const post_types_avoid = 	'attachment, nav_menu_item, revision';



		// Initialization
		// ---------------------------------------------------------------------------------------------------



		/**
		 * Creates a new object instance
		 */
		public static function instance() {
			return new BE_Remove_Pingback_Trackback_Comments;
		}



		/**
		 * Constructor
		 */
		private function __construct() {
			add_action('admin_menu', array(&$this, 'admin_menu'));
		}



		/**
		 *  Load translation file
		 */
		private static function load_plugin_textdomain($lang_dir = 'languages') {
			
			// Check load
			static $loaded;
			if (isset($loaded))
				return;
			$loaded = true;
			
			// Check if this plugin is placed in wp-content/mu-plugins directory or subdirectory
			if (('mu-plugins' == basename(dirname(__FILE__)) || 'mu-plugins' == basename(dirname(dirname(__FILE__)))) && function_exists('load_muplugin_textdomain')) {
				load_muplugin_textdomain('remove-pingback-trackback-comments', ('mu-plugins' == basename(dirname(__FILE__))? '' : basename(dirname(__FILE__)).'/').$lang_dir);
			
			// Usual wp-content/plugins directory location
			} else {
				load_plugin_textdomain('remove-pingback-trackback-comments', false, basename(dirname(__FILE__)).'/'.$lang_dir);
			}
		}



		// Admin Page
		// ---------------------------------------------------------------------------------------------------



		/**
		 * Admin menu hook
		 */
		public function admin_menu() {
			$this->parent_slug = apply_filters(self::key.'parent_menu', self::parent, self::slug, self::capability);
			$this->plugin_url = apply_filters(self::key.'plugin_url', admin_url(self::parent.'?page='.self::slug), $this->parent_slug);
			add_submenu_page($this->parent_slug, apply_filters(self::key.'title', self::title), apply_filters(self::key.'title_menu', self::title), self::capability, self::slug, array(&$this, 'admin_page'));
		}



		/**
		 * Admin page display
		 */
		public function admin_page() {
			
			// Check user capabilities
			if (!current_user_can(self::capability))
				wp_die(__('You do not have sufficient permissions to access this page.', 'remove-pingback-trackback-comments'));
			
			// Globals
			global $wpdb;
			
			// Initialize
			$sended = false;
			
			// Retrieve valid post types
			$post_types_keys = self::get_allowed_post_types('keys');
			
			// Check submit
			if (isset($_POST['nonce'])) {
				
				// Check nonce
				if (!wp_verify_nonce($_POST['nonce'], __FILE__))
					return;
				
				// Sended mode
				$sended = true;
				
				// No timeout
				set_time_limit(0);
				
				// Close comments in existing posts
				if (!empty($post_types_keys) && is_array($post_types_keys))
					$wpdb->query('UPDATE '.$wpdb->posts.' SET ping_status = "closed" WHERE ping_status = "open" AND post_type IN ("'.implode('", "', array_map('esc_sql', $post_types_keys)).'")');
				
				// Remove pingback and trackback comments
				$wpdb->query('DELETE FROM '.$wpdb->comments.' WHERE comment_type IN ("pingback", "trackback")');
				
				// Update default pingback value
				update_option('default_ping_status', 'closed');
				
				// Remove orphans from comment meta table
				$wpdb->query('DELETE FROM '.$wpdb->commentmeta.' WHERE comment_id NOT IN (SELECT comment_ID FROM '.$wpdb->comments.')');
			}
			
			// Totals
			$total_pingbacks 	= (int) $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->comments.' WHERE comment_type IN ("pingback", "trackback")');
			$total_no_pingbacks = (int) $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->comments.' WHERE comment_type NOT IN ("pingback", "trackback")');
			$total_posts_open	= (empty($post_types_keys) || !is_array($post_types_keys))? 0 : (int) $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->posts.' WHERE ping_status = "open" AND post_type IN ("'.implode('", "', array_map('esc_sql', $post_types_keys)).'") AND post_status = "publish"');
			
			// Load translations
			self::load_plugin_textdomain();
			
			?><div class="wrap">
				
				<?php screen_icon('tools'); ?>
				
				<h2><?php echo apply_filters(self::key.'title', self::title); ?></h2>
				
				<div id="poststuff">
					
					<div class="postbox">
						
						<h3 class="hndle"><span><?php _e('Pingbacks and Trackbacks overview', 'remove-pingback-trackback-comments'); ?></span></h3>
						
						<div class="inside">
							
							<div id="postcustomstuff">
								
								<p><?php printf(__('Total comments: <b style="color: %s">%s pingbacks and trackbaks</b>, and %s user comments.', 'remove-pingback-trackback-comments'), ($total_pingbacks > 0)? 'red' : 'green', number_format_i18n($total_pingbacks), number_format_i18n($total_no_pingbacks)); ?></p>
								
								<p><?php printf(__('Total allowing pingbacks and trackbacks: <b style="color: %s">%s posts</b> with ping status in mode <b>open</b>', 'remove-pingback-trackback-comments'), ($total_posts_open > 0)? 'red' : 'green', number_format_i18n($total_posts_open)); ?></p>
								
								<p><?php printf(__('Default value for new posts: <i>default ping status</i> is <b style="color: %s">%s</b>', 'remove-pingback-trackback-comments'), ('open' == get_option('default_ping_status'))? 'red' : 'green', ('open' == get_option('default_ping_status'))? 'open' : 'closed'); ?></b></p>
								
							</div>
							
						</div>
						
					</div>
					
					<div class="postbox">
					
						<h3 class="hndle"><span><?php _e('Removing Pingbacks and Trackbacks', 'remove-pingback-trackback-comments'); ?></span></h3>
						
						<div class="inside">
							
							<div id="postcustomstuff">
								
								<?php if ($sended) : ?>
								
									<p><b><?php _e('Update was done successfully.', 'remove-pingback-trackback-comments'); ?></b></p>
									
									<p><?php _e('Now you need to update the internal comments counter for each post.', 'remove-pingback-trackback-comments'); ?></p>
									
									<p><?php printf(__('You can do this by installing and running the <a href="%s" target="_blank"><b>Update Comments Count</b></a> plugin.', 'remove-pingback-trackback-comments'), 'https://wordpress.org/plugins/update-comments-count/'); ?></p>
								
								<?php else : ?>
								
									<form id="be-rptc-update" method="post" action="<?php echo $this->plugin_url; ?>">
										
										<input type="hidden" name="nonce" value="<?php echo wp_create_nonce(__FILE__); ?>" />
										
										<p><?php _e('By submitting the form the following updates will be performed:', 'remove-pingback-trackback-comments'); ?></p>
										
										<ol>
											<li><?php _e('Change to state <b>closed</b> for all posts with the <i>ping status</i> in mode <b>open</b>.', 'remove-pingback-trackback-comments'); ?></li>
											<li><?php _e('Changes in <i>Settings &gt; Discussion</i> the value of <i>default ping status</i> to <b>closed</b>, avoiding future <b>pingbacks and trackbacks</b> for new posts.', 'remove-pingback-trackback-comments'); ?></li>
											<li><?php _e('Delete records in comments table for any comment with value type <b>pingback</b> or <b>trackback</b>.', 'remove-pingback-trackback-comments'); ?></li>
											<li><?php _e('Delete orphan records from comments <i>meta</i> table without post ID equivalent in posts table.', 'remove-pingback-trackback-comments'); ?></li>
										</ol>
										
										<p><?php printf(__('Updates will be affect the following Post Types: <b>%s</b>', 'remove-pingback-trackback-comments'), implode(', ', self::get_allowed_post_types('names'))); ?></p>
										
										<p><?php printf(__('It`s recommended a <strong>database backup</strong> of tables <code>%s</code>, <code>%s</code> and <code>%s</code>', 'remove-pingback-trackback-comments'), $wpdb->posts, $wpdb->comments, $wpdb->commentmeta); ?></p>
										
										<input type="submit" value="<?php _e('Perform changes', 'remove-pingback-trackback-comments'); ?>" class="button-primary" />
										
									</form>
									
									<p><?php _e('After submitting this form is recommended to update the comments counter of each individual post.', 'remove-pingback-trackback-comments'); ?></p>
									
									<p><?php printf(__('You can update these counters installing and running the plugin <a href="%s" target="_blank"><b>Update Comments Count</b></a>.', 'remove-pingback-trackback-comments'), 'https://wordpress.org/plugins/update-comments-count/'); ?></p>
									
									<script type="text/javascript">;jQuery(document).ready(function($) { $('#be-rptc-update').submit(function() { return confirm("<?php esc_attr(_e('Changes will be update in database, before you should have a backup, continue?', 'remove-pingback-trackback-comments')); ?>"); }); });</script>
									
								<?php endif; ?>
								
							</div>
							
						</div>
						
					</div>
					
				</div>
				
			</div><?php
		}



		// Internal procedures
		// ---------------------------------------------------------------------------------------------------



		/**
		 * Post types allowed for comments count update
		 */
		private static function get_allowed_post_types($output = 'keys') {
			
			// Current avoid post types
			$avoid_post_types = apply_filters(self::key.'avoid_post_types', array_map('trim', explode(',', self::post_types_avoid)));
			if (empty($avoid_post_types) || !is_array($avoid_post_types))
				return false;
			
			// Compute allowed post types
			$post_types = get_post_types(array(), 'objects');
			$allowed_post_types = array_diff_key($post_types, array_fill_keys($avoid_post_types, true));
			
			// Return keys
			if ('keys' == $output)
				return array_keys($allowed_post_types);
			
			// Return names
			if ('names' == $output) {
				$names = array();
				foreach ($allowed_post_types as $key => $post_type)
					$names[] = $post_type->labels->name;
				return $names;
			}
			
			// Default
			return $allowed_post_types;
		}



	}
}