<?php

/*
 * Plugin Name: xnau Plugin Updates
 * Version: 1.0
 * Description: provides update services to xnau plugins
 * Author: Roland Barker, xnau webdesign
 * Plugin URI: https://xnau.com/shop/combo-multisearch-plugin/
 * Text Domain: xnau-updates
 * Domain Path: /languages
 * License: GPL3
 * 
 * Copyright 2022 Roland Barker xnau webdesign  (email : webdesign@xnau.com)
 */

class xnau_plugin_updates {

  /**
   * @var string the plugin slug name
   */
  const name = 'xnau-plugin-updates';

  /**
   * 
   */
  public function __construct()
  {
    add_action( 'init', function () {
      xnau_setup_plugin_updater( __FILE__, self::name );
    }, 50 );
  }

}

/**
 * sets up the update checker for the plugin
 * 
 * @param string $plugin_file absolute path
 * @param string $plugin_name slug name of the plugin
 */
function xnau_setup_plugin_updater( $plugin_file, $plugin_name )
{
  // add the composer autoload
  require_once dirname( __FILE__ ) . '/vendor/autoload.php';

  /**
   * set up the aux plugin update class
   * 
   * this sets up a check to the xnau plugin packages, and looks for a zip 
   * archive matching the name $this->aux_plugin_name
   * 
   */
  if ( apply_filters( 'pdbaux-enable_auto_updates', true ) && class_exists( 'PDb_Aux_Plugin' ) )
  {

    $update_url = PDb_Aux_Plugin::update_url . '?action=get_metadata&slug=' . $plugin_name;

    Participants_Db::debug_log( __FUNCTION__ . ': initializing Aux Plugin Updater for ' . $plugin_name, 2 );

    $update_checker = \Puc_v4_Factory::buildUpdateChecker(
                    $update_url, $plugin_file, $plugin_name
    );

    add_action( 'participants_database_uninstall', function () use ( $update_checker ) {
      $update_checker->resetUpdateState();
    } );
  }
}

new xnau_plugin_updates();
