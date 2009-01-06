<?php

class external_links_admin
{
	#
	# init()
	#
	
	function init()
	{
		add_action('admin_menu', array('external_links_admin', 'admin_menu'));
	} # init()
	

	#
	# update_options()
	#

	function update_options()
	{
		check_admin_referer('external_links');

		#echo '<pre>';
		#var_dump($_POST['sem_external_links']);
		#echo '</pre>';

		$options = $_POST['sem_external_links'];
		$options['global'] = isset($options['global']);
		$options['add_css'] = isset($options['add_css']);
		$options['add_target'] = isset($options['add_target']);
		$options['add_nofollow'] = isset($options['add_nofollow']);

		update_option('sem_external_links_params', $options);
	} # update_options()


	#
	# admin_menu()
	#

	function admin_menu()
	{
		add_options_page(
				__('External&nbsp;Links'),
				__('External&nbsp;Links'),
				'manage_options',
				__FILE__,
				array('external_links_admin', 'admin_page')
				);
	} # admin_menu()
	

	#
	# admin_page()
	#

	function admin_page()
	{
		# Acknowledge update

		if ( isset($_POST['update_sem_external_links_options'])
			&& $_POST['update_sem_external_links_options']
			)
		{
			external_links_admin::update_options();

			echo "<div class=\"updated\">\n"
				. "<p>"
					. "<strong>"
					. __('Settings saved.')
					. "</strong>"
				. "</p>\n"
				. "</div>\n";
		}

		$options = get_option('sem_external_links_params');

		# show controls

		echo "<div class=\"wrap\">\n"
			. "<h2>" . __('External Links Settings') . "</h2>\n"
			. "<form method=\"post\" action=\"\">\n";

		if ( function_exists('wp_nonce_field') ) wp_nonce_field('external_links');

		echo '<input type="hidden" name="update_sem_external_links_options" value="1">';

		echo '<table class="form-table">';

		echo '<tr>'
			. '<td>'
			. '<label for="sem_external_links[global]">'
			. '<input type="checkbox"'
				. ' name="sem_external_links[global]" id="sem_external_links[global]"'
				. ( $options['global'] ? ' checked="checked"' : '' )
				. ' />'
			. '&nbsp;'
			. __('Process all outbound links as configured below. This means links in the sidebars, header, footer and so on in addition to those in posts\' and pages\' content. Note: If you add a nofollow attribute, be sure to turn this off if you wish to let your commenters to have some Google Juice. Note: In the <a href="http://www.semiologic.com/software/wp-themes/">Semiologic Reloaded theme</a>, icons are not added outside of the main area.')
			. '</label>'
			. '</td>'
			. '</tr>';

		echo '<tr>'
			. '<td>'
			. '<label for="sem_external_links[add_css]">'
			. '<input type="checkbox"'
				. ' name="sem_external_links[add_css]" id="sem_external_links[add_css]"'
				. ( $options['add_css'] ? ' checked="checked"' : '' )
				. ' />'
			. '&nbsp;'
			. __('Add an external link icon to outbound links. You can use a class="noicon" attribute on individual links to override this.')
			. '</label>'
			. '</td>'
			. '</tr>';

		echo '<tr>'
			. '<td>'
			. '<label for="sem_external_links[add_target]">'
			. '<input type="checkbox"'
				. ' name="sem_external_links[add_target]" id="sem_external_links[add_target]"'
				. ( $options['add_target'] ? ' checked="checked"' : '' )
				. ' />'
			. '&nbsp;'
			. __('Open outbound links in new windows. Some usability experts suggest <a href="http://www.useit.com/alertbox/9605.html">this can damage your visitor\'s trust</a> towards your site. Others highlight that some users (mainly elderly) do not know how to use the back button and encourage the practice.')
			. '</label>'
			. '</td>'
			. '</tr>';

		echo '<tr>'
			. '<td>'
			. '<label for="sem_external_links[add_nofollow]">'
			. '<input type="checkbox"'
				. ' name="sem_external_links[add_nofollow]" id="sem_external_links[add_nofollow]"'
				. ( $options['add_nofollow'] ? ' checked="checked"' : '' )
				. ' />'
			. '&nbsp;'
			. __('Add rel=nofollow to outbound links. This is not very nice for those you\'re linking to.')
			. '</label>'
			. '</td>'
			. '</tr>';

		echo '</table>';

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . attribute_escape(__('Save Changes')) . '"'
				. ' />'
			. '</p>';

		echo '</form>'
			. '</div>';
	} # admin_page()
} # external_links_admin

external_links_admin::init();

?>