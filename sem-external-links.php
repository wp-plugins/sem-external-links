<?php
/*
Plugin Name: External Links
Plugin URI: http://www.semiologic.com/software/external-links/
Description: Marks outbound links as such, with various effects that are configurable under <a href="options-general.php?page=external-links">Settings / External Links</a>.
Version: 5.0
Author: Denis de Bernardy & Mike Koepke
Author URI: http://www.getsemiologic.com
Text Domain: external-links
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Denis de Bernardy & Mike Koepke, and is distributed under the terms of the GPL license, v.2.

http://www.opensource.org/licenses/gpl-2.0.php
**/


load_plugin_textdomain('external-links', false, dirname(plugin_basename(__FILE__)) . '/lang');


/**
 * external_links
 *
 * @package External Links
 **/

class external_links {

	private $opts;

    /**
     * constructor()
     */
    public function __construct() {
        if ( !is_admin() ) {

	        if ( !class_exists('simple_html_dom_node') )
	            include dirname(__FILE__) . '/simple_html_dom.php';

        	$o = external_links::get_options();

        	if ( $o['icon'] )
        		add_action('wp_print_styles', array($this, 'styles'), 5);

        	if ( $o['global'] )
		        add_action('wp_head', array($this, 'ob_start'), 10000);
			else {
		        add_filter('the_content', array($this, 'filter'), 100);
		        add_filter('the_excerpt', array($this, 'filter'), 100);
		        add_filter('comment_text', array($this, 'filter'), 100);

				if ( $o['text_widgets'] )
					add_filter('widget_text', array($this, 'filter'), 100);
			}

        	unset($o);
        } else {
        	add_action('admin_menu', array($this, 'admin_menu'));
        }
    }

    /**
	 * styles()
	 *
	 * @return void
	 **/

	function styles() {
		$folder = plugin_dir_url(__FILE__);
		wp_enqueue_style('external-links', $folder . 'sem-external-links.css', null, '20090903');
	} # styles()


	/**
	* ob_start()
	*
	* @return void
	**/

	function ob_start() {

		ob_start(array($this, 'filter'));
		add_action('wp_footer', array($this, 'ob_flush'), 10000);

	} # ob_start()


	/**
	 * ob_flush()
	 *
	 * @return void
	 **/

	static function ob_flush() {

		ob_end_flush();
	} # ob_flush()

	/**
	 * filter()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function filter($text) {

		$anchor = array();

		$this->opts = external_links::get_options();

		$html = new simple_html_dom();
		$html->load( $text );
		foreach( $html->find( 'a, img' ) as $link) {

			$this->apply_attributes( $link );
		}

		$text = $html->save();

		return $text;
	} # filter()
	
	/**
	 * apply_attributes()
	 *
	 * @param simple_html_dom_node $anchor
	 * @return null
	 **/

	function apply_attributes( $anchor ) {
		# disable in feeds
		if ( is_feed() )
			return;
		
		# ignore local urls
		$url =  ($anchor->tag == 'a') ? $anchor->href : $anchor->src;
		if ( $this->is_local_url( $url ) )
			return;
		
		if ( isset($anchor->class) ) {
			if ( stripos($anchor->class, 'external') === false )
				$anchor->class .= ' external';
		}
		else
			$anchor->class = 'external';

		if ( $anchor->tag == 'a' && $this->opts['icon']
			&& ( stripos($anchor->class, 'external_icon') === false )
			&& ( stripos($anchor->class, 'no_icon') === false )
			&& ( stripos($anchor->class, 'noicon') === false ) )
			$anchor->class .= ' external_icon';

		if ( $this->opts['nofollow'] && !function_exists('strip_nofollow')
			&& ( stripos($anchor->rel, 'nofollow') === false )
			&& ( stripos($anchor->rel, 'follow') === false ) )
				$anchor->rel = 'nofollow';
		
		if ( $this->opts['target'] && !isset($anchor->target) )
			$anchor->target = '_blank';
	} # filter()
	
	
	/**
	 * is_local_url()
	 *
	 * @param string $url
	 * @return bool $is_local_url
	 **/

