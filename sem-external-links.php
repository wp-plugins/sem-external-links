<?php
/*
Plugin Name: External Links
Plugin URI: http://www.semiologic.com/software/publishing/external-links/
Description: Adds a class=&quot;external&quot; to all outbound links, with various effects that are configurable under Options / External Links. Use &lt;a class=&quot;no_icon&quot; ...&gt; to disable the icon on individual links.
Author: Denis de Bernardy
Version: 3.0.4
Author URI: http://www.getsemiologic.com
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts (http://www.mesoconcepts.com), and is distributed under the terms of the GPL license, v.2.

http://www.opensource.org/licenses/gpl-2.0.php
**/


class external_links
{
	#
	# init()
	#
	
	function init()
	{
		add_action('wp_head', array('external_links', 'wp_head'));
		
		add_action('the_content', array('external_links', 'filter'), 40);
		add_action('the_excerpt', array('external_links', 'filter'), 40);
	} # init()
	
	
	#
	# get_options()
	#
	
	function get_options()
	{
		if ( ( $o = get_option('sem_external_links_params') ) === false )
		{
			$o = array(
				'global' => true,
				'add_css' => true,
				'add_target' => false,
				'add_nofollow' => false,
				);
			
			update_option('sem_external_links_params', $o);
		}
		
		return $o;
	} # get_options()
	
	
	#
	# wp_head()
	#
	
	function wp_head()
	{
		$options = external_links::get_options();

		if ( $options['add_css'] )
		{
			echo '<link rel="stylesheet" type="text/css"'
				. ' href="'
					. trailingslashit(get_option('siteurl'))
					. 'wp-content/plugins/sem-external-links/sem-external-links.css?ver=3.0'
					. '"'
				. ' />' . "\n";
		}
		
		if ( $options['global'] )
		{
			remove_action('the_content', array('external_links', 'filter'), 40);
			remove_action('the_excerpt', array('external_links', 'filter'), 40);
			
			$GLOBALS['did_external_links'] = false;
			ob_start(array('external_links', 'filter'));
			add_action('wp_footer', array('external_links', 'ob_flush'), 1000000000);
		}
	} # wp_head()
	
	
	#
	# filter()
	#
	
	function filter($buffer)
	{
		# escape head
		$buffer = preg_replace_callback(
			"/
			^.*
			<\s*\/\s*head\s*>		# everything up to where the body starts
			/isUx",
			array('external_links', 'escape'),
			$buffer
			);

		# escape scripts
		$buffer = preg_replace_callback(
			"/
			<\s*script				# script tag
				(?:\s[^>]*)?		# optional attributes
				>
			.*						# script code
			<\s*\/\s*script\s*>		# end of script tag
			/isUx",
			array('external_links', 'escape'),
			$buffer
			);

		# escape objects
		$buffer = preg_replace_callback(
			"/
			<\s*object				# object tag
				(?:\s[^>]*)?		# optional attributes
				>
			.*						# object code
			<\s*\/\s*object\s*>		# end of object tag
			/isUx",
			array('external_links', 'escape'),
			$buffer
			);

		global $site_host;

		$site_host = trailingslashit(get_option('home'));
		$site_host = preg_replace("~^https?://~i", "", $site_host);
		$site_host = preg_replace("~^www\.~i", "", $site_host);
		$site_host = preg_replace("~/.*$~", "", $site_host);

		$buffer = preg_replace_callback(
			"/
			<\s*a					# ancher tag
				(?:\s[^>]*)?		# optional attributes
				\s*href\s*=\s*		# href=...
				(
					\"[^\"]*\"		# double quoted link
				|
					'[^']*'			# single quoted link
				|
					[^'\"]\S*		# non-quoted link
				)
				(?:\s[^>]*)?		# optional attributes
				\s*>
			/isUx",
			array('external_links', 'filter_callback'),
			$buffer
			);

		# unescape anchors
		$buffer = external_links::unescape($buffer);
		
		$GLOBALS['did_external_links'] = true;

		return $buffer;
	} # filter()
	
	
	#
	# filter_callback()
	#
	
