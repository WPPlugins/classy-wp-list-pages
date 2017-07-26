<?php
/*
Plugin Name: Classy wp_list_pages
Plugin URI: http://code.dunae.ca/classy_wp_list_pages
Description: Adds a uniqe class or ID attribute to each LI tag generated by wp_list_pages() and wp_nav_menu() allowing them to be individually styled with CSS.
Author: Alex Dunae
Version: 1.4,0
Author URI: http://dunae.ca/
*/

/**************************************

 Hooks and configuration

***************************************/
/*add_filter('page_css_class', 'abcde', 10, 2);
function abcde ($css_class, $page) {
var_dump($css_class);
var_dump($page);
die;
}*/
add_filter('wp_list_pages','c_wp_lp_filter');
add_filter('wp_nav_menu', 'c_wp_lp_filter');
add_action('admin_menu', 'c_wp_lp_add_menus');

$c_wp_lp_options = array('c_wp_lp_prefix' => 'Class prefix',
                         'c_wp_lp_sep' => 'Class separator',
                         'c_wp_lp_attr' => 'Attribute',
                         'c_wp_lp_first_class' => 'Class to add to first element',
                         'c_wp_lp_last_class' => 'Class to add to last element');


// for WordPress 2.7 and MU
add_action('admin_init', 'classy_wp_list_pages_init' );
function classy_wp_list_pages_init() {
	global $c_wp_lp_options;

	if( function_exists( 'register_setting' ) ) {
		foreach ( $c_wp_lp_options as $k => $v ) {
			register_setting( 'classy-wp-list-pages' , $k );
		}
	}
}

$c_wp_lp_values = array();


/**************************************

 wp_list_pages() filter

***************************************/
function c_wp_lp_filter_callback($matches) {
	global $c_wp_lp_values;

	$prefix = (strlen($c_wp_lp_values['c_wp_lp_prefix'])) > 0 ?
	              $c_wp_lp_values['c_wp_lp_prefix'] . $c_wp_lp_values['c_wp_lp_sep'] :
	              '';

	// build the identifier
	//  - strip trailing and preceding slashes
	//  - replace the remaining slashes with the separator (from get_options())
	$identifier = '';
	if( $matches[5] && !empty($matches[5]) ) {
		$identifier = preg_replace('/(^\/|\/$)/', '', $matches[5]);
		
		if ( empty($identifier) ) {
			$identifier = 'frontpage';			
		} else {
			$identifier = str_replace('/', $c_wp_lp_values['c_wp_lp_sep'], $identifier);
		}
	} else { // if the url is blank, this is the homepage
		$identifier = 'frontpage';
	}

	$identifier = $prefix . $identifier;

	$filtered = '';

	if( $c_wp_lp_values['c_wp_lp_attr'] == 'id' ) {
		$filtered = sprintf("<li id=\"%s\" class=\"%s\"><a href=\"%s\"", $identifier, $matches[4], get_option('home') . $matches[5]);
	} else {
		// append any existing classes and trim out extra spaces
		$class = $identifier . ' ' . trim($matches[2]);
		$class = preg_replace('/[ ]+/', ' ', $class);
		$filtered = sprintf("<li%s class=\"%s\"><a href=\"%s\"", 
			$matches[2] ? ' id="' . $matches[2] . '"' : '', $class, get_option('home') . $matches[5]);
	}

	return $filtered;
}

function c_wp_lp_filter($content) {
	global $c_wp_lp_values;
	// load plugin options from the database
	$c_wp_lp_values = c_wp_lp_get_values();

	// escape the blog's base URL
	$url = preg_replace(array('/\//', '/\./', '/\-/'), array('\/', '\.', '\-'), get_option('home'));

	$pattern = '/<li( id=\"([\w\-]+)\")?( class=\"([\w\s_\-]+)\")?><a href=\"' . $url . '([\w\-_\/]*)"/i';

	$content = preg_replace_callback($pattern, "c_wp_lp_filter_callback", $content);
	
	// add class to first list item
	if ( !empty($c_wp_lp_values['c_wp_lp_first_class']) ) {
		$content = preg_replace('/(<ul\b[^>]*>|^)[\s]*<li([\s]*id=("[\w_-]+"|\'[\w_-]+\')[\s]*)? class="/i', 
		                        "$1<li$2 class=\"" . $c_wp_lp_values['c_wp_lp_first_class'] . ' ', 
		                        $content, 
		                        -1);
	}

	$content = str_replace('class="', 'class=" ', $content);

	// add class to last list item; reverses and tokenizes the string
	if ( !empty($c_wp_lp_values['c_wp_lp_last_class']) ) {
		$rev_class = strrev($c_wp_lp_values['c_wp_lp_last_class']);

		// add spaces between tags and reverse
		$content = strrev( preg_replace( '/><(\/?(ul|li))/i', '> <$1', $content ) );
		$out = '';
		$list_depth = 0;
		
		$t = strtok( $content, " \n\t" );

		while ($t !== false) {
			if ( $list_depth > 0) {
				// detected `page_item` or `menu-item` class in the first LI before the UL
				if ( stripos($t, 'meti_egap') !== FALSE || stripos($t, 'meti-unem') !== FALSE) {
					$t .= " $rev_class";
					$list_depth -= 1;
				}
			}

			// increase depth after encountering a closing UL
			if ( strcasecmp( $t, '>lu/<' ) == 0 ) {
				$list_depth += 1;
				$t .= "\n";
			}

			$out .= $t . ' ';
			$t = strtok( " \n\t" );
		}

		$out = str_replace( '<li', "\n<li", strrev( $out ) );
		$out = str_replace('class=" ', 'class="', $out);
		$content = $out;
	}

	return $content;
}



