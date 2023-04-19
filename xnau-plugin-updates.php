<?php

/*
 * Plugin Name: xnau Plugin Updates
 * Version: 1.5.1
 * Description: provides update services to xnau plugins
 * Author: Roland Barker, xnau webdesign
 * Plugin URI: https://xnau.com/shop/combo-multisearch-plugin/
 * Text Domain: xnau-updates
 * Domain Path: /languages
 * License: GPL3
 * 
 * Copyright 2022 Roland Barker xnau webdesign  (email : webdesign@xnau.com)
 */
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class xnau_plugin_updates {

  /**
   * @var string the plugin slug name
   */
  const name = 'xnau-plugin-updates';

  /**
   * @var string  URL for the aux plugin updates
   */
  const update_url = 'https://xnau.com/xnau-updates/';

  /**
   * 
   */
  public function __construct()
  {
    add_action( 'init', function () {
      self::setup( __FILE__, self::name );
    }, 50 );
  }

  /**
   * sets up the update checker for the plugin
   * 
   * @param string $plugin_file absolute path
   * @param string $plugin_name slug name of the plugin
   */
  public static function setup( $plugin_file, $plugin_name )
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
      
      $update_url = self::update_url . '?action=get_metadata&slug=' . $plugin_name;

      // Participants_Db::debug_log( __FUNCTION__ . ': initializing Aux Plugin Updater for ' . $plugin_name, 3 );

      $update_checker = PucFactory::buildUpdateChecker(
                      $update_url, $plugin_file, $plugin_name
      );

      add_action( 'participants_database_uninstall', function () use ( $update_checker ) {
        $update_checker->resetUpdateState();
      } );
    }
  }

}

new xnau_plugin_updates();