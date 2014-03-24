#targetengine "session";

var WPBrowserApiKey = '';
var WPBrowserSearchServer = '';
var WPBrowserSearchEndpoint = '/wp-admin/admin-ajax.php?action=wp-browser-search&apiKey=' + WPBrowserApiKey + '&s=';
var WPBrowserNotifyServer = WPBrowserSearchServer;
var WPBrowserNotifyEndpoint = '/wp-admin/admin-ajax.php?action=wp-browser-notify&apiKey=' + WPBrowserApiKey;
var placeFromServer = true;

//If we're on a Mac, use a different path than on Windows
var appfullName = new String( app.fullName );
if( appfullName.substr( 0, 14 ) == '/Applications/' ) {
	var WPBrowserFilePath = '/volumes/my/path/to/files/';
} else {
	var WPBrowserFilePath = 'Z:\\my\\path\\to\\files\\';
}

if( WPBrowserSearchServer == ''
	|| WPBrowserSearchEndpoint == ''
	|| WPBrowserApiKey == '' ) {

	alert( 'Please provide settings' );
	var availableCategories = [];

} else {

	//Get our available categories to filter by
	var availableCategoriesJSON = eval( '(' + getURL( WPBrowserSearchServer, WPBrowserSearchEndpoint + '&filter_list=true' ) + ')');
	var availableCategories = availableCategoriesJSON.data;
	availableCategories.unshift( '' );
	
}

//Create our dialog window
var modal = new Window( 'palette', 'WordPress Browser' );

//Search row
modal.search = modal.add('group');
modal.search.orientation = 'row';
var searchField = modal.search.add( 'edittext', undefined, '' );
searchField.characters = 25;

//Search filters
var searchCategory = modal.search.add( 'dropdownlist', undefined, undefined, {items:availableCategories} );
//Search button
var searchButton = modal.search.add( 'button', undefined, 'Search');
searchButton.onClick = function() { bdnSearch( searchField.text ); }

//This is where the results will display
modal.results = modal.add('group');
modal.results.orientation = 'row';
var results = modal.results.add( 'listbox', undefined, '', {
	numberOfColumns: 5,
	showHeaders: true,
	columnTitles: [ 'Post ID', 'slug', 'status', 'author', 'in' ],
	columnWidths: [65,135,175,100,30]
});

results.minimumSize.height = 200;

//We prefill with blank results, else the modal will display really tiny
with( results.add( 'item', '') ) {
	subItems[0].text = '';
	subItems[1].text = '';
	subItems[2].text = '';
	subItems[3].text = '';
}


modal.buttons = modal.add('group');
modal.buttons.orientation = 'row';
var importButton = modal.buttons.add( 'button', undefined, 'Import');
importButton.onClick = function() {

	if( app.activeDocument.name.indexOf( 'Untitled' ) != -1 ) {
		alert( 'Please save your document before placing a story' );
		return;
	}

	if( !app.selection[0] ) {
		alert( 'Please select a text frame' );
		return;
	}
	
	//This gets the ID of the row
	//If you don't convert it to a string when you try to call it it will give you the offset instead
	var selection = new String( results.selection ) + '';
	
	if( selection == '' ) {
		alert( 'That story is not ready yet' );
		return;
	}
	
	if( placeFromServer ) {
		//Get the file to place
		var file = new File( WPBrowserFilePath + selection + '.txt' );
		if( !file.exists ) { 
			alert( 'File ' + selection + '.txt does not exist. Ensure the drive is mapped correctly' );
			return;
		}
	} else {
		for( var index in searchResults.data  ) {
		
			if( searchResults.data[ index ].post_id == selection ) { 

				var file = new File("~/wpbrowser.txt" );
				file.open( "w" );
				file.write( searchResults.data[ index ].content );
	
				break;
			}
		}
	}
	
	var frame = app.selection[0];
	frame.place( file );
	
	bdnNotify( selection );
}

modal.show();


function bdnSearch( searchString ) {

	results.removeAll();
	
	with( results.add( 'item', '' ) ) {
		subItems[0].text = 'Searching...';
		subItems[1].text = '';
		subItems[2].text = '';
		subItems[3].text = '';
	}

	var selectedFilter = new String( searchCategory.selection );
	var resultsJSON = getURL( WPBrowserSearchServer, WPBrowserSearchEndpoint + encodeURIComponent( searchString ) + '&filter=' + selectedFilter );

	if( resultsJSON === undefined ) {
		alert( 'Search timed out' );
		return;
	}
	
	var searchResults = eval('(' + resultsJSON + ')');	
	
	results.removeAll();

	if( searchResults.data.length == 0 ) {
		alert( 'No results found.' );
	} else {
		for( var index in searchResults.data ) {
			with( results.add( 'item', searchResults.data[index].post_id ) ) {
				subItems[0].text = searchResults.data[index].slug;
				subItems[1].text = searchResults.data[index].status;
				subItems[2].text = searchResults.data[index].author;
				subItems[3].text = searchResults.data[index].depth + '"';
			}
		}
	}
}

function bdnNotify( postId ) {
	
	//Bounds of the box
	//If the frame is on the page, return the page it's placed on. Else, return the active page.
	//The date and time the story was placed
	//Filename of the current document

	var notifyObject = {
		postId: postId,
		date: new Date(),
		page: ( typeof app.selection[0].parentPage !== 'object' ) ? app.activeWindow.activePage.name : app.selection[0].parentPage.name,
		docName: app.activeDocument.name,
	}
	
	var thisNotifyURL = WPBrowserNotifyEndpoint + '&' + serialize( notifyObject );
	
	getURL( WPBrowserNotifyServer, thisNotifyURL );

}

function getURL( server, page ) {

	var reply = "";
	conn = new Socket;
	conn.timeout=30;
	conn.encoding = 'UTF-8';
	if( conn.open( server + ':80' ) ) {
		conn.write( 'GET ' + page + ' HTTP/1.0' + "\n\n" );
		reply = conn.read(999999);
		conn.close();
	} else {
		alert( 'Problem connecting to server' );
    }

	txt = reply.split( "\n\n" );
	return txt[1];
	
}

serialize = function(obj) {
  var str = [];
  for(var p in obj)
    if (obj.hasOwnProperty(p)) {
      str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
    }
  return str.join("&");
}
