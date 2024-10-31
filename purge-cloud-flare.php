<?php
/**
 * @package Cache Purger for Cloud Flare
 */
/*
Plugin Name: Purge Cloud Flare
Plugin URI: 
Description: Simply purges whole CloudFlare cache for desired domain if you entered your domain data. Purge is done from wp admin panel or plugin's page
Version: 1.6
Author: WebRangers
Author URI: http://webrangers.agency/
License: GPLv2 or later
Text Domain: cf-purger
*/

/*
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

Copyright 20015-2016 WebRangers
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}


define( 'CFPURGER__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFPURGER_DELETE_LIMIT', 100000 );
define( 'CFPURGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

register_activation_hook( __FILE__, array( 'Cfpurger', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Cfpurger', 'plugin_deactivation' ) );

require_once( CFPURGER__PLUGIN_DIR . 'class.cfpurger.php' );


add_action( 'init', array( 'Cfpurger', 'init' ) );
