<?php

/**
 * The user interface and activation/deactivation methods for administering
 * the Tweet Mirror plugin
 *
 * This plugin abstracts WordPress' Settings API to simplify the creation of
 * a settings admin interface.  Read the docblocks for the set_sections() and
 * set_fields() methods to learn how to create your own settings.
 *
 * A table is created in the activate() method and is dropped in the
 * deactivate() method.  If your plugin needs tables, adjust the table
 * definitions and removals as needed.  If you don't need a table, remove
 * those portions of the activate() and deactivate() methods.
 *
 * This plugin is coded to be installed in either a regular, single WordPress
 * installation or as a network plugin for multisite installations.  So, by
 * default, multisite networks can only activate this plugin via the
 * Network Admin panel.  If you want your plugin to be configurable for each
 * site in a multisite network, you must do the following:
 *
 * + Search admin.php and tweet-mirror.php
 *   for is_multisite() if statements.  Remove the true parts and leave
 *   the false parts.
 * + In tweet-mirror.php, go to the initialize() method
 *   and remove the $wpdb->get_blog_prefix(0) portion of the
 *   $this->table_login assignment.
 *
 * Beyond that, you're advised to leave the rest of this file alone.
 *
 * @package tweet-mirror
 * @link http://wordpress.org/extend/plugins/tweet-mirror/
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @author
 * @copyright
 *
 * This plugin used the Object-Oriented Plugin Template Solution as a skeleton
 * http://wordpress.org/extend/plugins/oop-plugin-template-solution/
 */

/**
 * The user interface and activation/deactivation methods for administering
 * the Tweet Mirror plugin
 *
 * @package tweet-mirror
 * @link http://wordpress.org/extend/plugins/tweet-mirror/
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @author
 * @copyright
 *
 * This plugin used the Object-Oriented Plugin Template Solution as a skeleton
 * http://wordpress.org/extend/plugins/oop-plugin-template-solution/
 */
class tweet_mirror_admin extends tweet_mirror {
	/**
	 * The WP privilege level required to use the admin interface
	 * @var string
	 */
	protected $capability_required;

	/**
	 * Metadata and labels for each element of the plugin's options
	 * @var array
	 */
	protected $fields;

	/**
	 * URI for the forms' action attributes
	 * @var string
	 */
	protected $form_action;

	/**
	 * Name of the page holding the options
	 * @var string
	 */
	protected $page_options;

	/**
	 * Metadata and labels for each settings page section
	 * @var array
	 */
	protected $settings;

	/**
	 * Title for the plugin's settings page
	 * @var string
	 */
	protected $text_settings;


	/**
	 * Sets the object's properties and options
	 *
	 * @return void
	 *
	 * @uses tweet_mirror::initialize()  to set the object's
	 *	     properties
	 * @uses tweet_mirror_admin::set_sections()  to populate the
	 *       $sections property
	 * @uses tweet_mirror_admin::set_fields()  to populate the
	 *       $fields property
	 */
	public function __construct() {
		$this->initialize();
		$this->set_sections();
		$this->set_fields();

		// Translation already in WP combined with plugin's name.
		$this->text_settings = self::NAME . ' ' . __('Settings');

		if (is_multisite()) {
			$this->capability_required = 'manage_network_options';
			$this->form_action = '../options.php';
			$this->page_options = 'settings.php';
		} else {
			$this->capability_required = 'manage_options';
			$this->form_action = 'options.php';
			$this->page_options = 'options-general.php';
		}
	}

	/*
	 * ===== ACTIVATION & DEACTIVATION CALLBACK METHODS =====
	 */

