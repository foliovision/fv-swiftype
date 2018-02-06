<?php
/*
Plugin Name: FV Swiftype
Description: Use Swiftype engine for your search.
Author: Foliovision
Version: 0.3.6
Author URI: http://www.foliovision.com
*/


require_once( dirname(__FILE__).'/fp-api.php' );
if( class_exists('FV_Swiftype_Foliopress_Plugin') ) :

define( 'SWIFTYPE_VERSION', 'fv0.1.2');

class FV_Swiftype extends FV_Swiftype_Foliopress_Plugin {
  
	var $version = '0.3.5';  
  var $fv_swiftype_response;
  var $aOptions;
  
  
  
  function __construct() {
    
    $this->aOptions = get_option( 'fv_swiftype', array() );
    
    include( dirname(__FILE__).'/includes/class-swiftype-client.php');

    //include( dirname(__FILE__).'/includes/swiftype.php');
        
    if( !$this->is_test() || isset($_GET['fv_swiftype']) ) {
      add_action( 'pre_get_posts', array( $this, 'check_query' ), 11 );
      add_filter( 'the_posts', array( $this, 'query_insert_results' ), 10, 2 );
      add_filter( 'post_link', array( $this, 'post_link' ), 999999, 2 );
      add_action( 'wp_footer', array( $this, 'debug_sql' ) );
    }
        
    add_filter( 'post_class', array( $this, 'index_include_content' ), 99999 );
    add_filter( 'comment_class', array( $this, 'index_include_content' ), 99999 );
    add_action( 'wp_head', array( $this, 'index_featured_image' ) );
    add_action( 'wp_head', array( $this, 'index_content_type' ) );
    add_action( 'wp_footer', array( $this, 'index_not_important' ), 0 );
    
    add_action( 'admin_init', array( $this, 'options_save' ) );
    add_action( 'admin_menu', array($this, 'admin_menu') );
    add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    
    add_filter( 'robots_txt', array( $this, 'robots_txt' ) );
    
    add_filter( 'post_thumbnail_html', array( $this, 'post_thumbnail_html'), 999, 4 );
  }
  
  
  
  
  function admin_menu() {
    add_options_page( 'FV Swiftype', 'FV Swiftype', 'manage_options', 'fv_swiftype', array($this, 'options_panel') );
  }
  
  
  
  
  function admin_notices() {
    if( !$this->have_api_key() ) {
      echo '<div class="error">
       <p>FV Swiftype requires your Swiftype API key to become operational. Please enter it <a href="'.site_url('wp-admin/options-general.php?page=fv_swiftype').'">here</a>.</p>
    </div>';  
    }
    
    if( $this->have_api_key() && !$this->have_engine() ) {
      echo '<div class="error">
       <p>FV Swiftype needs you to pick your search engine. Please enter it <a href="'.site_url('wp-admin/options-general.php?page=fv_swiftype').'">here</a>.</p>
    </div>';  
    }    

    if( isset($this->aOptions['errors']) ) {
      echo '<div class="error">
       <p>'.$this->aOptions['errors'].'</p>
    </div>';  
    }
    
    if( isset($_GET['saved']) ) {
      echo '<div class="updated">
       <p>Settings saved!</p>
    </div>';  
    }
    
    if( isset($_GET['reset']) ) {
      echo '<div class="updated">
       <p>API key reset!</p>
    </div>';  
    }
    
    if( isset($_GET['dismissed']) ) {
      echo '<div class="updated">
       <p>Error message dismissed!</p>
    </div>';  
    }    
    
    if( current_user_can('edit_posts') && get_option('fv_swiftype_last_error') ) {
      echo '<div class="error">
       <p>Looks like there is a problem with your Swiftype configuration (<a href="#" onclick="jQuery(this).parents(\'div\').find(\'pre\').toggle(); return false">show details</a> <a href="'.site_url('wp-admin/options-general.php?page=fv_swiftype&dismiss').'">dismiss</a>).</p>
       <pre style="display: none">'.get_option('fv_swiftype_last_error').'</pre>
    </div>';  
    }         
  }  

  
  
  
  function check_query( $query ) {
    if( !is_admin() &&  !empty($query->query['s']) && ( !function_exists('bbp_is_search_results') || !bbp_is_search_results() ) ) {

      $iPerPage = $query->get( 'posts_per_page');
      if( !$iPerPage ) {
        $iPerPage = get_option('posts_per_page');
      }
      
      $tStart = microtime(true);
      
      $this->init_api();
      
      if( !$this->client ) {
        $this->error( "engine init failed!" );        
        return;
      }
      
      $engine_id = $this->aOptions['engine_id'];
            
      $aArgs = array(
                'search_fields' => array(
                    "page" => array( )
                  ),
                'per_page' => $iPerPage,
                'page' => isset($query->query['paged']) ? $query->query['paged'] : 1,
                'fetch_fields' => array(
                    "page" => array( "title", "url", "published_at", "highlight", "sections", "type", "image" )
                    )
                );
      
      if( $this->aOptions['exclude'] ) {
        $aArgs['filters'] = array( 'page' => array( 'type' => array_values($this->get_search_types( array('searchable'=>true) )) ) );
      }
      
      try {      
        $aResponse = $this->client->search(
                      $engine_id,
                      'page',
                      $query->query['s'],
                      $aArgs
                      );          
        
        $this->debug( "response time ".(microtime(true) - $tStart ) );
        $this->debug( "response ".var_export($aResponse,true) );
        
      } catch( Exception $e ) {
        $this->error( "fail: ".var_export($e,true) );
        return;
      }
          
      //  Swiftype WP engine
      if( isset($aResponse['records']['posts']) && count($aResponse['records']['posts']) ) {  
        $aIds = array();
        foreach( $aResponse['records']['posts'] AS $aPost ) {
          $aIds[] = $aPost['external_id'];
        }
        
        
        
        if( count($aIds) > 0 ) {
          $query->set('s', '' );
          $query->set('post__in', $aIds );
        }
        
        //echo "<!--FVSwiftype post IDs ".var_export($aIds,true)."-->\n";
        //echo "<!--FVSwiftype WP_Query ".var_export($query,true)."-->\n";
        
      } else if( isset($aResponse['records']['page']) && count($aResponse['records']['page']) ) {              
        $this->fv_swiftype_response[$query->query['s']] = $aResponse;
        
        //$query->set('s', '' );  //  todo: fix performance
        $query->set('post__in', array(-1) );
        $query->set('fv_swiftype_s', $query->query['s'] );    
  
      } else {
        $this->debug( "got no results, fall back to WP search" );
        
      }
      
      
      
    }
    

  }
  
  
  
  
  function debug( $msg ) {
    if( isset($this->aOptions['debug']) && $this->aOptions['debug'] ) {
      echo "<!--FVSwiftype ".str_replace( '-->', '-- >', $msg )."-->\n";
    }
  }
  
  
  
  
  function debug_sql() {
    global $wpdb;
    
    //var_dump($wpdb->queries);
  }
  
  
  
  
  function error( $msg ) {
    if( stripos($msg,'Operation timed out') !== false ) {
      $aErrors = get_option( 'fv_swiftype_last_error_operation_timed_out', array() );
      $aErrors[date('r')] = $msg;
      update_option( 'fv_swiftype_last_error_operation_timed_out', $aErrors );
      
    } else if( stripos($msg,'name lookup timed out') !== false ) {
      $aErrors = get_option( 'fv_swiftype_last_error_name_lookup_timed_out', array() );
      $aErrors[date('r')] = $msg;
      update_option( 'fv_swiftype_last_error_name_lookup_timed_out', $aErrors );
      
    } else if( stripos($msg,'Resolving timed out') !== false ) {
      $aErrors = get_option( 'fv_swiftype_last_error_resolving_timed_out', array() );
      $aErrors[date('r')] = $msg;
      update_option( 'fv_swiftype_last_error_resolving_timed_out', $aErrors );
      
    } else if( stripos($msg,'Could not resolve host') !== false ) {
      $aErrors = get_option( 'fv_swiftype_last_error_could_not_resolve_host', array() );
      $aErrors[date('r')] = $msg;
      update_option( 'fv_swiftype_last_error_could_not_resolve_host', $aErrors );
      
    } else {
      update_option( 'fv_swiftype_last_error', $msg );
    }
    
    if( isset($this->aOptions['debug']) && $this->aOptions['debug'] ) {
      echo "<!--FVSwiftype ".str_replace( '-->', '-- >', $msg )."-->\n";
    }
  }  
  
  
  
  
  function get_excerpt( $text ) {
    $text = str_replace( 'em>', 'strong>', $text );
    
    $num_words = apply_filters( 'excerpt_length', 55 );
    
    if ( 'characters' == _x( 'words', 'word count: words or characters?' ) && preg_match( '/^utf\-?8$/i', get_option( 'blog_charset' ) ) ) {
      $text = trim( preg_replace( "/[\n\r\t ]+/", ' ', $text ), ' ' );
      preg_match_all( '/./u', $text, $words_array );
      $words_array = array_slice( $words_array[0], 0, $num_words + 1 );
      $sep = '';
    } else {
      $words_array = preg_split( "/[\n\r\t ]+/", $text, $num_words + 1, PREG_SPLIT_NO_EMPTY );
      $sep = ' ';
    }
    
    if ( count( $words_array ) > $num_words ) {
      array_pop( $words_array );
      $text = implode( $sep, $words_array );
      $text = $text . apply_filters( 'excerpt_more', ' ' . '[&hellip;]' );
    } else {
      $text = implode( $sep, $words_array );
    }
    
    return $text;
  }
  
  
  
  
  function get_search_types( $aArgs ) {
    
    $aArgs = wp_parse_args( $aArgs, array( 'group' => false, 'searchable' => false ) );
    extract($aArgs);
    
    $aPostTypes = get_post_types( array( 'exclude_from_search' => false ) );
    $aPostTypes['other'] = '';
    $aTaxonomies = get_taxonomies( array( 'public' => true ) );
    $aTaxonomies['archive'] = 'archive';
    
    $aSearchTypes = array_merge($aPostTypes,$aTaxonomies);
    
    if( $searchable ) {
      $aExclude = array();
      if( $this->aOptions['exclude'] ) {
        foreach( $this->aOptions['exclude'] AS $group_name => $aTypes ) {
          $aExclude = array_merge( $aExclude, $aTypes );
        }
      }
      
      if( $aExclude ) {
        foreach( $aSearchTypes AS $key => $value ) {
          if( in_array($value,array_keys($aExclude)) ) {
            unset($aSearchTypes[$key]);
          }
        }
      }
    }
    
    if( $group ) {
      return array( 'posts' => $aPostTypes, 'archives' => $aTaxonomies );
    } else {
      return $aSearchTypes;
    }
   
    

  }
  
  
  
  
  function have_api_key() {
    if( !isset($this->aOptions['api_key']) || trim($this->aOptions['api_key']) == '' ) {
      return false;
    }
    
    return true;
  }
  
  
  
  
  function have_engine() {
    if( !isset($this->aOptions['engine_id']) || trim($this->aOptions['engine_id']) == '' ) {
      return false;
    }
    
    return true;
  }  
  
  
  
  
  function index_content_type() {
    if( is_singular() ) {
      global $post;
      $sType = $post->post_type;
      $iPop = 3;
    } else if( is_category() ) {
      $sType = 'category';
      $iPop = 2;
    } else if( is_tag() ) {
      $sType = 'post_tag';
      $iPop = 1;
    } else if( is_tax() ) {
      $term = get_queried_object();
      $sType = $term->taxonomy;
      $iPop = 2;
    } else if( is_archive() ) {
      $sType = 'archive';
      $iPop = 1;
    } else {
      $sType = 'page';
      $iPop = 2;
    }
    
    ?>
    <meta property='st:type' content='<?php echo $sType; ?>' />
    <meta property='st:popularity' content='<?php echo $iPop; ?>' />
    <?php
  }  
  
  
  
  
  function index_featured_image() {
    if( 1<0 && !is_singular() ) {  //  todo: experimental!
      ?><meta property='st:published_at' content='2000-05-21T00:48:25+00:00' />
      <?php
      return;
    }
  
    global $post;
    
    ?><meta property='st:published_at' content='<?php echo date( 'c', strtotime($post->post_date) ); ?>' />
    <?php
    
    $aImage = array();
    
    if( $thumb = get_the_post_thumbnail($post->ID,'large') ) {
      if( !empty($thumb) ) $aImage[] = $thumb;
    } 
    
    if( 0 != preg_match_all( '~<img[^>]*>~', $post->post_content, $aImgMatches ) ){
      $aImage = array_merge($aImage, $aImgMatches[0]);
    }
    
    if( wp_attachment_is_image($post->ID) ){
      $aAttachment = wp_get_attachment_image_src($post->ID, 'large');
      $aImage = array_merge($aImage, array($aAttachment[0]));
    }
    
    
    if( !empty($aImage) ) {
      $aImage = preg_replace( '~^[\s\S]*src=["\'](.*?)["\'][\s\S]*$~', '$1', $aImage );
      
      foreach( $aImage as $key => $singleImg ) {
        if( preg_match('~^/[^/]~', $singleImg) ) {
            $aImage[$key] = home_url($singleImg);
        }
      }
    }
    
    if( isset($aImage[0]) ) : ?>
    <meta property='st:image' content='<?php echo $aImage[0]; ?>' />
    <?php endif;
    
    global $post;
    
    if( is_singular() && isset($post->ID) ) {
      $aCats = wp_get_post_categories( $post->ID );
      if( count($aCats) > 0 ) {
        foreach( $aCats AS $cat_id ) {
          $objCat = get_category($cat_id);
          ?>
          <meta class="swiftype" name="tag" data-type="string" content="<?php echo esc_attr($objCat->name); ?>" />
          <?php
        }
      }
    }
  }
  
  
  
  
  function index_include_content( $aClasses ) {
    //if( is_singular() ) {
      $aClasses[] = '" data-swiftype-index="true';
    //}
    return $aClasses;
  }
  
  
  
  
  function index_not_important() {
    /*if( !is_singular() ) {
      ?><div data-swiftype-index="true"></div><?php
    }*/
  }
  
  
  
  
  function init_api() {
    if( !$this->have_api_key() ) {
      return false;
    }
    
    $this->client = new FVSwiftypeClient();
    $this->client->set_api_key( $this->aOptions['api_key'] );
  }
  
  
  
  
  
