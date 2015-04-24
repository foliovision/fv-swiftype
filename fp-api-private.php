<?php

/**
 * Foliopress base class
 */
 
 /*
 Usage:
  
  * Autoupdates
  
  In the plugin object:
  var $strPluginSlug = 'fv-sharing';
  var $strPrivateAPI = 'http://foliovision.com/plugins/';
  
  In the plugin constructor:
  parent::auto_updates();  
  
  * Update notices
  
  In the plugin constructor:
    $this->readme_URL = 'http://plugins.trac.wordpress.org/browser/{plugin-slug}/trunk/readme.txt?format=txt';    
	  add_action( 'in_plugin_update_message-{plugin-dir}/{plugin-file}.php', array( &$this, 'plugin_update_message' ) );
 */

/**
 * Class FVFB_Foliopress_Plugin_Private
 */
class FV_Swiftype_Foliopress_Plugin_Private
{
  function __construct(){
        $this->class_name = sanitize_title( get_class($this) );
        add_action( 'admin_enqueue_scripts', array( $this, 'pointers_enqueue' ) );
        add_action( 'wp_ajax_fv_foliopress_ajax_pointers', array( $this, 'pointers_ajax' ), 999 );
        add_filter( 'plugins_api_result', array( $this, 'changelog_filter' ), 5, 3 );
  }
  
  function auto_updates(){
    if( is_admin() ){
      
      //define $this->strPrivateAPI in main plugin class if the plugin is public
      $this->strPrivateAPI = ( isset($this->strPrivateAPI) && !empty($this->strPrivateAPI) ) ? $this->strPrivateAPI : $this->getUpgradeUrl();

      if( $this->strPrivateAPI !== FALSE ){
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'CheckPluginUpdate' ) );
        add_filter( 'plugins_api', array( $this, 'PluginAPICall' ), 10, 3 );
        add_action( 'update_option__transient_update_plugins',  array( $this, 'CheckPluginUpdateOld' ) );
      }
    }         
  }
  
  function http_request_args( $params ) {
    $aArgs = func_get_args();
    $url = $aArgs[1];
  
    if( stripos($url,'foliovision.com') === false ) {
      return $params;
    }
  
    add_filter( 'https_ssl_verify', '__return_false' );
    return $params;
  }    
  
  function http_request($method, $url, $data = '', $auth = '', $check_status = true) {
      $status = 0;
      $method = strtoupper($method);
      
      if (function_exists('curl_init')) {
          $ch = curl_init();
          
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)');
          @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
          curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
          curl_setopt($ch, CURLOPT_TIMEOUT, 10);
          
          switch ($method) {
              case 'POST':
                  curl_setopt($ch, CURLOPT_POST, true);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                  break;
              
              case 'PURGE':
                  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
                  break;
          }
          
          if ($auth) {
              curl_setopt($ch, CURLOPT_USERPWD, $auth);
          }
          
          $contents = curl_exec($ch);
          
          $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          
          curl_close($ch);
      } else {
          $parse_url = @parse_url($url);
          
          if ($parse_url && isset($parse_url['host'])) {
              $host = $parse_url['host'];
              $port = (isset($parse_url['port']) ? (int) $parse_url['port'] : 80);
              $path = (!empty($parse_url['path']) ? $parse_url['path'] : '/');
              $query = (isset($parse_url['query']) ? $parse_url['query'] : '');
              $request_uri = $path . ($query != '' ? '?' . $query : '');
              
              $request_headers_array = array(
                  sprintf('%s %s HTTP/1.1', $method, $request_uri), 
                  sprintf('Host: %s', $host), 
                  sprintf('User-Agent: %s', W3TC_POWERED_BY), 
                  'Connection: close'
              );
              
              if (!empty($data)) {
                  $request_headers_array[] = sprintf('Content-Length: %d', strlen($data));
              }
              
              if (!empty($auth)) {
                  $request_headers_array[] = sprintf('Authorization: Basic %s', base64_encode($auth));
              }
              
              $request_headers = implode("\r\n", $request_headers_array);
              $request = $request_headers . "\r\n\r\n" . $data;
              $errno = null;
              $errstr = null;
              
              $fp = @fsockopen($host, $port, $errno, $errstr, 10);
              
              if (!$fp) {
                  return false;
              }
              
              $response = '';
              @fputs($fp, $request);
              
              while (!@feof($fp)) {
                  $response .= @fgets($fp, 4096);
              }
              
              @fclose($fp);
              
              list($response_headers, $contents) = explode("\r\n\r\n", $response, 2);
              
              $matches = null;
              
              if (preg_match('~^HTTP/1.[01] (\d+)~', $response_headers, $matches)) {
                  $status = (int) $matches[1];
              }
          }
      }
      
      if (!$check_status || $status == 200) {
          return $contents;
      }
      
      return false;
  }
  
  
  function is_min_wp( $version ) {
    return version_compare( $GLOBALS['wp_version'], $version. 'alpha', '>=' );
  }


  private function check_license_remote( ) {
	
    if( !isset($this->strPluginSlug) || empty($this->strPluginSlug)
       || !isset($this->version) || empty($this->version)
       || !isset($this->license_key) || $this->license_key === FALSE  )
      return false;
        
    $args = array(
      'body' => array( 'plugin' => $this->strPluginSlug, 'version' => $this->version, 'type' => home_url(), 'key' => $this->license_key, 'action' => 'check' ),
      'timeout' => 20,
      'user-agent' => $this->strPluginSlug.'-'.$this->version
    );
    $resp = wp_remote_post( 'http://foliovision.com/?fv_remote=true', $args );

    if( !is_wp_error($resp) && isset($resp['body']) && $resp['body'] && $data = json_decode( preg_replace( '~[\s\s]*?<FVFLOWPLAYER>(.*?)</FVFLOWPLAYER>[\s\s]*?~', '$1', $resp['body'] ) ) ) {
      return $data;
    } else {
      return false;  
    }
  }
  
  // set force = true to delete transient and recheck license
  function setLicenseTransient( $force = false ){
    $strTransient = $this->strPluginSlug . '_license';
    
    if( $force )
      delete_transient( $strTransient );
    
    //is transiet set?
    if ( false !== ( $aCheck = get_transient( $strTransient ) ) )
      return;
    
    $aCheck = $this->check_license_remote( );
    if( $aCheck ) {
      set_transient( $strTransient, $aCheck, 60*60*24 );
    } else {
      set_transient( $strTransient, json_decode(json_encode( array('error' => 'Error checking license') ), FALSE), 60*10 );
    }
  }
 

  function checkLicenseTransient(){
    $strTransient = $this->strPluginSlug . '_license';
    
    $aCheck = get_transient( $strTransient );
     if( isset($aCheck->valid) && $aCheck->valid) 
	return TRUE;
     else
	return FALSE;
  }

  function getUpgradeUrl(){
    $strTransient = $this->strPluginSlug . '_license';
 
    $aCheck = get_transient( $strTransient );
    if( isset($aCheck->upgrade) && !empty($aCheck->upgrade) ) 
	return $aCheck->upgrade;
     else
	return FALSE;
  }
  
  
