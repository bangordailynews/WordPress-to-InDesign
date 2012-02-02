<?php
/*
By William P. Davis (http://wpdavis.com) for Bangor Daily News (http://bangordailynews.com)
Read all about it at http://dev.bangordailynews.com
LICENSE: Released under GPL (http://www.gnu.org/licenses/gpl.html). You may use this script for free for any use. However, if you make changes and distribute it you must release the code under the GPL license.
Report changes or bugs to will@wpdavis.com or at http://dev.bangordailynews.com
*/

//We don't want the script timing out on us
set_time_limit( 0 );

//Set the internal coding so we don't have character issues
header( 'Content-Type:text/html; charset=UTF-8' );
mb_internal_encoding( 'UTF-8' );

//Default timezone is EST. This doesn't really matter so much what it is, but it must be set
date_default_timezone_set( 'America/New_York' );

//This script requires the IXR Library, which can be downloaded at http://scripts.incutio.com/xmlrpc/
require( 'IXR_Library.php.inc' );

// Create the client object. The URL should point to your server's XMLRPC script
$client = new IXR_Client( 'http://mysite.com/xmlrpc.php' );


//Set the username and password for the XMLRPC login
$username = 'username'; 
$password = 'password'; 
//If you're using the BDN's XMLRPC extender, the login goes like this:
//				Blog ID	username	password	post type	category	Number of posts		Extra parameters
$params = array( 1, 	$username, 	$password, 	'post', 	false, 		25, 				array( 'orderby' => 'modified' ) );