	function filter_callback($input)
	{
		global $site_host;

		$anchor = $input[0];
		$link = $input[1];

	#	echo '<pre>';
	#	var_dump(
	#		get_option('sem_external_links_params'),
	#		htmlspecialchars($link),
	#		htmlspecialchars($anchor)
	#		);
	#	echo '</pre>';

		if ( ( strpos($link, '://') !== false
				&& !preg_match(
					"/
						https?:\/\/
						(?:www\.)?
						" . str_replace('.', '\.', $site_host) . "
					/ix",
					$link
					)
				)
			|| preg_match("/
					\/
					(?:go|get)
					(?:\.|\/)
					/ix",
					$link
					)
			)
		{
			$options = get_option('sem_external_links_params');

			if ( $options['add_css'] )
			{
				if ( preg_match(
					"/
						\s
						class\s*=\s*
						(?:
							\"([^\"]*)\"
						|
							'([^']*)'
						|
							([^\"'][^\s>]*)
						)
					/iUx",
					$anchor,
					$match
					) )
				{
					#echo '<pre>';
					#var_dump($match);
					#echo '</pre>';

					if ( !preg_match(
						"/
							\b
							(?:
								no_?icon
							|
								external
							)
							\b
						/ix",
						$match[1]
						) )
					{
						$anchor = str_replace(
							$match[0],
							' class="' . $match[1] . ' external"',
							$anchor
							);
					}
				}
				else
				{
					$anchor = str_replace(
						'>',
						' class="external">',
						$anchor
						);
				}
			}

			if ( $options['add_target'] )
			{
				if ( !preg_match(
					"/
						\s
						target\s*=
					/iUx",
					$anchor
					) )
				{
					$anchor = str_replace(
						'>',
						' target="_blank">',
						$anchor
						);
				}
			}

			if ( $options['add_nofollow'] )
			{
				if ( preg_match(
					"/
						\s
						rel\s*=\s*
						(?:
							\"([^\"]*)\"
						|
							'([^']*)'
						|
							([^\"'][^\s>]*)
						)
					/iUx",
					$anchor,
					$match
					) )
				{
					#echo '<pre>';
					#var_dump($match);
					#echo '</pre>';

					if ( !preg_match(
						"/
							\b
							(?:
								nofollow
							|
								follow
							)
							\b
						/ix",
						$match[1]
						) )
					{
						$anchor = str_replace(
							$match[0],
							' rel="' . $match[1] . ' nofollow"',
							$anchor
							);
					}
				}
				else
				{
					$anchor = str_replace(
						'>',
						' rel="nofollow">',
						$anchor
						);
				}
			}
		}

		return $anchor;
	} # filter_callback()
	
	
	#
	# escape()
	#

	function escape($input)
	{
		global $escaped_external_links;

		#echo '<pre>';
		#var_dump($input);
		#echo '</pre>';

		$tag_id = '--escaped_external_link:' . md5($input[0]) . '--';
		$escaped_external_links[$tag_id] = $input[0];

		return $tag_id;
	} # escape()
	
	
	#
	# unescape()
	#

	function unescape($input)
	{
		global $escaped_external_links;

		$find = array();
		$replace = array();

		foreach ( (array) $escaped_external_links as $key => $val )
		{
			$find[] = $key;
			$replace[] = $val;
		}

		return str_replace($find, $replace, $input);
	} # unescape()
	
	
	#
	# ob_flush()
	#
	
	function ob_flush()
	{
		$i = 0;
		
		while ( !$GLOBALS['did_external_links'] && $i++ < 100 )
		{
			@ob_end_flush();
		}
	} # ob_flush()
} # external_links

external_links::init();


# include admin stuff when relevant
if ( is_admin() )
{
	include dirname(__FILE__) . '/sem-external-links-admin.php';
}
?>