	/**
	 * Establishes the tables and settings when the plugin is activated
	 * @return void
	 */
	public function activate() {
		global $wpdb;

		if (is_multisite() && !is_network_admin()) {
			die($this->hsc_utf8(sprintf(__("%s must be activated via the Network Admin interface when WordPress is in multistie network mode.", self::ID), self::NAME)));
		}

		/*
		 * Create or alter the plugin's tables as needed.
		 */

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Note: dbDelta() requires two spaces after "PRIMARY KEY".  Weird.
		// WP's insert/prepare/etc don't handle NULL's (at least in 3.3).
		// It also requires the keys to be named and there to be no space
		// the column name and the key length.
		$sql = "CREATE TABLE `$this->table_login` (
				login_id BIGINT(20) NOT NULL AUTO_INCREMENT,
				user_login VARCHAR(60) NOT NULL DEFAULT '',
				date_login TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (login_id),
				KEY user_login (user_login(5))
				)";

		dbDelta($sql);
		if ($wpdb->last_error) {
			die($wpdb->last_error);
		}

		dbDelta("CREATE TABLE `$this->table_log` (
				log_id BIGINT(20) NOT NULL AUTO_INCREMENT,
				log_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				log_message VARCHAR(200) NOT NULL DEFAULT '',
				PRIMARY KEY  (log_id)
				)");
		if ($wpdb->last_error) {
			die($wpdb->last_error);
		}

		/*
		 * Save this plugin's options to the database.
		 */