	function is_local_url($url) {
		if ( in_array(substr($url, 0, 1), array('?', '#')) || strpos($url, '://') === false )
			return true;
		elseif ( preg_match("~/go(/|\.)~i", $url) )
			return false;
		
		static $site_domain;
		
		if ( !isset($site_domain) ) {
			$site_domain = get_option('home');
			$site_domain = parse_url($site_domain);
			$site_domain = $site_domain['host'];
            if ($site_domain == false)
                return false;
            elseif (is_array($site_domain)) {
                if (isset($site_domain['host']))
                    $site_domain = $site_domain['host'];
                else
                    return false;
            }
			$site_domain = preg_replace("/^www\./i", '', $site_domain);
			
			# The following is not bullet proof, but it's good enough for a WP site
			if ( $site_domain != 'localhost' && !preg_match("/\d+(\.\d+){3}/", $site_domain) ) {
				if ( preg_match("/\.([^.]+)$/", $site_domain, $tld) ) {
					$tld = end($tld);
				} else {
					$site_domain = false;
					return false;
				}
				
				$site_domain = substr($site_domain, 0, strlen($site_domain) - 1 - strlen($tld));
				
				if ( preg_match("/\.([^.]+)$/", $site_domain, $subtld) ) {
					$subtld = end($subtld);
					if ( strlen($subtld) <= 4 ) {
						$site_domain = substr($site_domain, 0, strlen($site_domain) - 1 - strlen($subtld));
						$site_domain = explode('.', $site_domain);
						$site_domain = array_pop($site_domain);
						$site_domain .= ".$subtld";
					} else {
						$site_domain = $subtld;
					}
				}
				
				$site_domain .= ".$tld";
			}
			
			$site_domain = strtolower($site_domain);
		}
		
		if ( !$site_domain )
			return false;
		
		$link_domain = parse_url($url);
        if ($link_domain == false)
            return false;
        elseif (is_array($link_domain)) {
            if (isset($link_domain['host']))
		        $link_domain = $link_domain['host'];
            else
                return false;
        }
		$link_domain = preg_replace("/^www\./i", '', $link_domain);
		$link_domain = strtolower($link_domain);
		
		if ( $site_domain == $link_domain ) {
			return true;
		} elseif ( function_exists('is_multisite') && is_multisite() ) {
			return false;
		} else {
			$site_elts = explode('.', $site_domain);
			$link_elts = explode('.', $link_domain);
			
			while ( ( $site_elt = array_pop($site_elts) ) && ( $link_elt = array_pop($link_elts) ) ) {
				if ( $site_elt !== $link_elt )
					return false;
			}
			
			return empty($link_elts) || empty($site_elts);
		}
	} # is_local_url()
	
	
	/**
	 * get_options
	 *
	 * @return array $options
	 **/

	static function get_options() {
		static $o;
		
		if ( !is_admin() && isset($o) )
			return $o;
		
		$o = get_option('external_links');
		
		if ( $o === false || !isset($o['text_widgets']) )
			$o = external_links::init_options();
		
		return $o;
	} # get_options()
	
	
	/**
	 * init_options()
	 *
	 * @return array $options
	 **/

	function init_options() {
		$o = get_option('external_links');

		$defaults = array(
					'global' => false,
					'icon' => true,
					'target' => false,
					'nofollow' => true,
					'text_widgets' => true,
					);

		if ( !$o )
			$o  = $defaults;
		else
			$o = wp_parse_args($o, $defaults);
		
		update_option('external_links', $o);
		
		return $o;
	} # init_options()
	
	
	/**
	 * admin_menu()
	 *
	 * @return void
	 **/
	
	function admin_menu() {
		add_options_page(
			__('External Links', 'external-links'),
			__('External Links', 'external-links'),
			'manage_options',
			'external-links',
			array('external_links_admin', 'edit_options')
			);
	} # admin_menu()
} # external_links


function external_links_admin() {
	include_once dirname(__FILE__) . '/sem-external-links-admin.php';
}

add_action('load-settings_page_external-links', 'external_links_admin');

$external_links = new external_links();
?>