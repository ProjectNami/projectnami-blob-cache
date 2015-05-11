<?php
/*
 * Plugin Name: Project Nami Blob Cache
 * Plugin URI: http://projectnami.org/blob-cache
 * Description: External full page caching for WordPress.
 * Author: Patrick Bates, Spencer Cameron
 * Author URI: http://projectnami.org/
 * Version: 3.0
 * License: GPL2
 */

require_once 'blob-cache-handler.php';

class PN_BlobCache {
	
	private $page_key = null;

	private $cached_page_copy = null;

	private $initial_timestamp = null;

	private $plugin_page_name = 'pn-blob-cache-settings';

	private $default_cache_expiration = 300;

	public function __construct() {

		/*
		 * Set an initial timestamp once the plugin loads.
		 * This will give us a rough estimate of when page loading began.
		 * We'll reference this later once content is generated and ready
		 * to be sent to the browser.
		 */
		$this->initial_timestamp = microtime( true );

		/*
		 * Let's get things started, shall we?
		 */
		$this->init();
	}

	/*
	 * Setup and initialize the plugin components.
	 */
	private function init() {
		
		/*
		 * Add our admin menu.
		 */
		add_action( 'admin_menu' , array( $this, 'create_settings_menu' ) );

		add_action( 'comment_post', array( $this, 'handle_user_comment' ), 10, 2 );

        add_action( 'save_post', array( $this, 'handle_save_post'  ) );

		$this->create_page_key();

		/*
		 * There are several scenarios in which caching may not
		 * be desired. If any of the no-cache criteria are met,
		 * just return and let WordPress do it's thing.
		 */
		if( $this->do_not_cache() )
			return;

		/*
		if( $this->is_cached() )
			$this->serve_from_cache();
		*/

        add_action('wp_loaded', array( $this, 'begin_buffer' ));		
	}

    public function begin_buffer(){
        ob_start( array( $this, 'handle_output_buffer' ) );
    }

	private function get_cache_expiration() {
		$cache_expiration = get_option( $this->plugin_page_name . '-cache-expiration' );

		if( absint( $cache_expiration ) > 0 )
			return $cache_expiration;
		else
			return $this->default_cache_expiration;
	}

	private function get_cache_exclusions() {
		return get_option( $this->plugin_page_name . '-cache-exclusions' );
	}

	public function create_settings_menu() {
		add_options_page( 'Project Nami Blob Cache Settings', 'PN Blob Cache', 'manage_options', $this->plugin_page_name, array( $this, 'create_settings_page' ) );
	}

	public function create_settings_page() {
		if(  ! empty( $_POST ) )
			$this->process_form_input();

		$this->generate_settings_form();
	}

	private function process_form_input() {
		
		// Make sure there's no monkey-business going on.
		check_admin_referer( $this->plugin_page_name );

		$cache_expiration = isset( $_POST[ 'cache_expiration' ] ) ? absint( $_POST[ 'cache_expiration' ] ) : $this->default_cache_expiration;

		$cache_exclusions = isset( $_POST['cache_exclusions'] ) ? sanitize_text_field( $_POST['cache_exclusions'] ) : '';

		update_option( $this->plugin_page_name . '-cache-expiration', $cache_expiration );

		update_option( $this->plugin_page_name . '-cache-exclusions', $cache_exclusions );
	}

	private function generate_settings_form() { ?>
		<div>

			<style>

				form h2 {
					font-size: 22px;
					text-decoration: underline;
					margin: 10px 0 40px 0;
				}

				form .cache-setting {
					background: #fcfcfc;
					border: 1px solid #ddd;
					margin: 20px 0;
					padding: 10px;
					width: 300px;
				}

				form h3 {
					margin: 0;
				}

				form p {
					margin: 3px 0;
				}

				form input {
					display: block;
					margin: 0 0 25px 0;
				}

				form #cache-secret,
				form #cache-endpoint {
					width: 200px;
				}
	