  function is_test() {
    if( isset($this->aOptions['test_mode']) && $this->aOptions['test_mode'] ) {
      return true;
    }
    return false;
  }
  
  
  
  
  function options_panel() {
    $options = $this->aOptions;
    //var_dump($options);
?>

<div class="wrap">
  <div style="position: absolute; right: 20px; margin-top: 5px">
  <a href="https://foliovision.com/support" target="_blank" title="Documentation"><img alt="visit foliovision" src="http://foliovision.com/shared/fv-logo.png" /></a>
  </div>
  <div>
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2>FV Swiftype</h2>
  </div>
    <div id="poststuff" class="ui-sortable">
      <form id="fv_swiftype_form" method="post" action="">
        
        <div class="postbox">
          <h3>
          <?php _e('Search Engine Settings') ?>
          </h3>                
          <?php wp_nonce_field('fv_swiftype');
          $sShowSave = false;
          ?>        
          <div class="inside">
            <table class="form-table">
              <?php if( !$this->have_engine() ) : ?>
                <tr>
                  <td>
                    <label for="api_key">
                      API key (get it <a href="https://swiftype.com/settings/account" target="_blank">here</a>)<br />
                      <input type="text" class="large-text code" name="api_key" id="api_key" value="<?php echo esc_attr( $options['api_key'] ); ?>" />
                      <p class="description">We recommend that you set your engine to re-index after installing this plugin as it added some meta fields for Swiftype!</p>
                    </label>
                    <p>After you enter the API key save the settings and we will show you what engines are available.</p>
                  </td>
                </tr>
              <?php
                $sShowSave = true;
              endif; ?>
              <?php
              try{ 
                if( $this->have_api_key() && !$this->have_engine() ) :
                  $this->init_api();
                  ?>
                  <tr>
                    <td>
                      <?php
                      $bSucc = false;
                      if( $this->client ) {
                        if( $aEngines = $this->client->get_engines() ) :
                          $bSucc = true;
                        ?>
                        <select name="engine_id">
                          <option value="">Pick your engine</option>
                        <?php foreach( $aEngines AS $aEngine ) : ?>
                          <option value="<?php echo $aEngine['id']; ?>"><?php echo $aEngine['name']; ?> (<?php echo $aEngine['document_count'].' documents'; ?>)</option>                                    
                        <?php endforeach; ?>
                        </select>
                        <p>Make sure you pick an <strong>external crawler</strong> type of engine! Once you pick the engine and save the settings, the API key will remain hidden.</p>
                      <?php
                        $sShowSave = true;
                      endif;
                      } ?>
                      
                      <?php if( !$bSucc ) : ?>
                        <p>Error retreiving the engines! Is your API key correct?</p>
                      <?php endif; ?>
                    </td>
                  </tr>
                  
                <?php elseif( $this->have_api_key() ) : ?>
                  <tr>
                    <td>
                      <p>Using search engine:</p>
                      <?php
                      
                      $this->init_api();
                      if( $this->client ) {                    
                        if( $aEngines = $this->client->get_engines() ) {
                          foreach( $aEngines AS $aEngine ) {
                            if( $aEngine['id'] == $options['engine_id'] ) {
                              ?>
                              <table>
                                <tr>
                                  <th>Name</th><td><?php echo $aEngine['name']; ?></td>
                                </tr>
                                <tr>
                                  <th>Slug</th><td><?php echo $aEngine['slug']; ?></td>
                                </tr>
                                <tr>
                                  <th>Latest Update</th><td><?php echo $aEngine['updated_at']; ?></td>
                                </tr>
                                <tr>
                                  <th>Documents</th><td><?php echo $aEngine['document_count']; ?></td>
                                </tr>                              
                              </table>
                              <?php  
                              $bSucc = true;
                              break;
                            }
                          }
                        }
                      }
                      
                      if( !$bSucc ) {
                        ?>
                          <p>Error retreiving the engines! Please reset the API key below and then enter it again.</p>
                        <?php
                      } ?>
                    </td>
                  </tr>
                  
                <?php endif;
              } catch( Exception $e ) {
                echo "<p>Caught exception:</p><pre>".$e->getMessage()."</pre>";
              }
              ?>
            </table>
            <?php if( $sShowSave ) : ?>
              <p>
                <input type="submit" name="fv_swiftype_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
              </p>
            <?php endif; ?>
          </div>
        </div>
        <div class="postbox">
          <h3>
          <?php _e('Tweaks') ?>
          </h3>
          <div class="inside">
            <table>
              <?php if( $this->have_engine() ) : ?>
              <tr>
                <td>
                  <p>Exclude result types from search results:</p>
                  <p class="description">Changes here don't require re-index.</p>
                    <?php if( $aGroups = $this->get_search_types( array('group' => true) ) ) {
                      
                      function transpose($array) {
                          array_unshift($array, null);
                          return call_user_func_array('array_map', $array);
                      }
                      
                      $count = 0;                    
                      
                      echo "<table class='form-table'>\n";
                      echo "\t<tr>\n\t\t";
                      foreach( $aGroups AS $key => $aTypes ) {
                        echo "<th>$key</th>";
                      }
                      echo "\n\t</tr>\n";
                      
                      foreach( transpose($aGroups) AS $key => $aTypes ) {
                        echo "\t<tr>\n";
                        foreach( $aTypes AS $k => $sType ) {
                          echo "\t\t<td>";
                          if( isset($sType) ) {
                            $document_types = array_keys($aGroups);
                            $sChecked = ( isset($options['exclude'][$document_types[$k]][$sType]) ) ? " checked='checked'" : "";
                            $sLabel = ($sType) ? $sType : 'non-Wordpress items';
                            echo "<input id='type-$count' type='checkbox' value='1' name='exclude[".$document_types[$k]."][$sType]' $sChecked /> <label for='type-$count'>$sLabel</label>";
                          }
                          echo "</td>\n";
                          $count++;
                        }
                        echo "\n\t</tr>\n";
                      }
                      echo "</table>\n";
                      
                      if( $count > 0 ) {
                        ?>
                        <input type="hidden" name="exclude_present" value="1" />
                        <?php
                      }
                    } ?>                    
                </td>
              </tr>
              <?php endif; ?>
              <tr>
                <td>
                  <label for="disable_image_style">
                    <input type="checkbox" name="disable_image_style" id="disable_image_style" value="1" <?php if( isset($options['disable_image_style']) && $options['disable_image_style'] ) echo 'checked="checked"'; ?> />
                    Disable image max-sizes in inline style
                  </label>
                  <p class="description">My theme is using responsive image sizes. Do not include <u>max-width</u> and <u>max-height</u> as inline style for images.</p>
                </td>
              </tr>
              <tr>
                <td>
                  <label for="test_mode">
                    <input type="checkbox" name="test_mode" id="test_mode" value="1" <?php if( isset($options['test_mode']) && $options['test_mode'] ) echo 'checked="checked"'; ?> />
                    Test Mode.
                  </label>
                  <p class="description">Use to test the search, it won't affect the search for public. You will have to add &fv_swiftype to the URL to make it work for your search URLs.</p>
                </td>
              </tr>
              <tr>
                <td>
                  <label for="debug">
                    <input type="checkbox" name="debug" id="debug" value="1" <?php if( isset($options['debug']) && $options['debug'] ) echo 'checked="checked"'; ?> />
                    Debug mode
                  </label>
                  <p class="description">Puts some interesting information into your page HTML as HTML comments on search pages.</p>
                </td>
              </tr>                                      
            </table>
            <p>
              <input type="submit" name="fv_swiftype_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
            </p>
          </div>      
        </div>
      </form>
      <form id="fv_swiftype_form_reset" method="post" action="">
        <?php wp_nonce_field('fv_swiftype_reset') ?>
        <p>
          <input type="submit" name="fv_swiftype_reset" class="button" value="<?php _e('Reset API key') ?>" />
        </p>
      </form>        
      <p><?php echo __('Are you having any problems or questions? Use our <a target="_blank" href="http://foliovision.com/support/">support forums</a>.'); ?></p>
    </div>
  
</div>
<?php    
  }
  
  
  
  
  function options_save() {
    if( isset($_GET['page']) && $_GET['page'] == 'fv_swiftype' && isset($_GET['dismiss']) ) {
      delete_option('fv_swiftype_last_error');
      wp_redirect( site_url('wp-admin/options-general.php?page=fv_swiftype&dismissed=1') );
    }
    
    
    if( isset($_POST['fv_swiftype_submit'] ) ) :
      check_admin_referer('fv_swiftype');

      $options = get_option( 'fv_swiftype', array() );
      if( isset($_POST['api_key']) ) {
        $options['api_key'] = stripslashes( $_POST['api_key'] );
      }
      $options['disable_image_style'] = ( isset($_POST['disable_image_style']) && $_POST['disable_image_style'] ) ? true : false;
      $options['test_mode'] = ( isset($_POST['test_mode']) && $_POST['test_mode'] ) ? true : false;
      $options['debug'] = ( isset($_POST['debug']) && $_POST['debug'] ) ? true : false;
      if( isset($_POST['engine_id']) ) {
        $options['engine_id'] = $_POST['engine_id'];
      }
      
      if( isset($_POST['exclude_present']) ) {
        $options['exclude'] = ( isset($_POST['exclude']) ) ? $_POST['exclude'] : false;
      }
          
      update_option( 'fv_swiftype', $options );
      $this->aOptions = $options;

      wp_redirect( site_url('wp-admin/options-general.php?page=fv_swiftype&saved=1') );
      
    endif; // fv_swiftype_submit
    
    if( isset($_POST['fv_swiftype_reset'] ) ) :      
      $options = get_option( 'fv_swiftype', array() );
      $options['api_key'] = '';
      $options['engine_id'] = '';
      update_option( 'fv_swiftype', $options );
      $this->aOptions = $options;
      
      wp_redirect( site_url('wp-admin/options-general.php?page=fv_swiftype&reset=1') );

    endif; // fv_feedburner_replacement_submit       
  }
  
  
  
  
  function post_link( $permalink ) {
    $aArgs = func_get_args();
      
    if( !isset($aArgs[1]->post_type) || $aArgs[1]->post_type != 'fv_swiftype' ) {
      return $permalink;
    }
    
    return $aArgs[1]->guid;
  }
  
  
  
  
  function post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size ) {
    global $post;
    if( empty($post->fv_swiftype_featured) ) return $html;
    
    $style = '';
    if( !isset( $this->aOptions['disable_image_style'] ) || !$this->aOptions['disable_image_style'] ) {
      $width = get_option( $size.'_size_w');
      $height = get_option( $size.'_size_h');
      
      global $_wp_additional_image_sizes;
      if( isset($_wp_additional_image_sizes[$size]) ) {
        $width = $_wp_additional_image_sizes[$size]['width'];
        $height = $_wp_additional_image_sizes[$size]['height']; 
      }
      
      $width = intval($width);
      $height = intval($height);

      if( $width > 0 && $height > 0 ) {
        $style = ' style="max-width: '.$width.'px; max-height: '.$height.'px"';
      }
    }
    
    return "<img src='$post->fv_swiftype_featured'".$style." class='attachment-$size wp-post-image' alt='".esc_attr($post->post_title)."' />";
  }
  
  
  
  
  function query_insert_results( $posts ) {
    $aArgs = func_get_args();
    $wp_query = $aArgs[1];

    if( !isset($wp_query->query_vars['fv_swiftype_s']) ) {
      return $posts;
    }
    
    $tStart = microtime(true);

    $aResponse = $this->fv_swiftype_response[$wp_query->query_vars['fv_swiftype_s']];
    $aPosts = $aResponse['records']['page'];
    
    $aIds = array();
    $aIdsDebug = array();
    foreach( $aPosts AS $aPost ) {
      $post_id = url_to_postid($aPost['url']);
      if( $post_id > 0 ) {
        $aIds[] = $post_id;
        $aIdsDebug[] = array( 'id' => $post_id, 'url' => $aPost['url'] );
      }
    }
    
    if( count($aIds) ) {
      $query_local = new WP_Query( array( 'post__in' => $aIds, 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1 ) );  //  todo: perhaps add option to exclude attachments completelly
    }
    
    //var_dump($query_local->query_vars);
    //var_dump($query_local->posts);
    
    $posts = array();
    foreach( $aPosts AS $aPost ) {
      
      $newPost = false;
      
      $post_id = url_to_postid($aPost['url']);  //  todo: check type first?
      if( $post_id > 0 && isset($query_local) && isset($query_local->posts) && count($query_local->posts) ) {
        foreach( $query_local->posts AS $local_post ) {
          if( $local_post->ID == $post_id ) {
            $newPost = $local_post;
          }
        }
      }
        
      if( !$newPost ) {   
        $newPost = new stdClass;
        
        $newPost->ID = 5006007008009001000;  //  to trick get_permalink()
        $newPost->post_author = 0;
        $newPost->post_date = $aPost['published_at'];
        $newPost->post_date_gmt = $aPost['published_at'];
  
        $newPost->post_status = 'publish';
        $newPost->comment_status = 'open';
        $newPost->ping_status = 'open';
        $newPost->post_password = '';
        $newPost->post_name = trim( strrchr( trim($aPost['url'],'/'), '/' ), '/' );
        
        $newPost->guid = $aPost['url'];
        
        $newPost->post_type = 'fv_swiftype';
        
        if( isset($aPost['highlight']['sections']) ) {
          $newPost->post_content = $this->get_excerpt( $aPost['highlight']['sections'] ). apply_filters( 'excerpt_more', ' ' . '[&hellip;]' );
        } else {
          $newPost->post_content = '';
        }
        
        if( !empty($aPost['image']) ) {
          $newPost->fv_swiftype_featured = $aPost['image'];
        }
        
        if( $objTaxonomy = get_taxonomy($aPost['type']) ) {
          $newPost->post_content = __('Browse the '.$objTaxonomy->labels->singular_name.' archive: ').$newPost->post_content;
        }
        
        $newPost->post_excerpt = $newPost->post_content;
        
      }
      
      $newPost->post_title = preg_replace( '~\s?\|.*$~', '', str_replace( 'em>', 'strong>', ( isset($aPost['highlight']['title']) ) ? $aPost['highlight']['title'] : $aPost['title'] ) );            
      if( empty($newPost->post_title) ) {
        $aURL = parse_url($aPost['url']);
        if( isset($aURL['path']) ) $newPost->post_title .= $aURL['path'];
        if( isset($aURL['query']) ) $newPost->post_title .= '?'.$aURL['query'];
      }
      
      $posts[] = $newPost;
    }
    
    $wp_query->posts = $posts;
    $wp_query->post_count = count($posts);
    $wp_query->found_posts = $aResponse['info']['page']['total_result_count'];
    $wp_query->max_num_pages = $aResponse['info']['page']['num_pages'];

    $this->debug( "processing time ".(microtime(true) - $tStart ) );

    return $posts;  
  }  
  
  
  
  
  function robots_txt( $content ) {
    $content .= "\n\nUser-agent: Swiftbot\nCrawl-delay: 3\n";

    return $content;
  }
  

  
  
}




$FV_Swiftype = new FV_Swiftype;
endif;