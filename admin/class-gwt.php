<?php
/**
 * @package Admin
 */

if ( ! class_exists( 'Yoast_OAuthConsumer' ) )
	require_once plugin_dir_path( __FILE__ ) . '/wp-gdata/wp-gdata.php';
 
if ( !defined( 'WPSEO_VERSION' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

/**
 * Class that handles the communication with Google Webmaster Tools.
 */
 
class WPSEO_Gwt extends WPSEO_Admin_Pages {

	public $hook = 'wpseo_dashboard';
	public $longname = '';
	public $shortname = '';
	// private $ozhicon = 'images/chart_curve.png';
	public $optionname = 'Yoast_Google_Webmaster_Tools';
	
	private $sitename = '';
	// private $sitename = 'http://yoast.nl//';
	// private $sitename = 'http://54.241.25.88/';
	
	
	/**
	 * Constructur, load all required stuff.
	 */
	function __construct() {
		$this->longname  = __( 'Google Webmaster Tools Configuration', 'gwtwp' );
		$this->shortname = __( 'Google Webmaster Tools', 'gwtwp' );

		$this->plugin_url = plugins_url( '', __FILE__ ) . '/';

		
		$o = get_option( $this->optionname );
		$this->oauth_token = $o['gwtwp_oauth']['access_token']['oauth_token'];
		$this->oauth_token_secret = $o['gwtwp_oauth']['access_token']['oauth_token_secret'];

		// TODO: ending slash is important, add logic to make there is ONE and only ONE ending slash
		$this->sitename = get_bloginfo('url') . '/';
		
		// // Register the settings page
		// add_action( 'admin_menu', array( &$this, 'register_settings_page' ) );

		// // Register the contextual help for the settings page
		// //	add_action( 'contextual_help', 		array(&$this, 'plugin_help'), 10, 3 );

		// // Give the plugin a settings link in the plugin overview
		// add_filter( 'plugin_action_links', array( &$this, 'add_action_link' ), 10, 2 );

		// // Print Scripts and Styles
		// add_action( 'admin_print_scripts', array( &$this, 'config_page_scripts' ) );
		// add_action( 'admin_print_styles', array( &$this, 'config_page_styles' ) );

		// // Print stuff in the settings page's head
		// add_action( 'admin_head', array( &$this, 'config_page_head' ) );

		// // Drop a warning on each page of the admin when Google Analytics hasn't been configured
		// add_action( 'admin_footer', array( &$this, 'warning' ) );

		// // Save settings
		// // TODO: replace with Options API
		// add_action( 'admin_init', array( &$this, 'save_settings' ) );

		// Authenticate
		add_action( 'admin_init', array( &$this, 'authenticate' ) );
		do_action( 'admin_init' );
	}

	
	function add_site($sitename = '') {
		var_dump('in add a site');
		
		// exit early if token and secret are not set
		if( !$this->oauth_token || !$this->oauth_token_secret ) {
			// return null;
		}
		
		if ($sitename == '') {
			$sitename = $this->sitename;
		}
		
		$gdata = new WP_GData(
			array(
				// 'scope'              => 'https://www.google.com/webmasters/tools/feeds/',
				// 'xoauth_displayname' => 'Google WEBMASTER for WordPress by Yoast'
			),
			$this->oauth_token,
			$this->oauth_token_secret
		);

		// adding a site uses a redirection, but will fail because the redirection will not have the auth token
		// use redirection = 0 for now
		$parameters = array(
			'body' => "<atom:entry xmlns:atom='http://www.w3.org/2005/Atom'>
				<atom:content src='{$this->sitename}' />
			</atom:entry>",
			
			'redirection' => 0,
			'headers' => array(
				'content-type' => 'application/atom+xml'
			)
		);
		
		$response = $gdata->post('https://www.google.com/webmasters/tools/feeds/sites/', $parameters );
		
		
		// site added so save the metatag code
		if ($response['response']['code'] == 201) {
			$gwt_site_info = xml2array(wp_remote_retrieve_body( $response ));
		
			//$gwt_site_info = array ( 'id' => 'https://www.google.com/webmasters/tools/feeds/sites/http%3A%2F%2F54.241.25.88%2F', 'updated' => '2013-05-06T18:46:55.607Z', 'category' => array ( '@attributes' => array ( 'scheme' => 'http://schemas.google.com/g/2005#kind', 'term' => 'http://schemas.google.com/webmasters/tools/2007#site-info', ), ), 'title' => 'http://54.241.25.88/', 'content' => array ( '@attributes' => array ( 'src' => 'http://54.241.25.88/', ), ), 'link' => array ( 0 => array ( '@attributes' => array ( 'rel' => 'self', 'type' => 'application/atom+xml', 'href' => 'https://www.google.com/webmasters/tools/feeds/sites/http%3A%2F%2F54.241.25.88%2F', ), ), 1 => array ( '@attributes' => array ( 'rel' => 'edit', 'type' => 'application/atom+xml', 'href' => 'https://www.google.com/webmasters/tools/feeds/sites/http%3A%2F%2F54.241.25.88%2F', ), ), ), 'wt:verified' => 'false', 'wt:verification-method' => array ( 0 => '<meta name="google-site-verification" content="ic77CysOP08mrJ3FFYGwvwrvMI9NqQ8iLCmek8HcWdE" />', 1 => 'google01b451e3469640cf.html', ), 'gd:entryLink' => array ( 0 => NULL, 1 => NULL, ), );
		
			// save verify code and status
			$o = get_option( $this->optionname );
			$wpseo_options = get_option( 'wpseo' );
			
			$o['verified'] = $gwt_site_info['wt:verified'];
			foreach ( (array) $gwt_site_info['wt:verification-method'] as $method ) {
				if ( preg_match('|<meta.*>|', $method, $matches) ) {
					$metacode = $matches[0];
					$o['meta_code'] = $metacode;
					$wpseo_options['googleverify'] = $metacode;
				}
			}
			
			update_option( $this->optionname, $o );
			update_option( 'wpseo', $wpseo_options );
		
		} else {
			// TODO: a 403 code means duplicate site, but the response does not provide the metatag code for verification,
			// must makes a request to https://www.google.com/webmasters/tools/feeds/sites/SiteID/ to get site info that 
			// should have the metatag code so we can process it
		
		}
		
		// send the verify request
		$this->verify_site();
		
		return $response;
	}
	
	public function verify_site() {
		var_dump('in verify a site');
		
		$gdata = new WP_GData(
			array(
				// 'scope'              => 'https://www.google.com/analytics/feeds/',
				// 'scope'              => 'https://www.google.com/webmasters/tools/feeds/',
				// 'xoauth_displayname' => 'Google WEBMASTER for WordPress by Yoast'
			),
			$this->oauth_token,
			$this->oauth_token_secret
		);
	
		$parameters = array(
			'body' => '<atom:entry xmlns:atom="http://www.w3.org/2005/Atom"
			xmlns:wt="http://schemas.google.com/webmasters/tools/2007">
				<atom:id>'.$this->sitename.'</atom:id>
				<atom:category scheme="http://schemas.google.com/g/2005#kind"
				term="http://schemas.google.com/webmasters/tools/2007#site-info"/>
				<wt:verification-method type="metatag" in-use="true"/>
			</atom:entry>',

			'headers' => array(
				'content-type' => 'application/atom+xml'
			)

		);

		$response = $gdata->put('https://www.google.com/webmasters/tools/feeds/sites/' . urlencode($this->sitename), $parameters );
		
		if (WP_DEBUG)
			echo("<pre>".htmlspecialchars( wp_remote_retrieve_body($response) )."</pre>");
		
		$gwt_site_info = xml2array(wp_remote_retrieve_body( $response ));
		
		// save verify status
		$o = get_option( $this->optionname );
		$o['verified'] = $gwt_site_info['wt:verified'];
		update_option( $this->optionname, $o );
		
		// if site is verified automatically send the sitemap
		if ( $o['verified'] === true || true)
			$this->submit_sitemap();
		
		return $response;
	}
	
	
	public function submit_sitemap() {
		var_dump('inside sitemap');
		
		$sitemap_url = $this->sitename . 'sitemap_index.xml';
		
		$gdata = new WP_GData(
			array(),
			$this->oauth_token,
			$this->oauth_token_secret
		);
	
		$parameters = array(
			'body' => '<atom:entry xmlns:atom="http://www.w3.org/2005/Atom" xmlns:wt="http://schemas.google.com/webmasters/tools/2007">
				<atom:id>' . $sitemap_url .'</atom:id>
				<atom:category scheme="http://schemas.google.com/g/2005#kind"
				term="http://schemas.google.com/webmasters/tools/2007#sitemap-regular"/>
				<wt:sitemap-type>WEB</wt:sitemap-type>
			</atom:entry>',

			'headers' => array(
				'content-type' => 'application/atom+xml'
			)
		);
	
		$response = $gdata->post('https://www.google.com/webmasters/tools/feeds/'.urlencode($this->sitename).'/sitemaps/', $parameters );
	
		return $response;
	}
	
	
	// TODO: process this and display it as a table
	public function get_crawl_issues() {
		$gdata = new WP_GData(
			array(
				// 'scope'              => 'https://www.google.com/analytics/feeds/',
				// 'scope'              => 'https://www.google.com/webmasters/tools/feeds/',
				// 'xoauth_displayname' => 'Google WEBMASTER for WordPress by Yoast'
			),
			$this->oauth_token,
			$this->oauth_token_secret
		);
	
		$response = $gdata->get('https://www.google.com/webmasters/tools/feeds/'.urlencode($this->sitename).'/crawlissues/' );
		
		echo("<pre>".htmlspecialchars( wp_remote_retrieve_body($response) )."</pre>");
		
		$issue = xml2array(wp_remote_retrieve_body( $response ));
		var_dump($issue);
	}
	
	

	function authenticate() {
		if ( isset( $_GET['gwt'] )) {
			var_dump('in auth');
			
			$gdata = new WP_GData(
				array(
					'scope'              => 'https://www.google.com/webmasters/tools/feeds/',
					'xoauth_displayname' => 'Google WEBMASTER for WordPress by Yoast',
				)
			);
			
			$oauth_callback = add_query_arg( array( 'gwt_oauth_callback' => 1, 'gwt_callback' => true ), menu_page_url( 'wpseo_dashboard', false ) );
			
			$request_token  = $gdata->get_request_token( $oauth_callback );

			$options = get_option( $this->optionname );
			unset( $options['gwt_token'] );
			unset( $options['gwtwp_oauth']['access_token'] );
			$options['gwtwp_oauth']['oauth_token']        = $request_token['oauth_token'];
			$options['gwtwp_oauth']['oauth_token_secret'] = $request_token['oauth_token_secret'];
			update_option( $this->optionname, $options );
			
			wp_redirect( $gdata->get_authorize_url( $request_token ) );
			exit;
		}

	
		if ( isset( $_REQUEST['gwt_oauth_callback'] ) && isset( $_REQUEST['gwt_callback'] )) {
			var_dump('in callback');
		
			$o = get_option( $this->optionname );
			if ( isset( $o['gwtwp_oauth']['oauth_token'] ) && $o['gwtwp_oauth']['oauth_token'] == $_REQUEST['oauth_token'] ) {
				$gdata = new WP_GData(
					array(
						'scope'              => 'https://www.google.com/webmasters/tools/feeds/',
						'xoauth_displayname' => 'Google WEBMASTER for WordPress by Yoast'
					),
					$o['gwtwp_oauth']['oauth_token'],
					$o['gwtwp_oauth']['oauth_token_secret']
				);

				$o['gwtwp_oauth']['access_token'] = $gdata->get_access_token( $_REQUEST['oauth_verifier'] );
				unset( $o['gwtwp_oauth']['oauth_token'] );
				unset( $o['gwtwp_oauth']['oauth_token_secret'] );
				$o['gwt_token'] = $o['gwtwp_oauth']['access_token']['oauth_token'];
			}

			update_option( $this->optionname, $o );
			wp_redirect( menu_page_url( $this->hook, false ) );
			exit;
		} //end reauthenticate()
	}


}

$wpseo_gwt = new WPSEO_Gwt();