				form #cache-exclusions {
					width: 100%;
				}

				form #cache-expiration {
					width: 50px;
				}

			</style>	
	
			<form method="post" action="<?php menu_page_url( $this->plugin_page_name ); ?>" >	
				<img src="https://pnsrc.azurewebsites.net/blobcache/header.png" alt="Project Nami" />
                <h2>Blob Cache Settings</h2>

				<div class="cache-setting">
					<h3>Cache Expiration</h3>
					<p>The amount of time (in seconds) a page should be cached before expiring.</p>
					<input id="cache-expiration" type="text" name="cache_expiration" value="<?php echo esc_attr( $this->get_cache_expiration() ); ?>" />
				</div>

				<div class="cache-setting">
					<h3>Cache Exclusions (Optional)</h3>
					<p>This is a comma separated list of URLs to exclude from the cache. (Query strings will be stripped automatically before comparison and not considered as part of the url.)</p>
					<textarea id="cache-exclusions" name="cache_exclusions"><?php echo esc_textarea( $this->get_cache_exclusions() ); ?></textarea>
				</div>

				<input type="submit" value="Update Settings" />
				<?php wp_nonce_field( $this->plugin_page_name ); ?>
			</form>
		</div><?php
	}

	/*
	 * We need to create a key specific to the page
	 * the user is currently on. That way, we know
	 * how to look up the cached copy when a subsequent
	 * attempt is made to load the page.
	 */
	private function create_page_key() {
		$scheme = empty( $_SERVER['HTTPS'] ) || 'off' == $_SERVER['HTTPS'] ? 'http://' : 'https://';

		$url = parse_url( $scheme . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ] );

		$query = empty( $url[ 'query' ] ) ? '' : '?' . $url[ 'query' ];

		$url = $scheme . $url[ 'host' ] . $url[ 'path' ] . $query;

        if ( wp_is_mobile() ){
            $url = $url . "|mobile";
        }

		$this->url = $url;

		$this->page_key = md5( $url );
	}

