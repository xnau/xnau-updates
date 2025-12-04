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

    add_action( 'init', [$this, 'register_deactivated_plugins'] );

    register_uninstall_hook( __FILE__, ['xnau_plugin_updates', 'uninstall'] );
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
   * 
   * @global string $pagenow
   */
  public function register_deactivated_plugins()
  {
    global $pagenow;
    
    if ( is_admin() && in_array( $pagenow, ['plugins.php', 'plugin-install.php', 'admin-ajax.php'] ) ) :
    
    // add the composer autoload
    require_once dirname( __FILE__ ) . '/vendor/autoload.php';
    
    foreach ( $this->deactivated_plugin_list() as $filepath => $plugin_data )
    {
      $package_name = self::package_name( $filepath );

      if ( self::can_register( $package_name ) )
      {
        // instantiating the UpdateChecker instance registers the plugin and checks for an update
        $checker = self::checker_instance( trailingslashit( WP_PLUGIN_DIR ) . $filepath, $package_name );

//        $info = $checker->requestInfo();
//        /** @var YahnisElsts\PluginUpdateChecker\v5p6\Plugin\PluginInfo $info */
//
//        error_log(__METHOD__.'<br>plugin: '. $info->slug .'<br>repo version: '. $info->version . '<br>installed: '. $checker->getInstalledVersion() );
      }
    }
    
    $this->setup_invalidation();
    
    endif;
  }

  /**
   * provides the list of deactivated xnau plugins
   * 
   * @return array as $local_plugin_path => $absolute_plugin_path
   */
  private function deactivated_plugin_list()
  {
    $xnau_plugin_list = get_transient( self::deactivated_plugins );

    if ( $xnau_plugin_list !== false )
    {
      return $xnau_plugin_list;
    }

    $plugin_list = get_plugins();

    $xnau_plugin_list = [];

    foreach ( $plugin_list as $filepath => $plugin_data )
    {
      $package_name = self::package_name( $filepath );
      /*
       * checking here for inactive xnau plugins that are also in the xnau repo
       */
      if ( strpos( $plugin_data['PluginURI'], 'xnau.com' ) !== false && !in_array( $package_name, $this->all_active_plugins() ) && self::in_xnau_repo( $filepath ) )
      {
        $xnau_plugin_list[$filepath] = trailingslashit( WP_PLUGIN_DIR ) . $filepath;
      }
    }

    set_transient( self::deactivated_plugins, $xnau_plugin_list, DAY_IN_SECONDS );

    return $xnau_plugin_list;
  }

  /**
   * provides the list of all activated plugins
   * 
   * @return array as $i => $package_name
   */
  private function all_active_plugins()
  {
    $cachekey = 'pdb-active_plugin_list';
    
    $active_plugin_list = wp_cache_get( $cachekey );
    
    if ( $active_plugin_list !== false )
    {
      return $active_plugin_list;
    }
    
    $active_plugin_list = is_multisite() ? wp_get_active_network_plugins() : get_option( 'active_plugins' );

    // convert the path to the package name
    $package_list = array_map( 'xnau_plugin_updates::package_name', $active_plugin_list );
    
    wp_cache_set($cachekey, $package_list, '', 10);
    
    return $package_list;
  }

  /**
   * checks if the plugins can be registered
   * 
   * this checks if a plugin by the same name has already been registered
   * 
   * @param string $package_name
   * @return bool true if the plugin has not been registered
   */
  private static function can_register( $package_name )
  {
    // we're checking for a filter that is set when a plugin registers
    return apply_filters( 'puc_is_slug_in_use-' . $package_name, false ) === false;
  }

  /**
   * sets up the cache invalidation
   */
  private function setup_invalidation()
  {
    $action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

    if ( in_array( $action, ['activate', 'deactivate'] ) )
    {
      $this->clear_deactivated_list();
    }
  }

  /**
   * tells if the plugin is in the xnau repo and is accessible
   * 
   * @param string $filepath plugin file path
   * @return bool true if the plugin is in the repo
   */
  private static function in_xnau_repo( $filepath )
  {
    $package = self::package_name( $filepath );

    return self::xnau_repo_response( $package ) == 200;
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
    return self::update_url . '?' . http_build_query( ['action' => 'get_metadata', 'slug' => $plugin_name] );
  }

  /**
   * provides the plugin package name given the plugin URI
   * 
   * 
   * @param string $uri the plugin URI
   * @return string the plugin slug name
   */
  private static function package_name( $uri )
  {
    $parts = explode( '/', $uri );

    array_pop( $parts );

    return end( $parts );
  }

  /**
   * clears the deactivated plugins list
   */
  private function clear_deactivated_list()
  {
    delete_transient( self::deactivated_plugins );
  }

  /**
   * uninstalls the plugin
   * 
   * clears transients on uninstall
   */
  public static function uninstall()
  {
    delete_transient( self::deactivated_plugins );
    delete_transient( self::xnau_repo_plugins );
  }
}

new xnau_plugin_updates();