// Run the query. If there is an error message, die and print the message
if ( !$client->query( 'bdn.getPosts', $params ) ) {
	die( 'Something went wrong - '.$client->getErrorCode().' : '.$client->getErrorMessage() );
} else {
	
	// No fatal error, so get the posts
	$posts = $client->getResponse();

	foreach( $posts as $post ) {

		//All our stories are saved to the server as id.txt. To see if we need to update the story, we need to get the ID and the post modified time
		$id = $post[ 'postid' ];
		$modified = $post[ 'dateModified' ]->year . '-' . $post[ 'dateModified' ]->month . '-' . $post[ 'dateModified' ]->day . ' ' . $post[ 'dateModified' ]->hour . ':' . $post[ 'dateModified' ]->minute . ':' . $post[ 'dateModified' ]->second;
	
		//By default, the files are saved in a subdirectory files
		$filename = 'files/' . $id . '.txt';
		//Maybe consider naming files by title?
		//$filename = 'files' . $post[ 'title' ] . '.txt';
		
		//Check to see if the file exists and whether or not it needs to be updated
		if ( file_exists( $filename ) && $modified < date( 'Y-m-d H:i:s', filemtime( $filename ) ) ) {
			echo 'Story ' . $id . ' has not been updated (file: ' . date( 'Y-m-d H:i:s', filemtime( $filename ) ) . ' story:' . $modified . '<br>)';
		} else {

			//Either the file doesn't exist, or the file modification time is less than the post modification time
			echo 'Modifying story ' . $id . '<br>';			
			
			//All the custom fields are in an array, so let's loop through them and get the ones we need. Make sure to set the variable false first
			$print_hed = false;
			$print_subhed = false;
			$caption = false;
			$byline = false;
			
			$custom_fields = array();
			
			foreach( $post[ 'custom_fields' ] as $custom_field ) {
				
				//Alternative way of accessing custom fields
				$custom_fields[ $custom_field[ 'key' ] ][] = $custom_field[ 'value' ];
				
				//We save the font size and text of a print headline and subheadline as a custom field
				//This can also be accessed like so:
				//$print_hed = reset( $custom_fields[ '_print_hed' ] );
				if( $custom_field[ 'key' ] == '_print_hed' )
					$print_hed = $custom_field[ 'value' ];
					
				if( $custom_field[ 'key' ] == '_print_subhed' )
					$print_subhed = $custom_field[ 'value' ];
				
				//If the author isn't a staff writer, we have a custom field for byline
				if( $custom_field[ 'key' ] == '_byline' )
					$byline = $custom_field[ 'value' ];
		
			}
			
			//We check to see if there is a custom byline that has been set. If not, we get the authors that are attached to the post
			if( empty( $byline ) ) {
				
				//Per the XMLRPC extender, the authors are sent as an array. There can be multiple authors
				$authors = $post[ 'wp_author_display_name' ];
				foreach( $authors as $author ) {
					//For each author, tack the display name onto the byline
					$byline .= $author[ 'display_name' ];
					if( $author != end( $authors ) ) {
						// If it's not the last author in the array, add 'and' to the byline
						$byline .= ' and ';
					} else {
						// If it is the last author, we can add a comma and custom field from the user metadata
						$byline .= ', ' . $author[ 'myfield' ];
					}
				}
			}
			
			// Now we start modifying the content
			// You will want to export a story from InDesign as tagged text and see what irregularities arise.
			
			
			//To start, decode all HTML entities
			$copy = htmlspecialchars_decode( html_entity_decode( $post[ 'description' ] ) );
			
			//Get rid of youtube embeds. You might want to modify this to be a little more greedy and delete all
			//shortcodes, but at the BDN we use square brackets in stories so we didn't want to risk deleting those
			$copy = preg_replace( '/\[youtube=(.*?)>\]/', '', $copy );
			
			//Strip tags we don't need. We use h4s for subheadlines, for example, but you can use different tags
			//to translate to different styles in print
			$copy = strip_tags( $copy, '<p><br><b><strong><i><em><h4>' );
			
			//Get rid of all classes and IDs
			$copy = preg_replace( '/<p(.*?)>/', '<p>', $copy );				
			
			//Convert our HTML tags to InDesign tags
			//We want to get rid of closing p tags, convert double line breaks and p tags to returns,
			//turn bold into bold text and italic into italic text, and turn closing bold and italic tags
			//back into regular text
			
			//BBDN will probably have to be turned into something else depending on your font. Create a test
			//text box in InDesign and export it as InDesign tagged text to see what styles you'll really need
			$original = array( '/<\/p>/', '/<br><br>/', '/<p>/', '/<b>/', '/<strong>/', '/<\/b>/', '/<\/strong>/', '/<i>/', '/<em>/', '/<\/i>/', '/<\/em>/' );
			$replacements = array( '',"\r\n", "\r\n", '<ct:BBDN>', '<ct:BBDN>', '<ct:>', '<ct:>', '<ct:IBDN>', '<ct:IBDN>', '<ct:>', '<ct:>' );
			$copy = preg_replace( $original, $replacements, $copy );
			
			
			//All special characters must come in as unicode. This part shouldn't need modification
			//It's a little excessive just in case
			//Convert mdashes to unicode
			$mdashoriginal = array( '/&mdash;/', '/—/', '/--/' );
			$mdashreplace = '<0x2014>';
			$copy = preg_replace( $mdashoriginal, $mdashreplace, $copy );
			
			//Convert smart quotes to unicode
			$smartquotes = array( chr(145), chr(146), chr(147), chr(148), chr(151) ); 
			$smartquotesreplace = array( '<0x2018>', '<0x2019>', '<0x201C>', '<0x201D>', '<0x2014>' ); 
			$copy = str_replace( $smartquotes, $smartquotesreplace, $copy );
			
			//And straight quotes
			$copy = str_replace( array( '&#39;', '&#34;' ), array( '\'', '"' ), $copy );
			$copy = str_replace( array( '&#039;', '&#034;' ), array( '\'','"' ), $copy );
			
			//Curly quotes to unicode
			$quotesoriginal = array('/‘/','/&lsquo;/','/’/','/&rsquo;/','/“/','/&ldquo;/','/”/','/&rdquo;/', '/…/', '/&hellip;/' );
			$quotesreplace = array('<0x2018>','<0x2018>','<0x2019>','<0x2019>','<0x201C>','<0x201C>','<0x201D>','<0x201D>', '<0x2026>', '<0x2026>' );
			$copy = preg_replace($quotesoriginal, $quotesreplace, $copy);
			
			//Convert spaces to spaces
			$copy = str_replace('&nbsp;', ' ', $copy);
			
			//Do some voodoo to standardize line breaks. This is straight from WordPress
			$copy = preg_replace('/<br>/', '<br />', $copy);
			$copy = preg_replace('|<br />\s*<br />|', "\n", $copy);
			$copy = preg_replace('/<br \/>/', "\n", $copy);
			$copy = str_replace(array("\r\n", "\r"), "\n", $copy);
			$copy = preg_replace("/\n\n+/", "\n", $copy);
			$copy = str_replace('\n\n','\n',$copy);
			$copy = preg_replace('/\n /', "\n", $copy);
			$copy = preg_replace('/\n/', "\r\n", $copy);
			$copy = preg_replace("/ +/", " ", $copy);

			//Pipes are tabs
			$copy = str_replace('|', '	', $copy);

			//One of the things we do is strip out Maine from datelines for print
			$copy = preg_replace('/, Maine <0x2014>/',' <0x2014>',$copy);

		
			//Go through each paragraph and make sure there's no whitespace on either end
			$copy = trim( $copy );
			$exploosh = explode( "\r\n",$copy );
			$trimmed = array();
			foreach($exploosh as $graf)
				$trimmed[] = trim( $graf );
			$copy = implode( "\r\n", $trimmed );
			
			//Make sure there are no double line breaks or returns
			$copy = str_replace( "\r\n\r\n", "\r\n", $copy );
			$copy = preg_replace( "/\n\n+/", "\n", $copy );
			
			//As mentioned before, print headlines can be written in WordPress. Here's how we handle them:
			if ( $print_hed ) {
				
				//We save the font size and the headline as a custom field separated by a pipe
				list( $fontSize,$rawHed ) = explode( '|', $print_hed );
				
				//Set the leading
				$leading = $fontSize - 4;
				
				//Convert line returns to line returns
				$rawHed = str_replace( '<br>', "\r\n", $rawHed);
				$rawHed = preg_replace( "/\r\n+/", "\r\n", $rawHed);
				
				//We have a paragraph style called headline. Set the font size, leading and font
				$print_hed = '<pstyle:Headline><ct:A BDN><cs:' . $fontSize . '><cl:' . $leading . '><cf:Minion Semibold BDN95>';
				//Then put in the headline
				$print_hed .= $rawHed;
				//At the end, a column break
				$print_hed .= "<cnxc:Column>\r\n";
				//Finally, make sure we're converting smart quotes
				$string .= preg_replace( $quotesoriginal, $quotesreplace, $print_hed );
				
			}
	

			//All our systems run on Windows, hence the windows line endings. If you wanted to use a unix system instead,
			//you'd want to use unix line endings (\r) and ASCII-MAC
			$string = "<ASCII-WIN>\r\n";
		
			//Get the two parts of the byline by exploding on the comma
			list( $byline1, $byline2 ) = explode( ',', $byline );
			
			//We treat bylines that are just for The Associated Press differently
			if( $byline && $byline1 == 'The Associated Press' ) {
				$string .= '<pstyle:Byline 2>' . trim( $byline1 ) . "\r\n";
			} elseif( $byline ) {

				//If it's not an AP byline, the first line is Byline 1 By So and So
				$string .= '<pstyle:Byline 1>By ' . trim( $byline1 ) . "\r\n"; 
				
				//And if there's a second part, it would be BDN Staff
				if( $byline2 )
					$string .= '<pstyle:Byline 2>' . trim( $byline2 ) . "\r\n";
			}
		
			//At this point, we're almost done. Our body text paragraph style is, brilliantly, called Body Text
			$string .= '<pstyle:Body Text>';
			
			//Then just flow in the copy
			$string .= $copy;
			
			//Finally, write everything to the file
			$fh = fopen( $filename, 'w' ) or die( 'Can\'t open file' );
			fwrite( $fh, $string );
			fclose( $fh );
			echo 'Generated post ID ' . $id . '<br>';
		}
	}
}