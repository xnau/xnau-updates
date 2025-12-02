<?php

/*
 * Plugin Name: xnau Plugin Updates
 * Version: 1.7
 * Description: provides update services to xnau plugins
 * Author: Roland Barker, xnau webdesign
 * Plugin URI: https://xnau.com/the-xnau-plugin-updater/
 * Text Domain: xnau-updates
 * Domain Path: /languages
 * License: GPL3
 * 
 * Copyright 2022 - 2025 Roland Barker xnau webdesign  (email : webdesign@xnau.com)
 */

use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

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
   * @var string name of the plugin list transient
   * 
   * this stores a list of all deactivated xnau plugins
   */
  const deactivated_plugins = 'xnau_deactivated_plugins';
  
  /**
   * @var string name of the transient storing the list of xnau plugins in the repo
   */
  const xnau_repo_plugins = 'xnau_repo_plugins';

  /**
   * 
   */
  public function __construct()
  {
    add_action( 'init', function () {
      self::setup( __FILE__, self::name );
    }, 50 );

    add_action( 'load-plugins.php', [$this, 'register_deactivated_plugins'] );
  }

  /**
   * sets up the update checker for the plugin
   * 
   * this is called by an activated xnau plugin
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
    if ( apply_filters( 'pdbaux-enable_auto_updates', true ) && self::in_xnau_repo( $plugin_file ) )
    {
      $update_checker = self::checker_instance( $plugin_file, $plugin_name );
      
      add_action( 'participants_database_uninstall', function () use ( $update_checker ) {
        $update_checker->resetUpdateState();
      } );
    }
  }

  /**
   * provides the update checker instance
   * 
   * @param string $plugin_file path to the plugin file
   * @param string $plugin_name slug name of the plugin
   * @return YahnisElsts\PluginUpdateChecker\v5p6\Plugin\UpdateChecker
   */
  private static function checker_instance( $plugin_file, $plugin_name )
  {
    return PucFactory::buildUpdateChecker(
                    self::update_url( $plugin_name ), $plugin_file, $plugin_name
            );
  }

  /**
   * register deactivated xnau plugins
   */
  public function register_deactivated_plugins()
  {
    foreach ( $this->deactivated_plugin_list() as $filepath => $plugin_data )
    {
      // instantiating the UpdateChecker instance registers the plugin and checks for an update
      $checker = self::checker_instance( trailingslashit( WP_PLUGIN_DIR ) . $filepath, self::plugin_name( $filepath ) );
    }
  }

  /**
   * provides the list of deactivated xnau plugins
   * 
   * @return array as $plugin_path => $plugin_data
   */
  private function deactivated_plugin_list()
  {
    $xnau_plugin_list = get_transient( self::deactivated_plugins );

    if ( $xnau_plugin_list !== false )
    {
      return $xnau_plugin_list;
    }

    $plugin_list = get_plugins();

    $all_active_plugins = get_option( 'active_plugins' );

    $xnau_plugin_list = [];

    foreach ( $plugin_list as $filepath => $plugin_data )
    {
      /*
       * checking here for inactive xnau plugins that are also in the xnau repo
       */
      if ( strpos( $plugin_data['PluginURI'], 'xnau.com' ) !== false && !in_array( $filepath, $all_active_plugins ) && self::in_xnau_repo( $filepath ) )
      {
        $xnau_plugin_list[$filepath] = trailingslashit( WP_PLUGIN_DIR ) . $filepath;
      }
    }

    set_transient( self::deactivated_plugins, $xnau_plugin_list, DAY_IN_SECONDS );

    return $xnau_plugin_list;
  }

  /**
   * tells if the plugin is in the xnau repo and is accessible
   * 
   * @param string $filepath plugin file path
   * @return bool true if the plugin is in the repo
   */
  private static function in_xnau_repo( $filepath )
  {
    $plugin_name = self::plugin_name( $filepath );

    return self::xnau_repo_response( $plugin_name ) == 200;
  }
  
  /**
   * checks the xnau repo for a plugin file
   * 
   * @param string $plugin_name slug name of the plugin
   * @return int http code
   */
  private static function xnau_repo_response( $plugin_name )
  {
    $repo_list = get_transient( self::xnau_repo_plugins );
    
    if ( is_array( $repo_list ) && isset( $repo_list[$plugin_name] ) )
    {
      return $repo_list[$plugin_name];
    }
    
    if ( $repo_list === false )
    {
      $repo_list = [];
    }
    
    if ( !array_key_exists( $plugin_name, $repo_list ) )
    {
      $repo_list[$plugin_name] = wp_remote_retrieve_response_code( wp_remote_get( self::update_url( $plugin_name ) ) );
      
      set_transient( self::xnau_repo_plugins, $repo_list, DAY_IN_SECONDS );
    }
    
    return $repo_list[$plugin_name];
  }

  /**
   * provides the update URL
   * 
   * @param string $plugin_name
   * @return string URL
   */
  private static function update_url( $plugin_name )
  {
    return self::update_url . '?' . http_build_query( [ 'action' => 'get_metadata', 'slug' => $plugin_name ] );
  }

  /**
   * provides the plugin slug name given the plugin URI
   * 
   * this compensates for some inconsistent naming of the plugin file in 
   * relation to the plugin name
   * 
   * @param string $uri the plugin URI
   * @return string the plugin slug name
   */
  private static function plugin_name( $uri )
  {
    $parts = explode( '/', $uri );

    $name = str_replace( '.php', '', end( $parts ) );
    
    return strpos( $name, 'xnau-' ) !== 0 && strpos( $name, 'pdb-' ) !== 0 ? 'pdb-' . $name : $name;
  }
}

new xnau_plugin_updates();
