<?php
/*
Plugin Name: BDN InDesign
Plugin URI: http://dev.bangordailynews.com/
Description: Convert WordPress posts to WordPress tagged text
Author: wpdavis
Version: 0.1
Author URI: http://bangordailynews.com
License: GPL2+
*/

class BDN_InDesign {

	//API key for making calls from Indesign
	//Set this.
	var $api_key = '';

	/*
	 * Initialize our class
	 *
	 */
	function BDN_InDesign() {

		register_activation_hook( __FILE__, array( &$this, 'create_folder' ) );

		//Save a text file every time a post is saved
		add_action( 'save_post', array( &$this, 'generate_files' ), 99, 1 );
		
		//Hook into admin-ajax to return a JSON object for the WP Browser
		add_action( 'wp_ajax_wp-browser-search', array( &$this, 'wp_browser_search' ) );
		add_action( 'wp_ajax_nopriv_wp-browser-search', array( &$this, 'wp_browser_search' ) );

		//Save a notification
		add_action( 'wp_ajax_wp-browser-notify', array( &$this, 'wp_browser_notify' ) );
		add_action( 'wp_ajax_nopriv_wp-browser-notify', array( &$this, 'wp_browser_notify' ) );

	}

	
	/*
	 * Create the indesign folder on activation
	 *
	 */
	function create_folder() {
		$upload_dirs = wp_upload_dir();
		wp_mkdir_p( $upload_dirs[ 'basedir' ] . '/indesign/' );
	}
	