/**************************************

 Menu functions

***************************************/
function c_wp_lp_add_menus() {
	add_options_page('Classy wp_list_pages Options', 'Classy wp_list_pages', 8, 'c_wp_lp_options', 'c_wp_lp_options_page');
}


// read this plugin's options from the database
function c_wp_lp_get_values() {
	global $c_wp_lp_options;

	$opt_values = array();

	foreach( $c_wp_lp_options as $k => $v )
		$opt_values[$k] = get_option($k);

	// default value for separator is an underscore
	if(strlen(trim($opt_values['c_wp_lp_sep'])) < 1)
		$opt_values['c_wp_lp_sep'] = '_';

	// default value for attribute is ID
	if(strlen(trim($opt_values['c_wp_lp_attr'])) < 1)
		$opt_values['c_wp_lp_attr'] = 'id';

	return $opt_values;
}

function c_wp_lp_options_page() {
	global $c_wp_lp_options;


    echo '<div class="wrap">';

    echo "<h2>" . __( 'Classy wp_list_pages Options', 'c_wp_lp_trans_domain' ) . "</h2>";

    // options form

    ?>

<form method="post" action="options.php">
<?php 	
	if( function_exists( 'settings_fields' ) ):
		settings_fields('classy-wp-list-pages');
	else: 
		wp_nonce_field('update-options');
?>
	<input type="hidden" name="page_options" value="c_wp_lp_attr,c_wp_lp_prefix,c_wp_lp_sep,c_wp_lp_first_class,c_wp_lp_last_class" />		
	<input type="hidden" name="action" value="update" />
<?php endif; ?>

	<p>Should the identifier be applied to the class or ID attribute?<br />(Any existing IDs will be overwritten if you select ID.)<br /><br/>
		<?php _e("Attribute:", 'c_wp_lp_trans_domain' ); ?>
		<select name="c_wp_lp_attr">
			<option value="id"<?php echo get_option('c_wp_lp_attr') == 'id' ? ' selected="selected"' : ''; ?>>ID</option>
			<option value="class"<?php echo get_option('c_wp_lp_attr') == 'class' ? ' selected="selected"' : ''; ?>>Class</option>
		</select>
	</p><hr />

	<p>An optional string appended to the beginning of the generated identifier (letters and numbers only).<br /><br />
	<?php _e("Prefix:", 'c_wp_lp_trans_domain' ); ?>
		<input type="text" name="c_wp_lp_prefix" value="<?php echo get_option('c_wp_lp_prefix'); ?>" size="5">
	</p><hr />


	<p>What character should separate each token in the identifier?<br /><br />
	<?php _e("Token separator:", 'c_wp_lp_trans_domain' ); ?>
		<select name="c_wp_lp_sep">
			<option value="-"<?php echo $opt_values['c_wp_lp_sep'] == '-' ? ' selected="selected"' : ''; ?>>Dash (-)</option>
			<option value="_"<?php echo $opt_values['c_wp_lp_sep'] == '_' ? ' selected="selected"' : ''; ?>>Underscore (_)</option>
		</select>
	</p><hr />

	<p>	<?php _e("Class to add to the first item:", 'c_wp_lp_trans_domain' ); ?>
		<input type="text" name="c_wp_lp_first_class" value="<?php echo get_option('c_wp_lp_first_class'); ?>" size="25">
	</p><hr />


	<p>	<?php _e("Class to add to the last item:", 'c_wp_lp_trans_domain' ); ?>
		<input type="text" name="c_wp_lp_last_class" value="<?php echo get_option('c_wp_lp_last_class'); ?>" size="25">
	</p><hr />

	<p class="submit">
		<input type="submit" name="Submit" value="<?php _e('Update Options »', 'c_wp_lp_trans_domain' ) ?>" />
	</p>
</form>
</div>

<?php } ?>