/*
 * Handler for the optionally excluded urls. The admin may define them in the plugin options page.
 * Takes a single string of one or more urls separated by commas and compares them to the current url.
 * If a match is found it returns true.
 * False if no matches are found. 
 */

	private function is_current_url_cache_excluded() {
		// Retrieve the value from the cache_exclusions options input on the plugin options page.
		$exclusions_input_str = $this->get_cache_exclusions();
		
		// Exit function if there is no value.
		if ( empty( $exclusions_input_str ) )
			return false;

		// Get the current page url for comparison to the list of exclusions.
		$current_url = $_SERVER[ 'SERVER_NAME' ] . $_SERVER[ 'REQUEST_URI' ];
		$url_arr = parse_url( $current_url );

		// Constructs the comparison url omitting the scheme and any query strings.
		$current_page_url = $url_arr[ 'host' ] . $url_arr[ 'path' ];

		// If no delimiter is in the string, it will output a single element array.
		$exclusions = explode( ',', $exclusions_input_str );


		foreach ( $exclusions as $exclusion ) {
			$exclusion = trim( $exclusion );
			$exclusion = parse_url( $exclusion );

			// Constructs the comparison url omitting the scheme and any query strings.
			$exclusion = $exclusion[ 'host' ] . $exclusion[ 'path' ];

			// Compares the current page url with the current element of the the foreach.
			// If it matches, it will return true indicating not to cache.
			if ( trailingslashit( $exclusion ) === trailingslashit( $current_page_url ) )
				return true; // do not cache
		}

		// If nothing matches, return false.		
		return false; // page does not match a cache excluded url
	}

	private function do_not_cache() {
		if( $this->user_logged_in() )
			return true;
		if( $this->user_is_commenter() )
			return true;
		elseif( $this->should_not_cache() )
			return true;
		if( $this->is_current_url_cache_excluded() )
			return true;
		else
			return false;
	}

	private function is_cached() {
		$pn_remote_cache = new PN_Blob_Cache_Handler( );

		$this->cached_page_copy = $pn_remote_cache->pn_blob_cache_get( $this->page_key );

		if( ! empty( $this->cached_page_copy ) )
			return true;
		else
			return false;
	}

	private function user_logged_in() {
		return preg_match( '/wordpress_logged_in/', implode( ' ', array_keys( $_COOKIE ) ) );
	}

	private function user_is_commenter() {
		return preg_match( "/comment_post_key_{$this->page_key}/", implode( ' ', array_keys( $_COOKIE ) ) );
	}

	private function should_not_cache() {
		$should_not_cache = false;

		$should_not_cache = preg_match( '/wp-admin/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
			return true;

		$should_not_cache = preg_match( '/wp-login.php/', $_SERVER[ 'REQUEST_URI' ] );

        if( $should_not_cache )
            return true;

        $should_not_cache = preg_match( '/wp-cron.php/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
            return true;

		$should_not_cache = preg_match( '/wp-comments-post.php/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
			return true;

		$should_not_cache = preg_match( '/xmlrpc.php/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
			return true;

		$should_not_cache = preg_match( '/wp-signup.php/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
			return true;

		$should_not_cache = preg_match( '/wp-trackback.php/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
			return true;

		$should_not_cache = preg_match( '/wp-links-opml.php/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
			return true;

		$should_not_cache = preg_match( '/wp-blog-header.php/', $_SERVER[ 'REQUEST_URI' ] );

		if( $should_not_cache )
			return true;

		return $should_not_cache;
	}

	public function handle_user_comment( $comment_id, $comment_status ) {

		$comment = get_comment( $comment_id );

		$url = parse_url( get_permalink( $comment->comment_post_ID ) );

		$url = $url[ 'host' ] . $url[ 'path' ];

		$this->page_key = md5( $url );

		setcookie( "comment_post_key_{$this->page_key}",  '1', time() + 1800, '/' );

		if( $comment_status != 1 )
			return;

		$comment_cookie = new WP_Http_Cookie( "comment_post_key_{$this->page_key}" );

		$comment_cookie->name = "comment_post_key_{$this->page_key}";

		$comment_cookie->value = '1';

		$comment_cookie->domain = '/';

		$comment_cookie->expires = time() + 1800;

		$cookies[] = $comment_cookie;

		$page_content = wp_remote_get( "http://$url", array( 'cookies' => $cookies ) );

		$page_content = $page_content[ 'body' ];

		//wp_die(var_dump($page_content));

		if( ! is_wp_error( $page_content ) ) {
			$this->cache_page_content( $page_content );
		}
	}

	private function serve_from_cache() {
		$now = microtime( true );

		$duration = round( $now - $this->initial_timestamp, 3 );
		$page_key = $this->page_key;

		//$request_started = $_SERVER[ 'REQUEST_TIME_FLOAT' ];

		$request_started = 0;

		$total_time = round( $now - $request_started, 3 );

		$overhead = $total_time - $duration;

		if( ! empty( $this->cached_page_copy ) )
			die( str_replace( '</head>', "<!-- Served by Project Nami Blob Cache in $duration seconds. URL = $this->url It's been $total_time seconds since the request began. Initial processing overhead is $overhead seconds. -->\n</head>", $this->cached_page_copy ) );
	}

    public function handle_save_post( $post_id ){
        if( !isset( $post_id ) || wp_is_post_revision( $post_id ) ){
            return;
        }        

        $this->page_key = md5( get_permalink( $post_id ) );

        $pn_remote_cache = new PN_Blob_Cache_Handler( );        
	
        $pn_remote_cache->pn_blob_cache_del( $this->page_key );
    }

	public function handle_output_buffer( $output_buffer ) {

		// Bail if the output buffer is empty
		if( sizeof( $output_buffer ) < 1 )
			return $output_buffer;

		//$elements = array( '<html', '<head', '<body', '</html', '</head', '</body', '<rss', '</rss' );

		//foreach( $elements as $element ) {
		//	if( substr_count( $output_buffer, $element ) < 1 )
		//		return $output_buffer;
		//}
			
		$this->cache_page_content( $output_buffer );
		
		$duration = round( microtime( true ) - $this->initial_timestamp, 3 );
        	
		return str_replace( '</head>', "<!-- Page generated without caching in $duration seconds. -->\n</head>", $output_buffer );
	}

	private function cache_page_content( $page_content ) {
		if( is_404() ) {
            if ( getenv("ProjectNamiBlobCache.404Duration") ) {
                $cache_expiration = getenv("ProjectNamiBlobCache.404Duration");
            } else {
                $cache_expiration = 300;
            }
        }else{
            $cache_expiration = $this->get_cache_expiration();
        }

        $headers = headers_list();

		$pn_remote_cache = new PN_Blob_Cache_Handler( );
			
		$host_name = site_url();

		$pn_remote_cache->pn_blob_cache_set( $this->page_key, $page_content, $cache_expiration, $headers );
	}

}

new PN_BlobCache;

?>