	/*
	 * Save the file to wp-content/uploads/indesign/
	 * The files can be synced to a local server using rsync
	 */
	function generate_files( $post_id ) {
	
		//Don't save on autosave
		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return $post_id;
		
		//Else it will try to save a file when creating the hello world post when creating a new blog
		if( is_network_admin() )
			return $post_id;
		
		$post = get_post( $post_id );

		//Don't save autodrafts, revisions or images
		if( $post->post_type == 'revision' || $post->post_status == 'auto-draft' || $post->post_status == 'inherit' || $post->post_type == 'attachment' )
			return $post_id;
	
		//Find the uploads directory and generate the location to save the file
		$upload_dirs = wp_upload_dir();
		$upload_dir = $upload_dirs[ 'basedir' ] . '/indesign/';
		$filename = $upload_dir . $post->ID . '.txt';
	
		//Generate the tagged text for the post
		$string = $this->do_tagged_text( $post );
		
		//Save the tagged text to the file
		$fh = fopen( $filename, 'w' ) or die( 'Could not open file ' . $post->ID . ' at ' . $filename . '<br>' );
		fwrite( $fh, $string );
		fclose( $fh );

	}
	
	
	/*
	 * Generate tagged text for a specific post
	 *
	 */
	function do_tagged_text( $post ) {
	
		//You can set a format to define how fields are mapped
		$format = get_post_meta( $post->ID, '_format', TRUE );
		//Check for a nonsystem byline
		$byline = get_post_meta( $post->ID, '_byline', TRUE );
		
		//If there's not a nonsystem byline, use coauthors
		if( empty( $byline ) && function_exists( 'get_coauthors' ) ) {
		
			//Get the WordPress users attached to the post
			$authors = get_coauthors( $post->ID );
			
			$byline = '';
			
			//Go through each author
			foreach( $authors as $authorentry ) {
				$byline .= $authorentry->display_name;
				if( $authorentry != end( $authors ) ) {
					$byline .= ' and ';
				} else {
					//At the end, we append the user's title (BDN Staff, Special to the BDN, etc.)
					//This doesn't take into account when a post has multiple authors with different titles 
					$byline_title = get_user_meta( $authorentry->ID, '_staff_title', TRUE );
					if( !empty( $byline_title ) )
						$byline .= ', ' . $byline_title;
				}
			}
		
		} elseif( empty( $byline ) ) {
			
			$author = get_user_by( 'id', $post->post_author );
			$byline_title = get_user_meta( $post->post_author, '_staff_title', TRUE );

			$byline = $author->display_name;
			if( !empty( $byline_title ) )
				$byline .= ', ' . $byline_title;
			
		}
					
		//Try to turn special chars, etc into unicode
		$copy = htmlspecialchars_decode( html_entity_decode( $post->post_content ) );
			
		//Get rid of youtube embeds
		//@TODO: Strip out all shortcodes
		$copy = preg_replace( '/\[youtube=(.*?)>\]/', '', $copy );
			
		//Strip tags we don't need
		$copy = strip_tags( $copy, '<p><br><b><strong><li><i><em><h4><h3><h5>' );
			
		//Get rid of all classes and IDs
		$copy = preg_replace( '/<p(.*?)>/', '<p>', $copy );
		$copy = preg_replace( '/<h4(.*?)>/', '<h4>', $copy );
		$copy = preg_replace( '/<h3(.*?)>/', '<h3>', $copy );
		$copy = preg_replace( '/<h5(.*?)>/', '<h5>', $copy );
			
		//Convert our HTML tags to InDesign tags
		//<ct:Bold> is a character tag. This will need to match the character tags for your particular font
		//<ct:> sets the text back to the default style
		$original = array( '/<\/p>/', '/<li>/', '/<br><br>/', '/<p>/', '/<b>/', '/<strong>/', '/<\/b>/', '/<\/strong>/', '/<i>/', '/<em>/', '/<\/i>/', '/<\/em>/' );
		$replacements = array( '',"\r\n","\r\n", "\r\n", '<ct:Bold>', '<ct:Bold>', '<ct:>', '<ct:>', '<ct:Italic>', '<ct:Italic>', '<ct:>', '<ct:>' );
		$copy = preg_replace( $original, $replacements, $copy );
			
		//Convert mdashes to unicode
		$copy = preg_replace( array( '/&mdash;/', '/—/', '/--/' ), '<0x2014>', $copy );
			
		//superscript lowercase a? That doesn't seem right.
		$copy = str_replace( chr(226) . chr(133) . chr(148), '<0x00AA>', $copy );
			
		//Convert smart quotes to unicode
		$smartquotes = array( chr(145), chr(146), chr(147), chr(148), chr(151) ); 
		$smartquotesreplace = array( '<0x2018>', '<0x2019>', '<0x201C>', '<0x201D>', '<0x2014>' ); 
		$copy = str_replace( $smartquotes, $smartquotesreplace, $copy );
			
		//And straight quotes
		$copy = str_replace( array( '&#39;', '&#34;' ), array( '\'', '"' ), $copy );
		$copy = str_replace( array( '&#039;', '&#034;' ), array( '\'','"' ), $copy );
			
		//Curly quotes to unicode
		$quotesoriginal = array( '/‘/','/&lsquo;/','/’/','/&rsquo;/','/“/','/&ldquo;/','/”/','/&rdquo;/', '/…/', '/&hellip;/' );
		$quotesreplace = array( '<0x2018>','<0x2018>','<0x2019>','<0x2019>','<0x201C>','<0x201C>','<0x201D>','<0x201D>', '<0x2026>', '<0x2026>' );
		$copy = preg_replace( $quotesoriginal, $quotesreplace, $copy );

		//Convert fractions to unicode
		//					1/4			1/2			3/4
		$fractions = array(	chr(188),	chr(189),	chr(190) );
		$fractionsreplace = array( '<0x00BC>', '<0x00BD>', '<0x00BE>' );
		$copy = str_replace( $fractions, $fractionsreplace, $copy );
			
		//Strip out Maine in the datelines
		$copy = preg_replace( '/, Maine <0x2014>/', ' <0x2014>', $copy );
			
		//Do some voodoo to standardize line breaks
		$copy = preg_replace( '/<br>/', '<br />', $copy );
		$copy = preg_replace( '|<br />\s*<br />|', "\n", $copy );
		$copy = preg_replace( '/<br \/>/', "\n", $copy );
		$copy = str_replace( array("\r\n", "\r"), "\n", $copy );
		$copy = preg_replace( "/\n\n+/", "\n", $copy );
		$copy = str_replace( '\n\n','\n',$copy );
		$copy = preg_replace( '/\n /', "\n", $copy );
		$copy = preg_replace( '/\n/', "\r\n", $copy );
			
		//Convert spaces to spaces and remove extras
		$copy = str_replace('&nbsp;', ' ', $copy);
		$copy = preg_replace( "/ +/", " ", $copy );
		
		//Convert headers to paragraph styles
		//And on end, convert back to body text
		$copy = str_replace( '<h3>', "\r\n<pstyle:Spot brief head>", $copy );
		$copy = str_replace( '</h3>', "\r\n<pstyle:Body Text>", $copy );
		$copy = str_replace( '<h4>', "\r\n<pstyle:Text subhead>", $copy );
		$copy = str_replace( '</h4>', "\r\n<pstyle:Body Text>", $copy );
		$copy = str_replace( '</h5>', "\r\n<pstyle:Heading>", $copy );
		$copy = str_replace( '</h5>', "\r\n<pstyle:Body Text>", $copy );
	
		//Trim the copy, explode it and trim each graf
		$copy = trim( $copy );
		$exploosh = explode("\r\n",$copy);
		$trimmed = array();
		foreach($exploosh as $graf) {
			if( empty( $graf ) )
				continue;
			$trimmed[] = trim( $graf );
		}
			
		//Then implode it and replace shits with shits
		$copy = implode( "\r\n", $trimmed );
	
		//Take care of multiple carriage returns
		$copy = preg_replace("/\n\n+/", "\n", $copy);
		
		//With \r\n line endings (needed on Windows), tell Indesign it's a Windows tagged text file
		$string = "<ASCII-WIN>\r\n";
		
		
		//If there are images attached to the post, grab the credit and cutline for each image
		//Image functions available at https://github.com/bangordailynews/BDN-image-functions
		if( function_exists( 'bdn_has_images' ) ) { 

			if( bdn_has_images( $post->ID ) ) {

				$images = bdn_get_images( $post->ID );
		
				if( $images ) {
					foreach( $images as $image ) {
				
						$meta = wp_get_attachment_metadata( $image->ID );
					
						//Integreates with Scott Bressler's media credit
						if( function_exists( 'get_media_credit_html' ) )
							$credit = get_media_credit_html( $image->ID );
					
						//We use a photo system called Merlin, and print out the ID of the photo if it exists
						if( ( $merlin_id = get_post_meta( $image->ID, '_merlin_id', TRUE ) ) ) {
							$identifier = 'Merlin ID: ' . $merlin_id;
						} elseif( ( $photo_file = wp_get_attachment_image_src( $image->ID, 'full' ) ) && !empty( $photo_file ) && is_array( $photo_file )  ) {
							$identifier = 'Filename: ' . basename( reset( $photo_file ) );
						}
					
						$string .= '<pstyle:Cut credit>' . $identifier . "\r\n";
						$string .= '<pstyle:Cut credit>' . strip_tags( $credit ) . "\r\n";
						$string .= '<pstyle:Cut>' . html_entity_decode( strip_tags( $image->post_excerpt ) ) . "\r\n\r\n";
	
					} // Foreach loop
	
				} // If Images
			}

		} // If function exists
		
		
		list( $byline1, $byline2 ) = explode( ',', $byline );
		
		if( !empty( $byline ) && $byline1 == 'Reuters' ) {
			$string .= '<pstyle:Byline 2>' . trim( $byline1 ) . "\r\n";
		} elseif( !empty( $byline ) ) {
			$string .= "<pstyle:Byline 1>By " . trim( $byline1 ) . "\r\n"; 
			if( $byline2 )
				$string .= "<pstyle:Byline 2>" . trim( $byline2 ) . "\r\n";
		}
		
		//Remove extra carriage returns
		$string .= "<pstyle:Body Text>" . $copy;
		$string = str_replace( "<pstyle:Body Text>\r\n", '<pstyle:Body Text>', $string );
		$string = str_replace( "<pstyle:Body Text>\r", '<pstyle:Body Text>', $string );
		$string = str_replace( '<pstyle:Body Text><pstyle:', '<pstyle:', $string );


		//Convert paragraph styles if this is a different format
		if( trim( strip_tags( $format ) ) == 'spagate' ) {
														//This is an example of a nested style
			$string = str_replace( '<pstyle:Body Text>', '<pstyle:Sports\:Sports agate>', $string );
			$string = str_replace( '<pstyle:Spot brief head>', "\r\n\r\n<pstyle:Sports\:Sports agate reverse head>", $string );
			$string = str_replace( '<pstyle:Text subhead>', '<pstyle:Sports\:Sports agate head>', $string );
			$string = str_replace( '<ct:Bold>', '<ct:75 Bold>', $string );
			$string = str_replace( '<ct:>', '<ct:>', $string );
		
		}
		
		//Again, extra carriage returns
		$string = str_replace( "\r\n\r\n<pstyle:Text subhead>", "\r\n<pstyle:Text subhead>", $string );
	
		return $string;
	
	}
	
	
	/*
	 * Return a JSON response for the WP Browser
	 *
	 */
	function wp_browser_search() {
	
		if( $_GET[ 'apiKey' ] != $this->api_key )
			wp_send_json_error( array( 'error' => 'Invalid API Key' ) );
		
		//Returns the list of categories (or some other taxonomy you want to filter by)
		if( !empty( $_GET[ 'filter_list' ] ) ) {
			
			$categories = get_categories();
			$send = array();
			foreach( $categories as $category )
				$send[] = $category->slug;
			wp_send_json_success( $send );
			
		}

		//Ooh, this is fun. 
		//By default, post status are filtered with an OR statement
		//This regex converts it to an IN statement, which is more efficient
		add_filter( 'posts_where', array( &$this, 'do_in_for_post_status' ) );

		//Build our args for the search
		$args = array(
			//Our above filter won't fire unless we suppress filters
			'suppress_filters' => false,
			'numberposts' => 20,
			'post_type' => array( 'post' ),
			//If we do post_status any, the query is even worse
			//If we want to widen this, the best way to do would be run get_post_stati and iterate
			'post_status' => array( 'publish', 'draft', 'pending' ),
			//Show modified posts up top
			'orderby' => 'modified',
		);
		
		if( !empty( $_GET[ 'filter' ] ) )
			$args[ 'category_name' ] = $_GET[ 'filter' ];

		//Yay, a query!
		if( !empty( $_GET[ 's' ] ) ) {
		
			//First, check to see if there's a slug matching the query
			$by_slug = $this->get_stories_by_slug( $_GET[ 's' ] );
		
			//If there's a slug, just search for the post IDs returned
			if( !empty( $by_slug ) ) {
				$args[ 'post__in' ] = $by_slug;
			//Else, search for the query
			} else {
				//@TODO: Do we need to validate?
				$args[ 's' ] = $_GET[ 's' ];
			}
		
		}

		$posts = get_posts( $args );
	
		$response = array();
	
		foreach( $posts as $post ) {
		
			//We need a better library for building the bylines
			$byline = '';
			if( ( $byline = get_post_meta( $post->ID, 'byline', true ) ) ) {
				$byline = $byline;
			} elseif( function_exists( 'get_coauthors' ) ) {
				$authors_object = get_coauthors( $post->ID );
				$authors = array();
				foreach( $authors_object as $author ) {
					$authors[] = $author->display_name;
				}
				$byline = implode( ', ', $authors );
			} else {
				//@TODO: I don't think this works
				$byline = get_the_author();
			}
	
			//@TODO: Status
			//@TODO: Has the story been placed already
			//@TODO: MD5 of contents?
			$response[] = array(
				'post_id' => $post->ID,
				'author' => $byline,
				'slug' => ( $slug = get_post_meta( $post->ID, 'slug', true ) ) ? $slug : substr( $post->post_title, 0, 20 ),
				'depth' => round( str_word_count( strip_tags( $post->post_content ) ) / 30 ),
				'status' => $post->post_status,
				'content' => trim( $this->do_tagged_text( $post ) ),
			);
		}
	
		wp_send_json_success( $response );

	}
	
	
	/*
	 * Search for posts by the slug
	 *
	 */
	function get_stories_by_slug( $slug = false ) {

		if( empty( $slug ) )
			return false;

		//Poor man's validation: If there's any nonalphanumeric characters, return false
		if( $slug != preg_replace( '/[^a-zA-Z0-9-_.]/', '', $slug ) )
			return false;

		global $wpdb;
	
		//get_col just returns an array of the selected column
		return $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'slug' AND meta_value = '%s' ORDER BY post_id DESC", $slug ) );	

	}

