<?php
/*
Plugin Name: External Links
Plugin URI: http://www.semiologic.com/software/external-links/
Description: Marks outbound links as such, with various effects that are configurable under <a href="options-general.php?page=external-links">Settings / External Links</a>.
Version: 6.1
Author: Denis de Bernardy & Mike Koepke
Author URI: http://www.getsemiologic.com
Text Domain: external-links
Domain Path: /lang
License: Dual licensed under the MIT and GPLv2 licenses
*/

/*
Terms of use
------------

This software is copyright Denis de Bernardy & Mike Koepke, and is distributed under the terms of the MIT and GPLv2 licenses.

**/

define('sem_external_links_version', '6.1');

/**
 * external_links
 *
 * @package External Links
 **/

class sem_external_links {

	protected $opts;

	protected $anchor_utils;

	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @return  void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain(
			$domain,
			FALSE,
			dirname(plugin_basename(__FILE__)) . '/lang'
		);
	}

	/**
	 * Constructor.
	 *
	 *
	 */
    public function __construct() {
	    $this->plugin_url    = plugins_url( '/', __FILE__ );
        $this->plugin_path   = plugin_dir_path( __FILE__ );
        $this->load_language( 'external-links' );

	    add_action( 'plugins_loaded', array ( $this, 'init' ) );
    }


	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		$this->opts = sem_external_links::get_options();

		if ( !is_admin() ) {
			$inc_text_widgets = false;
			if ( isset( $this->opts['text_widgets'] ) && $this->opts['text_widgets'] )
				$inc_text_widgets = true;

			if ( $this->opts['icon'] )
				add_action('wp_enqueue_scripts', array($this, 'styles'), 5);

			if ( $this->opts['autolinks'] ) {
				if ( !class_exists('sem_autolink_uri') )
				    include $this->plugin_path . '/sem-autolink-uri.php';
			}

			if ( $this->opts['global'] ) {
				if ( !class_exists('external_links_anchor_utils') )
				    include $this->plugin_path . '/external-links-anchor-utils.php';

				$this->anchor_utils = new external_links_anchor_utils( $this );
			}
			else {
				add_filter('the_content', array($this, 'process_content'), 1000000);
				add_filter('the_excerpt', array($this, 'process_content'), 1000000);
				add_filter('comment_text', array($this, 'process_content'), 1000000);
				if ( $inc_text_widgets )
					add_filter('widget_text', array($this, 'process_content'), 1000000);
			}
		}
		else {
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('load-settings_page_external-links', array($this, 'external_links_admin'));
		}
	}

	/**
	* external_links_admin()
	*
	* @return void
	**/
	function external_links_admin() {
		include_once $this->plugin_path . '/sem-external-links-admin.php';
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
	 * process_content()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function process_content($text) {

		// short circuit if there's no anchors at all in the text
		if ( false === stripos($text, '<a ') )
			return($text);

		global $escape_anchor_filter;
		$escape_anchor_filter = array();

		$text = $this->escape($text);

		// find all occurrences of anchors and fill matches with links
		preg_match_all("/
					<\s*a\s+
					([^<>]+)
					>
					(.*?)
					<\s*\/\s*a\s*>
					/isx", $text, $matches, PREG_SET_ORDER);

		$raw_links = array();
		$processed_links = array();

		foreach ($matches as $match)
		{
			$updated_link = $this->process_link($match);
			if ( $updated_link ) {
				$raw_links[]     = $match[0];
				$processed_links[] = $updated_link;
			}
		}

		if ( !empty($raw_links) && !empty($processed_links) )
			$text = str_replace($raw_links, $processed_links, $text);

		$text = $this->unescape($text);

		return $text;
	} # process_content()


	/**
	 * escape()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function escape($text) {
		global $escape_anchor_filter;

		if ( !isset($escape_anchor_filter) )
			$escape_anchor_filter = array();

		foreach ( array(
			'head' => "/
				.*?
				<\s*\/\s*head\s*>
				/isx",
			'blocks' => "/
				<\s*(script|style|object|textarea)(?:\s.*?)?>
				.*?
				<\s*\/\s*\\1\s*>
				/isx",
			) as $regex ) {
			$text = preg_replace_callback($regex, array($this, 'escape_callback'), $text);
		}

		return $text;
	} # escape()


	/**
	 * escape_callback()
	 *
	 * @param array $match
	 * @return string $text
	 **/

	function escape_callback($match) {
		global $escape_anchor_filter;

		$tag_id = "----escape_sem_external_links:" . md5($match[0]) . "----";
		$escape_anchor_filter[$tag_id] = $match[0];

		return $tag_id;
	} # escape_callback()


	/**
	 * unescape()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function unescape($text) {
		global $escape_anchor_filter;

		if ( !$escape_anchor_filter )
			return $text;

		$unescape = array_reverse($escape_anchor_filter);

		return str_replace(array_keys($unescape), array_values($unescape), $text);
	} # unescape()


	/**
	 * filter_callback()
	 *
	 * @param array $match
	 * @return string $str
	 **/

	function process_link($match) {
		# skip empty anchors
		if ( !trim($match[2]) )
			return $match[0];

		# parse anchor
		$anchor = $this->parse_anchor($match);

		if ( !$anchor )
			return $match[0];

		# filter anchor
		$anchor = $this->filter_anchor( $anchor );

		if ( $anchor )
			$anchor = $this->build_anchor($anchor);

		return $anchor;
	} # process_link()


	/**
	 * parse_anchor()
	 *
	 * @param array $match
	 * @return array $anchor
	 **/

	function parse_anchor($match) {
		$anchor = array();
		$anchor['attr'] = $this->parse_attrs( $match[1] );

		if ( !is_array($anchor['attr']) || empty($anchor['attr']['href']) # parser error or no link
			|| trim($anchor['attr']['href']) != esc_url($anchor['attr']['href'], null, 'db') ) # likely a script
			return false;

		foreach ( array('class', 'rel') as $attr ) {
			if ( !isset($anchor['attr'][$attr]) ) {
				$anchor['attr'][$attr] = array();
			} else {
				$anchor['attr'][$attr] = explode(' ', $anchor['attr'][$attr]);
				$anchor['attr'][$attr] = array_map('trim', $anchor['attr'][$attr]);
			}
		}

		$anchor['body'] = $match[2];

		$anchor['attr']['href'] = @html_entity_decode($anchor['attr']['href'], ENT_COMPAT, get_option('blog_charset'));

		return $anchor;
	} # parse_anchor()


	/**
	 * build_anchor()
	 *
	 * @param array $anchor
	 * @return string $anchor
	 */

	function build_anchor($anchor) {
		$anchor['attr']['href'] = esc_url($anchor['attr']['href']);

		$str = '<a';
		foreach ( $anchor['attr'] as $k => $v ) {
			if ( is_array($v) ) {
				$v = array_unique($v);
				if ( $v )
					$str .= ' ' . $k . '="' . implode(' ', $v) . '"';
			} else {
               if ($k)
				    $str .= ' ' . $k . '="' . $v . '"';
               else
                    $str .= ' ' . $v;
			}
		}
		$str .= '>' . $anchor['body'] . '</a>';

		return $str;
	} # build_anchor()

	/**
	 * Parse an attributes string into an array. If the string starts with a tag,
	 * then the attributes on the first tag are parsed. This parses via a manual
	 * loop and is designed to be safer than using DOMDocument.
	 *
	 * @param    string|*   $attrs
	 * @return   array
	 *
	 * @example  parse_attrs( 'src="example.jpg" alt="example"' )
	 * @example  parse_attrs( '<img src="example.jpg" alt="example">' )
	 * @example  parse_attrs( '<a href="example"></a>' )
	 * @example  parse_attrs( '<a href="example">' )
	 */
	function parse_attrs($attrs) {

	    if ( !is_scalar($attrs) )
	        return (array) $attrs;

	    $attrs = str_split( trim($attrs) );

	    if ( '<' === $attrs[0] ) # looks like a tag so strip the tagname
	        while ( $attrs && ! ctype_space($attrs[0]) && $attrs[0] !== '>' )
	            array_shift($attrs);

	    $arr = array(); # output
	    $name = '';     # for the current attr being parsed
	    $value = '';    # for the current attr being parsed
	    $mode = 0;      # whether current char is part of the name (-), the value (+), or neither (0)
	    $stop = false;  # delimiter for the current $value being parsed
	    $space = ' ';   # a single space
		$paren = 0;     # in parenthesis for js attrs

	    foreach ( $attrs as $j => $curr ) {

	        if ( $mode < 0 ) {# name
	            if ( '=' === $curr ) {
	                $mode = 1;
	                $stop = false;
	            } elseif ( '>' === $curr ) {
	                '' === $name or $arr[ $name ] = $value;
	                break;
	            } elseif ( !ctype_space($curr) ) {
	                if ( ctype_space( $attrs[ $j - 1 ] ) ) { # previous char
	                    '' === $name or $arr[ $name ] = '';   # previous name
	                    $name = $curr;                        # initiate new
	                } else {
	                    $name .= $curr;
	                }
	            }
	        } elseif ( $mode > 0 ) {# value
		        if ( $paren ) {
			        $value .= $curr;
                    if ( $curr === "(")
                        $paren += 1;
                    elseif ( $curr === ")")
                        $paren -= 1;
		        }
		        else {
		            if ( $stop === false ) {
		                if ( !ctype_space($curr) ) {
		                    if ( '"' === $curr || "'" === $curr ) {
		                        $value = '';
		                        $stop = $curr;
		                    } else {
		                        $value = $curr;
		                        $stop = $space;
		                    }
		                }
		            } elseif ( $stop === $space ? ctype_space($curr) : $curr === $stop ) {
		                $arr[ $name ] = $value;
		                $mode = 0;
		                $name = $value = '';
		            } else {
		                $value .= $curr;
			            if ( $curr === "(")
	                        $paren += 1;
	                    elseif ( $curr === ")")
	                        $paren -= 1;
		            }
		        }
	        } else {# neither

	            if ( '>' === $curr )
	                break;
	            if ( !ctype_space( $curr ) ) {
	                # initiate
	                $name = $curr;
	                $mode = -1;
	            }
	        }
	    }

	    # incl the final pair if it was quoteless
	    '' === $name or $arr[ $name ] = $value;

	    return $arr;
	}

	/**
	 * Updates attribute of an HTML tag.
	 *
	 * @param $html
	 * @param $attr_name
	 * @param $new_attr_value
	 * @return string
	 */
	function update_attribute($html, $attr_name, $new_attr_value) {

		$attr_value     = false;
		$quote          = false; // quotes to wrap attribute values

		if (preg_match('/\s' . $attr_name . '="([^"]*)"/iu', $html, $matches)
			|| preg_match('/\s' . $attr_name . "='([^']*)'/iu", $html, $matches)
		) {
			// two possible ways to get existing attributes
			$attr_value = $matches[1];

			$quote = false !== stripos($html, $attr_name . "='") ? "'" : '"';
		}

		if ($attr_value)
		{
			//replace current attribute
			return str_ireplace("$attr_name=" . $quote . "$attr_value" . $quote,
				$attr_name . '="' . esc_attr($new_attr_value) . '"', $html);
		}
		else {
			// attribute does not currently exist, add it
			return str_ireplace('>', " $attr_name=\"" . esc_attr($new_attr_value) . '">', $html);
		}
	} # update_attribute()


	/**
	 * filter_anchor()
	 *
	 * @param $anchor
	 * @return string
	 */

	function filter_anchor($anchor) {
		# disable in feeds
		if ( is_feed() )
			return null;

		# ignore local urls
		if ( sem_external_links::is_local_url($anchor['attr']['href']) )
			return null;

		# no icons for images
		$is_image = (bool) preg_match("/^\s*<\s*img\s.+?>\s*$/is", $anchor['body']);

		$updated = false;
		if ( !in_array('external', $anchor['attr']['class']) ) {
			$anchor['attr']['class'][] = 'external';
			$updated = true;
		}

		if ( !$is_image && $this->opts['icon'] && !in_array('external_icon', $anchor['attr']['class'])
			&& !in_array('no_icon', $anchor['attr']['class'])
			&& !in_array('noicon', $anchor['attr']['class']) ) {
			$anchor['attr']['class'][] = 'external_icon';
			$updated = true;
		}

		if ( $this->opts['nofollow'] && !in_array('nofollow', $anchor['attr']['rel'])
			&& !in_array('follow', $anchor['attr']['rel']) ) {
				$anchor['attr']['rel'][] = 'nofollow';
				$updated = true;
		}

		if ( $this->opts['target'] && empty($anchor['attr']['target']) ) {
		 	$anchor['attr']['target'] = '_blank';
			$updated = true;
		}

		if ( $updated )
			return $anchor;
		else
			return null;
	} # filter_anchor()


	/**
	 * is_local_url()
	 *
	 * @param string $url
	 * @return bool $is_local_url
	 **/

	function is_local_url($url) {
		if ( in_array(substr($url, 0, 1), array('?', '#')) )
			return true;
		elseif ( (substr($url, 0, 2) != '//') && (strpos($url, 'http://') === false) && (strpos($url, 'https://') === false) )
			return true;
		elseif ( $url == 'http://' || $url == 'https://' )
			return true;
		elseif ( preg_match("~/go(/|\.)~i", $url) )
			return false;
		
		static $site_domain;
		
		if ( !isset($site_domain) ) {
			$site_domain = home_url();
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
		
		$link_domain = @parse_url($url);
        if ($link_domain === false)
            return true;
        elseif (is_array($link_domain)) {
            if (isset($link_domain['host']))
		        $link_domain = $link_domain['host'];
            else
                return false;
        }

		$link_domain = strtolower($link_domain);
		$link_domain = str_replace('www.', '', $link_domain);
		if ( $this->opts['subdomains_local'] ) {
			$subdomains = $this->extract_subdomains($link_domain);
			if ( $subdomains != '')
				$link_domain = str_replace($subdomains . '.', '', $link_domain);
		}
		
		if ( $site_domain == $link_domain ) {
			return true;
		} elseif ( function_exists('is_multisite') && is_multisite() ) {
			return false;
		} else {
			return false;
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
	 * extract_domain()
	 *
	 * @param string $domain
	 * @return string
	 **/
	function extract_domain($domain)
	{
	    if(preg_match("/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i", $domain, $matches))
	    {
	        return $matches['domain'];
	    } else {
	        return $domain;
	    }
	} # extract_domain()

	/**
	 * extract_subdomains()
	 *
	 * @param string $domain
	 * @return string
	 **/
	function extract_subdomains($domain)
	{
	    $subdomains = $domain;
	    $domain = $this->extract_domain($subdomains);

	    $subdomains = rtrim(strstr($subdomains, $domain, true), '.');

	    return $subdomains;
	} # extract_subdomains()


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

		if ( $o === false || !isset($o['text_widgets']) || !isset($o['autolinks']) || !isset($o['version']) )
			$o = sem_external_links::init_options();

		return $o;
	} # get_options()


	/**
	 * init_options()
	 *
	 * @return array $options
	 **/

	static function init_options() {
		$o = get_option('external_links');

		$defaults = array(
					'global' => false,
					'icon' => false,
					'target' => false,
					'nofollow' => true,
					'text_widgets' => true,
					'autolinks' => false,
					'subdomains_local' => true,
					'version' => sem_external_links_version,
					);

		if ( !$o )
			$updated_opts  = $defaults;
		else
			$updated_opts = wp_parse_args($o, $defaults);

		if ( !isset( $o['version'] )) {

			if ( sem_external_links::replace_plugin('sem-autolink-uri/sem-autolink-uri.php') )
				$updated_opts['autolinks'] = true;
		}

		update_option('external_links', $updated_opts);

		return $updated_opts;
	} # init_options()

	/**
	 * replace_plugin()
	 *
	 * @param $plugin_name
	 * @return bool
	 */
	static function replace_plugin( $plugin_name ) {
		$active_plugins = get_option('active_plugins');

		if ( !is_array($active_plugins) )
		{
			$active_plugins = array();
		}

		$was_active = false;
		foreach ( (array) $active_plugins as $key => $plugin )
		{
			if ( $plugin == $plugin_name )
			{
				$was_active = true;
				unset($active_plugins[$key]);
				break;
			}
		}

		sort($active_plugins);

		update_option('active_plugins', $active_plugins);

		return $was_active;
	}
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

$sem_external_links = sem_external_links::get_instance();