		if (is_multisite()) {
			switch_to_blog(1);
		}
		update_option($this->option_name, $this->options);
		if (is_multisite()) {
			restore_current_blog();
		}
	}

	/**
	 * Removes the tables and settings when the plugin is deactivated
	 * if the deactivate_deletes_data option is turned on
	 * @return void
	 */
	public function deactivate() {
		global $wpdb;

		$prior_error_setting = $wpdb->show_errors;
		$wpdb->show_errors = false;
		$denied = 'command denied to user';

		$wpdb->query("DROP TABLE `$this->table_login`");
		if ($wpdb->last_error) {
			if (strpos($wpdb->last_error, $denied) === false) {
				die($wpdb->last_error);
			}
		}

		$wpdb->query("DROP TABLE `$this->table_log`");
		if ($wpdb->last_error) {
			if (strpos($wpdb->last_error, $denied) === false) {
				die($wpdb->last_error);
			}
		}

		$wpdb->show_errors = $prior_error_setting;

		$package_id = self::ID;
		$wpdb->escape_by_ref($package_id);

		$wpdb->query("DELETE FROM `$wpdb->options`
				WHERE option_name LIKE '$package_id%'");

		$wpdb->query("DELETE FROM `$wpdb->usermeta`
				WHERE meta_key LIKE '$package_id%'");
	}

	/*
	 * ===== ADMIN USER INTERFACE =====
	 */

	/**
	 * Sets the metadata and labels for each settings page section
	 *
	 * Settings pages have sections for grouping related fields.  This plugin
	 * uses the $sections property, below, to define those sections.
	 *
	 * The $sections property is a two-dimensional, associative array.  The top
	 * level array is keyed by the section identifier (<sid>) and contains an
	 * array with the following key value pairs:
	 *
	 * + title:  a short phrase for the section's header
	 * + callback:  the method for rendering the section's description.  If a
	 *   description is not needed, set this to "section_blank".  If a
	 *   description is helpful, use "section_<sid>" and create a corresponding
	 *   method named "section_<sid>()".
	 *
	 * @return void
	 * @uses tweet_mirror_admin::$sections  to hold the data
	 */
	protected function set_sections() {
		$this->sections = array(
			'auth' => array(
				'title' => __("Authentication", self::ID),
				'callback' => 'section_auth',
			),
			'feeds' => array(
				'title' => __("Twitter timelines", self::ID),
				'callback' => 'section_feeds',
			),
			'misc' => array(
				'title' => __("Miscellaneous", self::ID),
				'callback' => 'section_blank',
			),
		);
	}

	/**
	 * Sets the metadata and labels for each element of the plugin's
	 * options
	 *
	 * The $fields property is a two-dimensional, associative array.  The top
	 * level array is keyed by the field's identifier and contains an array
	 * with the following key value pairs:
	 *
	 * + section:  the section identifier (<sid>) for the section this
	 *   setting should be displayed in
	 * + label:  a very short title for the setting
	 * + text:  the long description about what the setting does.  Note:
	 *   a description of the default value is automatically appended.
	 * + type:  the data type ("int", "string", or "bool").  If type is "bool,"
	 *   the following two elements are also required:
	 * + bool0:  description for the button indicating the option is off
	 * + bool1:  description for the button indicating the option is on
	 *
	 * WARNING:  Make sure to keep this propety and the
	 * tweet_mirror_admin::$options_default
	 * property in sync.
	 *
	 * @return void
	 * @uses tweet_mirror_admin::$fields  to hold the data
	 */
	protected function set_fields() {
		$this->fields = array(
			'track_logins' => array(
				'section' => 'login',
				'label' => __("Track Logins", self::ID),
				'text' => __("Should the time of each user's login be stored?", self::ID),
				'type' => 'bool',
				'bool0' => __("No, don't track logins.", self::ID),
				'bool1' => __("Yes, track logins.", self::ID),
			),
			'example_int' => array(
				'section' => 'misc',
				'label' => __("Integer", self::ID),
				'text' => __("An example for storing an integer value.", self::ID),
				'type' => 'int',
			),
			'example_string' => array(
				'section' => 'misc',
				'label' => __("String", self::ID),
				'text' => __("See how to set a string value.", self::ID),
				'type' => 'string',
			),

			'auth_id' => array(
				'section' => 'auth',
				'label' => __("AUuh account", self::ID),
				'text' => __("Twitter account for authentication", self::ID),
				'type' => 'string',
			),

			'screen_name' => array(
					'section' => 'feeds',
					'label' => __("Twitter name", self::ID),
					'text' => __("Screen name of Twitter account to mirrir", self::ID),
					'type' => 'string',
				),
			'update_interval_minutes' => array(
					'section' => 'feeds',
					'label' => __("Update interval", self::ID),
					'text' => __("Number of minutes between updates", self::ID),
					'type' => 'int',
				),
			'post_type' => array(
					'section' => 'feeds',
					'label' => __("Post type", self::ID),
					'text' => __("Post type to use for mirrored tweets", self::ID),
					'type' => 'string',
				),
			'post_cat' => array(
					'section' => 'feeds',
					'label' => __("Category", self::ID),
					'text' => __("Category to use for mirrored tweets", self::ID),
					'type' => 'string',
				),
			'post_tags' => array(
					'section' => 'feeds',
					'label' => __("Tags", self::ID),
					'text' => __("Tags to use for mirrored tweets. Sepearate multiple tags with commas.", self::ID),
					'type' => 'string',
				),
			'post_auther' => array(
					'section' => 'feeds',
					'label' => __("Author", self::ID),
					'text' => __("Author to use for mirrored tweets", self::ID),
					'type' => 'string',
				),
			'title' => array(
					'section' => 'feeds',
					'label' => __("Title", self::ID),
					'text' => __("Title to use for mirrored tweets", self::ID),
					'type' => 'string',
				),

			'deactivate_deletes_data' => array(
				'section' => 'misc',
				'label' => __("Deactivation", self::ID),
				'text' => __("Should deactivating the plugin remove all of the plugin's data and settings?", self::ID),
				'type' => 'bool',
				'bool0' => __("No, preserve the data for future use.", self::ID),
				'bool1' => __("Yes, delete the data.", self::ID),
			),
		);
	}

	/**
	 * A filter to add a "Settings" link in this plugin's description
	 *
	 * NOTE: This method is automatically called by WordPress for each
	 * plugin being displayed on WordPress' Plugins admin page.
	 *
	 * @param array $links  the links generated thus far
	 * @return array
	 */
	public function plugin_action_links($links) {
		// Translation already in WP.
		$links[] = '<a href="' . $this->hsc_utf8($this->page_options)
				. '?page=' . self::ID . '">'
				. $this->hsc_utf8(__('Settings')) . '</a>';
		return $links;
	}

	/**
	 * Declares a menu item and callback for this plugin's settings page
	 *
	 * NOTE: This method is automatically called by WordPress when
	 * any admin page is rendered
	 */
	public function admin_menu() {
		add_submenu_page(
			$this->page_options,
			$this->text_settings,
			self::NAME,
			$this->capability_required,
			self::ID,
			array(&$this, 'page_settings')
		);
	}

	/**
	 * Declares the callbacks for rendering and validating this plugin's
	 * settings sections and fields
	 *
	 * NOTE: This method is automatically called by WordPress when
	 * any admin page is rendered
	 */
	public function admin_init() {
		register_setting(
			$this->option_name,
			$this->option_name,
			array(&$this, 'validate')
		);

		// Dynamically declares each section using the info in $sections.
		foreach ($this->sections as $id => $section) {
			add_settings_section(
				self::ID . '-' . $id,
				$this->hsc_utf8($section['title']),
				array(&$this, $section['callback']),
				self::ID
			);
		}

		// Dynamically declares each field using the info in $fields.
		foreach ($this->fields as $id => $field) {
			add_settings_field(
				$id,
				$this->hsc_utf8($field['label']),
				array(&$this, $id),
				self::ID,
				self::ID . '-' . $field['section']
			);
		}
	}

	/**
	 * The callback for rendering the settings page
	 * @return void
	 */
	public function page_settings() {
		if (is_multisite()) {
			// WordPress doesn't show the successs/error messages on
			// the Network Admin screen, at least in version 3.3.1,
			// so force it to happen for now.
			include_once ABSPATH . 'wp-admin/options-head.php';
		}

		echo '<h2>' . $this->hsc_utf8($this->text_settings) . '</h2>';
		echo '<form action="' . $this->hsc_utf8($this->form_action) . '" method="post">' . "\n";
		settings_fields($this->option_name);
		do_settings_sections(self::ID);
		submit_button();
		echo '</form>';
	}

	/**
	 * Callback for rendering the authentication section description
	 * @return void
	 */
	public function section_auth() {
		echo '<p>';
		echo $this->hsc_utf8(__("You need to authenticate with a Twitter account to gain access to Twitter data. This doesn't have to be the same as the Twitter account you want to mirror.", self::ID));
		echo '</p>';
	}

	/**
	 * Callback for rendering the feeds/timelines section description
	 * @return void
	 */
	public function section_feeds() {
		echo '<p>';
		echo $this->hsc_utf8(__("Details of the Twitter account you want to mirror in your blog.", self::ID));
		echo '</p>';
	}

	/**
	 * Callback for rendering sections that don't have descriptions
	 * @return void
	 */
	public function section_blank() {
	}

	/**
	 * The callback for rendering the fields
	 * @return void
	 *
	 * @uses tweet_mirror_admin::input_int()  for rendering
	 *       text input boxes for numbers
	 * @uses tweet_mirror_admin::input_radio()  for rendering
	 *       radio buttons
	 * @uses tweet_mirror_admin::input_string()  for rendering
	 *       text input boxes for strings
	 */
	public function __call($name, $params) {
		if (empty($this->fields[$name]['type'])) {
			return;
		}
		switch ($this->fields[$name]['type']) {
			case 'bool':
				$this->input_radio($name);
				break;
			case 'int':
				$this->input_int($name);
				break;
			case 'string':
				$this->input_string($name);
				break;
		}
	}

	/**
	 * Renders the radio button inputs
	 * @return void
	 */
	protected function input_radio($name) {
		echo $this->hsc_utf8($this->fields[$name]['text']) . '<br/>';
		echo '<input type="radio" value="0" name="'
			. $this->hsc_utf8($this->option_name)
			. '[' . $this->hsc_utf8($name) . ']"'
			. ($this->options[$name] ? '' : ' checked="checked"') . ' /> ';
		echo $this->hsc_utf8($this->fields[$name]['bool0']);
		echo '<br/>';
		echo '<input type="radio" value="1" name="'
			. $this->hsc_utf8($this->option_name)
			. '[' . $this->hsc_utf8($name) . ']"'
			. ($this->options[$name] ? ' checked="checked"' : '') . ' /> ';
		echo $this->hsc_utf8($this->fields[$name]['bool1']);
	}

	/**
	 * Renders the text input boxes for editing integers
	 * @return void
	 */
	protected function input_int($name) {
		echo '<input type="text" size="3" name="'
			. $this->hsc_utf8($this->option_name)
			. '[' . $this->hsc_utf8($name) . ']"'
			. ' value="' . $this->hsc_utf8($this->options[$name]) . '" /> ';
		echo $this->hsc_utf8($this->fields[$name]['text']
				. ' ' . __('Default:', self::ID) . ' '
				. $this->options_default[$name] . '.');
	}

	/**
	 * Renders the text input boxes for editing strings
	 * @return void
	 */
	protected function input_string($name) {
		echo '<input type="text" size="75" name="'
			. $this->hsc_utf8($this->option_name)
			. '[' . $this->hsc_utf8($name) . ']"'
			. ' value="' . $this->hsc_utf8($this->options[$name]) . '" /> ';
		echo '<br />';
		echo $this->hsc_utf8($this->fields[$name]['text']
				. ' ' . __('Default:', self::ID) . ' '
				. $this->options_default[$name] . '.');
	}

	/**
	 * Validates the user input
	 *
	 * NOTE: WordPress saves the data even if this method says there are
	 * errors.  So this method sets any inappropriate data to the default
	 * values.
	 *
	 * @param array $in  the input submitted by the form
	 * @return array  the sanitized data to be saved
	 */
	public function validate($in) {
		$out = $this->options_default;
		if (!is_array($in)) {
			// Not translating this since only hackers will see it.
			add_settings_error($this->option_name,
					$this->hsc_utf8($this->option_name),
					'Input must be an array.');
			return $out;
		}

		$gt_format = __("must be >= '%s',", self::ID);
		$default = __("so we used the default value instead.", self::ID);

		// Dynamically validate each field using the info in $fields.
		foreach ($this->fields as $name => $field) {
			if (!array_key_exists($name, $in)) {
				continue;
			}

			if (!is_scalar($in[$name])) {
				// Not translating this since only hackers will see it.
				add_settings_error($this->option_name,
						$this->hsc_utf8($name),
						$this->hsc_utf8("'" . $field['label'])
								. "' was not a scalar, $default");
				continue;
			}

			switch ($field['type']) {
				case 'bool':
					if ($in[$name] != 0 && $in[$name] != 1) {
						// Not translating this since only hackers will see it.
						add_settings_error($this->option_name,
								$this->hsc_utf8($name),
								$this->hsc_utf8("'" . $field['label']
										. "' must be '0' or '1', $default"));
						continue 2;
					}
					break;
				case 'int':
					if (!ctype_digit($in[$name])) {
						add_settings_error($this->option_name,
								$this->hsc_utf8($name),
								$this->hsc_utf8("'" . $field['label'] . "' "
										. __("must be an integer,", self::ID)
										. ' ' . $default));
						continue 2;
					}
					if (array_key_exists('greater_than', $field)
						&& $in[$name] < $field['greater_than'])
					{
						add_settings_error($this->option_name,
								$this->hsc_utf8($name),
								$this->hsc_utf8("'" . $field['label'] . "' "
										. sprintf($gt_format, $field['greater_than'])
										. ' ' . $default));
						continue 2;
					}
					break;
			}
			$out[$name] = $in[$name];
		}

		return $out;
	}
}
