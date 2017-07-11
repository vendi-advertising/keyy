<?php

if (!defined('KEYY_DIR')) die('No direct access allowed');

if (class_exists('Keyy_Options')) return;

class Keyy_Options {
	
	/**
	 * This method gets an option from the keyy options in the WordPress database
	 *
	 * @param  String $option  the name of the option to get
	 * @param  Mixed  $default a value to return if the option is not currently set
	 * @return Mixed  The option from the database
	 */
	public static function get_option($option, $default = null) {
		$tmp = get_site_option('keyy_options');
		if (isset($tmp[$option])) {
			return $tmp[$option];
		} else {
			return $default;
		}
	}

	/**
	 * This method is used to update a keyy option stored in the WordPress database
	 *
	 * @param  String  $option    the name of the option to update
	 * @param  Mixed   $value	  the value to save to the option
	 * @param  boolean $use_cache a bool to indicate if the cache should be used or not
	 * @return Mixed           	  the updated option
	 */
	public static function update_option($option, $value, $use_cache = true) {
		$tmp = get_site_option('keyy_options', array(), $use_cache);
		if (!is_array($tmp)) $tmp = array();
		$tmp[$option] = $value;
		return update_site_option('keyy_options', $tmp);
	}

	/**
	 * This method is used to delete a keyy option stored in the WordPress database
	 *
	 * @param  String $option the option to delete
	 */
	public static function delete_option($option) {
		$tmp = get_site_option('keyy_options');
		if (is_array($tmp)) {
			if (isset($tmp[$option])) unset($tmp[$option]);
		} else {
			$tmp = array();
		}
		update_site_option('keyy_options', $tmp);
	}
}