/// ================================================================================================
/// Custom plugin repository
/// ================================================================================================

/*
Uses:
$this->strPluginSlug - this has to be in plugin object
$this->strPrivateAPI - also

*/

   private function PrepareRequest( $action, $args ){
      global $wp_version;

      return array(
         'body' => array(
            'action' => $action, 
            'request' => serialize($args),
            'api-key' => md5(get_bloginfo('url'))
         ),
         'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
         'sslverify' => false
      );
   }

   public function CheckPluginUpdate( $checked_data ){
      $plugin_path = basename(dirname(__FILE__)).'/'.$this->strPluginSlug.'.php';
      if( !empty( $checked_data->checked ) ){
        $request_args = array(
          'slug' => $this->strPluginSlug,
          'version' => $checked_data->checked[$plugin_path],
        );
      }
      else{
        $cache_plugins = get_plugins();
        if( empty($cache_plugins[$plugin_path]['Version']) ){
          return $checked_data;
        }
        $request_args = array(
          'slug' => $this->strPluginSlug,
          'version' => $cache_plugins[$plugin_path]['Version'],
        );
      }

      $request_string = $this->PrepareRequest( 'basic_check', $request_args );
      
      $raw_response = get_transient( $this->strPluginSlug.'_fp-private-updates-api' );
      if( !$raw_response ){
        // Start checking for an update
        $raw_response = wp_remote_post( $this->strPrivateAPI, $request_string );
        set_transient( $this->strPluginSlug.'_fp-private-updates-api', $raw_response, 3600 );
        //echo "<!--CheckPluginUpdate raw_response ".var_export($raw_response,true)." -->\n\n";
      }
      
      if( !is_wp_error( $raw_response ) && ( $raw_response['response']['code'] == 200 ) ) {
        $response = @unserialize( $raw_response['body'] );
      }

      if( isset($response->version) && version_compare( $response->version, $request_args['version'] ) == 1 ){
         if( is_object( $response ) && !empty( $response ) ) // Feed the update data into WP updater
            $checked_data->response[ basename(dirname(__FILE__)).'/'.$this->strPluginSlug.'.php'] = $response;
      }
      
      return $checked_data;
   }

   public function CheckPluginUpdateOld( $aData = null ){
      $aData = get_transient( "update_plugins" );
      $aData = $this->CheckPluginUpdate( $aData );
      set_transient( "update_plugins", $aData );
      
      if( function_exists( "set_site_transient" ) ) set_site_transient( "update_plugins", $aData );
   }   

   public function PluginAPICall( $def, $action, $args ){
      if( !isset($args->slug) || $args->slug != $this->strPluginSlug ) return $def;

      // Get the current version
      $plugin_info = get_site_transient( 'update_plugins' );
      $current_version = ( isset($plugin_info->response[basename(dirname(__FILE__)).'/'.$this->strPluginSlug.'.php']) ) ? $plugin_info->response[basename(dirname(__FILE__)).'/'.$this->strPluginSlug.'.php'] : false;
      $args->version = $current_version;

      $request_string = $this->PrepareRequest( $action, $args );

      $request = wp_remote_post( $this->strPrivateAPI, $request_string );

      if( is_wp_error( $request ) ) {
         $res = new WP_Error( 'plugins_api_failed', __( 'An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>' ), $request->get_error_message() );
      }else{
         $res = unserialize( $request['body'] );
         if( $res === false ) $res = new WP_Error( 'plugins_api_failed', __( 'An unknown error occurred' ), $request['body'] );
      }

      return $res;
   }
   
  function pointers_ajax() {
		if( $this->pointer_boxes ) { 	
  		foreach( $this->pointer_boxes AS $sKey => $aPopup ) {
  			if( $_POST['key'] == $sKey ) {
					check_ajax_referer($sKey);
  			}
  		}
  	}
  }
   
   
  function pointers_enqueue() {
  	global $wp_version;
		if( ! current_user_can( 'manage_options' ) || ( isset($this->pointer_boxes) && count( $this->pointer_boxes ) == 0 ) || version_compare( $wp_version, '3.4', '<' ) ) {
			return;
		}

		/*$options = get_option( 'wpseo' );
		if ( ! isset( $options['yoast_tracking'] ) || ( ! isset( $options['ignore_tour'] ) || ! $options['ignore_tour'] ) ) {*/
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'jquery-ui' );
			wp_enqueue_script( 'wp-pointer' );
			wp_enqueue_script( 'utils' );
		/*}
		if ( ! isset( $options['tracking_popup'] ) && ! isset( $_GET['allow_tracking'] ) ) {*/
			
		/*}
		else if ( ! isset( $options['ignore_tour'] ) || ! $options['ignore_tour'] ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'intro_tour' ) );
			add_action( 'admin_head', array( $this, 'admin_head' ) );
		}  */
  	add_action( 'admin_print_footer_scripts', array( $this, 'pointers_init_scripts' ) );		
  }  
  
  
  private function get_readme_url_remote( ) {
	
    if( !isset($this->strPluginSlug) || empty($this->strPluginSlug) || !isset($this->version) || empty($this->version) )
      return false;
        
    $args = array(
      'body' => array( 'plugin' => $this->strPluginSlug, 'version' => $this->version, 'type' => home_url() ),
      'timeout' => 20,
      'user-agent' => $this->strPluginSlug.'-'.$this->version
    );
    $resp = wp_remote_post( 'http://foliovision.com/?fv_remote=true&readme=1', $args );
    
    if( !is_wp_error($resp) && isset($resp['body']) && $resp['body'] ) {
      return $resp['body'];
    } else {
      return false;  
    }
  }
  
  
  function changelog_filter( $res, $action, $args ){
    
    if( !isset( $args->slug ) || $args->slug != $this->strPluginSlug  )
      return $res;
    
    $data = $this->get_readme_url_remote();
    if( !$data )
      return $res;

    $plugin_data = get_plugin_data( dirname( __FILE__ ) . '/' . $this->strPluginSlug . '.php' );
    
    $pluginReq = preg_match( '~Requires at least:\s*([0-9.]*)~', $data, $reqMatch ) ? $reqMatch[1] : false;
    $pluginUpto = preg_match( '~Tested up to:\s*([0-9.]*)~', $data, $uptoMatch ) ? $uptoMatch[1] : false;
    
    $changelogOut = '';
    if( preg_match('~==\s*Changelog\s*==(.*)~si', $data, $match) ){
      $changelogPart = preg_replace('~==.*~','',$match[1]);
      $version = preg_match('~=\s*([0-9.]+).*=~', $changelogPart, $verMatch ) ? $verMatch[1] : false;
      
        $changelog = (array) preg_split('~[\r\n]+~', trim($changelogPart));
        $ul = false;
        foreach ($changelog as $index => $line) {
            if (preg_match('~^\s*\*\s*~', $line)) {
                if (!$ul) {
                    $changelogOut .= '<ul style="list-style: disc; margin-left: 20px;">';
                    $ul = true;
                }
                $line = preg_replace('~^\s*\*\s*~', '', htmlspecialchars($line));
                $changelogOut .= '<li style="width: 50%; margin: 0; float: left; ' . ($index % 2 == 0 ? 'clear: left;' : '') . '">' . $line . '</li>';
            } else {
                if ($ul) {
                    $changelogOut .= '</ul><div style="clear: left;"></div>';
                    $ul = false;
                }
                
                $strong = $strongEnd = '';
                if( preg_match('~^=(.*)=$~', $line ) ){
                  $strong = '<strong>';
                  $strongEnd = '</strong>';
                  $line = preg_replace('~^=(.*)=$~', '$1', $line );
                }
                $changelogOut .= '<p style="margin: 5px 0;">' .$strong. htmlspecialchars($line) .$strongEnd. '</p>';
            }
        }
        if ($ul) {
            $changelogOut .= '</ul><div style="clear: left;"></div>';
        }
        $changelogOut .= '</div>';
    }
    
    $res = (object) array(
       'name' => $plugin_data['Name'],
       'slug' => false,
       'version' => $version,
       'author' => $plugin_data['Author'],
       'requires' => $pluginReq,
       'tested' => $pluginUpto,
       'homepage' => $plugin_data['PluginURI'],
       'sections' => 
      array (
        'support' => 'Use support forum at <a href="https://foliovision.com/support/">foliovison.com/support</a>',
        'changelog' => $changelogOut,
      ),
       'donate_link' => NULL
    );
      
    return $res;
    
  }
  
  
  //notification boxes
   function pointers_init_scripts() {
  	if( !isset($this->pointer_boxes) || !$this->pointer_boxes ) {
  		return;
  	}
  	
  	foreach( $this->pointer_boxes AS $sKey => $aPopup ) {
			$sNonce = wp_create_nonce( $sKey );
	
			$content = '<h3>'.$aPopup['heading'].'</h3>';
			if( stripos( $aPopup['content'], '</p>' ) !== false ) {
				$content .= $aPopup['content'];
			} else {
				$content .= '<p>'.$aPopup['content'].'</p>';
			}
			
			$position = ( isset($aPopup['position']) ) ? $aPopup['position'] : array( 'edge' => 'top', 'align' => 'center' );
			
			$opt_arr = array(	'content'  => $content, 'position' => $position );
				
			$function2 = $this->class_name.'_store_answer("'.$sKey.'", "false","' . $sNonce . '")';
			$function1 = $this->class_name.'_store_answer("'.$sKey.'", "true","' . $sNonce . '")';
			
			?>
<script type="text/javascript">
	//<![CDATA[
		function <?php echo $this->class_name; ?>_store_answer(key, input, nonce) {
			var post_data = {
				action        : 'fv_foliopress_ajax_pointers',
				key						:	key, 
				value					: input,
				_ajax_nonce   : nonce
			}
			jQuery.post(ajaxurl, post_data, function () {
				jQuery('#wp-pointer-0').remove();	//	todo: does this really work?
			});
		}
	//]]>
</script>					
			<?php
	
			$this->pointers_print_scripts( $sKey, $aPopup['id'], $opt_arr, $aPopup['button2'], $aPopup['button1'], $function2, $function1 );
		}
  }
  
  
  
    function pointers_print_scripts( $id, $selector, $options, $button1, $button2 = false, $button2_function = '', $button1_function = '' ) {
		?>
		<script type="text/javascript">
			//<![CDATA[
			(function ($) {
				var <?php echo $id; ?>_pointer_options = <?php echo json_encode( $options ); ?>, <?php echo $id; ?>_setup;

				<?php echo $id; ?>_pointer_options = $.extend(<?php echo $id; ?>_pointer_options, {
					buttons: function (event, t) {
						button = jQuery('<a id="pointer-close" style="margin-left:5px" class="button-secondary">' + '<?php echo addslashes($button1); ?>' + '</a>');
						button.bind('click.pointer', function () {
							t.element.pointer('close');
						});
						return button;
					},
					close  : function () {
					}
				});

				<?php echo $id; ?>_setup = function () {
					$('<?php echo $selector; ?>').pointer(<?php echo $id; ?>_pointer_options).pointer('open');
					<?php if ( $button2 ) { ?>
					jQuery('#pointer-close').after('<a id="pointer-primary" class="button-primary">' + '<?php echo addslashes($button2); ?>' + '</a>');
					jQuery('#pointer-primary').click(function () { <?php echo $button1_function; ?> });
					jQuery('#pointer-close').click(function () { <?php echo $button2_function; ?>	});
					<?php } ?>
				};

				if(<?php echo $id; ?>_pointer_options.position && <?php echo $id; ?>_pointer_options.position.defer_loading)
					$(window).bind('load.wp-pointers', <?php echo $id; ?>_setup);
				else
					$(document).ready(<?php echo $id; ?>_setup);
			})(jQuery);
			//]]>
		</script>
    <?php
    }     
  

}