	/*
	 * By default, post status are filtered with an OR statement
	 * This regex converts it to an IN statement, which is more efficient
	 *
	 */
	function do_in_for_post_status( $where ) {

		global $wpdb;

		//Match all post_status ORs
		preg_match_all( "|" . $wpdb->posts . ".post_status = '(.*?)'|", $where, $statusmatches );
	
		//Replace the ORs with INs
		if( !empty( $statusmatches[ 1 ] ) ) {
			$where = str_replace( implode( ' OR ', $statusmatches[ 0 ] ), $wpdb->posts . ".post_status IN ('" . implode( "','", $statusmatches[ 1 ] ) . "')", $where );
		}
	
		//Also get rid of filtering out password protected posts
		$where = str_replace( "AND (" . $wpdb->posts . ".post_password = '')", '', $where );
	
		return $where;	
	
	}
	
	
	/*
	 *
	 *
	 */
	function wp_browser_notify() {
	
		if( $_GET[ 'apiKey' ] != $this->api_key )
			wp_send_json_error( array( 'error' => 'Invalid API Key' ) );
	
		if( empty( $_GET[ 'postId' ] ) )
			wp_send_json_error( array( 'error' => 'No post' ) );
		
		$post = get_post( $_GET[ 'postId' ] );
		
		if( empty( $post ) || !is_object( $post ) || is_wp_error( $post ) )
			wp_send_json_error( array( 'error' => 'Post not found' ) );
		
		if( !empty( $_GET[ 'date' ] ) )
			add_post_meta( $post->ID, '_placed_datetime', date( 'Y-m-d H:i:s', strtotime( $_GET[ 'date' ] ) ) );
		if( !empty( $_GET[ 'page' ] ) )
			add_post_meta( $post->ID, '_placed_page', preg_replace('/[^0-9a-zA-Z-_. ]/', '', $_GET[ 'page' ] ) );
		if( !empty( $_GET[ 'docName' ] ) )
			add_post_meta( $post->ID, '_placed_file', preg_replace('/[^0-9a-zA-Z-_. ]/', '', $_GET[ 'docName' ] ) );
		wp_set_object_terms( $post->ID, array( 'published', 'final-published' ), 'status' );
		
		wp_send_json_success();
	
	}

}

new BDN_InDesign;
