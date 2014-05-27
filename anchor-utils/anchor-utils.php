<?php
/*
 * Anchor Utils
 * Author: Denis de Bernardy & Mike Koepke <http://www.semiologic.com>
 * Version: 1.6.1
 */

if ( @ini_get('pcre.backtrack_limit') <= 1000000 )
	@ini_set('pcre.backtrack_limit', 1000000);
if ( @ini_get('pcre.recursion_limit') <= 250000 )
	@ini_set('pcre.recursion_limit', 250000);

/**
 * anchor_utils
 *
 * @package Anchor Utils
 **/

class anchor_utils {

	/**
     * constructor
     */
    public function __construct( $inc_text_widgets = true ) {
	    $priority = 1000000000;

        add_filter('the_content', array($this, 'filter'), 100);
        add_filter('the_excerpt', array($this, 'filter'), 100);
        add_filter('comment_text', array($this, 'filter'), 100);
	    if ( $inc_text_widgets )
	        add_filter('widget_text', array($this, 'filter'), 100);

        add_action('wp_head', array($this, 'ob_start'), 10000);
    } #anchor_utils


    /**
	 * ob_start()
	 *
	 * @return void
	 **/

	function ob_start() {
		echo '<!-- external-links  ' . 'ob_start' . ' -->' . "\n";
		static $done = false;

		if ( $done )
			return;

		if ( has_filter('ob_filter_anchor') ) {
			ob_start(array($this, 'ob_filter'));
			add_action('wp_footer', array($this, 'ob_flush'), 10000);
			$done = true;
		}
	} # ob_start()

	/**
	 * ob_filter()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function ob_filter($text) {
		global $escape_anchor_filter;
		$escape_anchor_filter = array();

		$text .= '<!-- external-links  ' . 'ob_filter' . ' -->' . "\n";

		$text = $this->escape($text);

		$text = preg_replace_callback("/
			<\s*a\s+
			([^<>]+)
			>
			(.*?)
			<\s*\/\s*a\s*>
			/isx", array($this, 'ob_filter_callback'), $text);

		$text = $this->unescape($text);

		return $text;
	} # ob_filter()


	/**
	 * ob_flush()
	 *
	 * @return void
	 **/

	static function ob_flush() {
		static $done = true;

		if ( $done )
			return;

		ob_end_flush();
		$done = true;
	} # ob_flush()


	/**
	 * ob_filter_callback()
	 *
	 * @param array $match
	 * @return string $str
	 **/

	function ob_filter_callback($match) {
		# skip empty anchors
		if ( !trim($match[2]) )
			return $match[0];

		# parse anchor
		$anchor = $this->parse_anchor($match);

		if ( !$anchor )
			return $match[0];

		$anchor['current_filter'] = current_filter();

		# filter anchor
		$anchor = apply_filters( 'ob_filter_anchor', $anchor );
		unset( $anchor['current_filter'] );

		# return anchor
		return $this->build_anchor($anchor);
	} # ob_filter_callback()


	/**
	 * filter()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function filter($text) {
		$text .= '<!-- external-links  ' . current_filter() . ' -->' . "\n";
		if ( !has_filter('filter_anchor') )
			return $text;

		global $escape_anchor_filter;
		$escape_anchor_filter = array();

		$text = $this->escape($text);

		$text = preg_replace_callback("/
			<\s*a\s+
			([^<>]+)
			>
			(.*?)
			<\s*\/\s*a\s*>
			/isx", array($this, 'filter_callback'), $text);

		$text = $this->unescape($text);

		return $text;
	} # filter()


	/**
	 * filter_callback()
	 *
	 * @param array $match
	 * @return string $str
	 **/

	function filter_callback($match) {
		# skip empty anchors
		if ( !trim($match[2]) )
			return $match[0];

		# parse anchor
		$anchor = $this->parse_anchor($match);

		if ( !$anchor )
			return $match[0];

		$anchor['current_filter'] = current_filter();

		# filter anchor
		$anchor = apply_filters( 'filter_anchor', $anchor );
		unset( $anchor['current_filter'] );

		# return anchor
		return $this->build_anchor($anchor);
	} # filter_callback()


	/**
	 * parse_anchor()
	 *
	 * @param array $match
	 * @return array $anchor
	 **/

	function parse_anchor($match) {
		$anchor = array();
//		$anchor['attr'] = shortcode_parse_atts($match[1]);
		$anchor['attr'] = $this->parse_attrs($match[1]);

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
	 **/

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

		$tag_id = "----escape_anchor_utils:" . md5($match[0]) . "----";
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
} # anchor_utils
