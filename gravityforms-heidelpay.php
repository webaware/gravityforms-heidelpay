<?php
/*
Plugin Name: Gravity Forms heidelpay
Plugin URI: https://wordpress.org/plugins/gf-heidelpay/
Description: Easily create online payment forms with Gravity Forms and heidelpay.
Version: 1.2.0
Author: WebAware
Author URI: https://shop.webaware.com.au/
Text Domain: gf-heidelpay
Domain Path: /languages/
*/

/*
copyright (c) 2016-2018 WebAware Pty Ltd (email : support@webaware.com.au)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if (!defined('ABSPATH')) {
	exit;
}

define('GFHEIDELPAY_PLUGIN_FILE', __FILE__);
define('GFHEIDELPAY_PLUGIN_ROOT', dirname(__FILE__) . '/');
define('GFHEIDELPAY_PLUGIN_NAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
define('GFHEIDELPAY_PLUGIN_MIN_PHP', '5.6');
define('GFHEIDELPAY_PLUGIN_VERSION', '1.2.0');

require GFHEIDELPAY_PLUGIN_ROOT . 'includes/functions-global.php';

if (version_compare(PHP_VERSION, GFHEIDELPAY_PLUGIN_MIN_PHP, '<')) {
	add_action('admin_notices', 'gf_heidelpay_fail_php_version');
	return;
}

require GFHEIDELPAY_PLUGIN_ROOT . 'includes/bootstrap.php';
