This is a WordPress plugin to format posts as Indesign Tagged Text. It is intended to be used in conjunction with the WP Browser.

This is not a plug-and-play plugin. You will have to modify the plugin to set the API key and insert your own paragraph styles.

This repository also includes the Javascript version of the WP Browser. It runs on both Macs and PCs and also requires some configuration.

#Note about the WP Browser:#
When InDesign makes a call to the server, it does so by creating a socket connection and then requesting the path, or something.

In short, your server will see a request come in for localhost/wp-admin/admin-ajax.php?etc

So, especially on multisite and possible on regular WordPress, you'll need to set the host for it to work.

I did this by adding the following line to wp-config.php. There's probably a better way to do it:

	if( ( $_SERVER[ 'HTTP_HOST' ] == 'localhost' || empty( $_SERVER[ 'HTTP_HOST' ] ) ) && !empty( $_GET[ 'action' ] ) && ( $_GET[ 'action' ] == 'wp-browser-search' || $_GET[ 'action' ] == 'wp-browser-notify' ) )
		$_SERVER[ 'HTTP_HOST' ] = 'mysite.com';

#Oh, and...#
I just ripped a lot of this out of the BDN site. It will definitely require customization. It might break. Email me at wdavis@bangordailynews.com if I did something stupid.
