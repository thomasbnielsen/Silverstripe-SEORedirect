SEORedirect
===========

SilverStripe 2.4 SEORedirect -redirects URL to the parent page or a defined URL.

### Installation instructions ###

- Put this module under the root folder of site, named SEORedirect.
- Since this module parses requests directly from index.php (eg: sitename.com/index.php?id=123), we have to modify main controller.
Open mysite/code/Page.php, insert bellow code into the Page_Controller class:

```

/**
* handle all requests
*/

public function handleRequest(SS_HTTPRequest $request) {
	if( $performRedirect = SEORedirect::handleRequest($request) ){
		return $performRedirect;
	}
	
	return parent::handleRequest($request);
}

```

- run sitename.com/dev/build?flush=all
