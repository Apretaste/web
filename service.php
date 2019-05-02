<?php

include __DIR__."/vendor/autoload.php";

class Service {

	public $base = NULL;

	/**
	 * Opens the browser screen
	 *
	 * @author salvipascual
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _main(Request $request, Response $response) {
		// get data from the request
		$query = isset($request->input->data->query) ? $request->input->data->query : "";

		// get the user settings
		Connection::query("INSERT IGNORE INTO _web_user_settings (id_person) VALUES ({$request->person->id})");
		$settigns = Connection::query("SELECT save_mode FROM _web_user_settings WHERE id_person = {$request->person->id}")[0];

		//
		// show welcome message when query is empty
		//

		if (empty($query)) {
			// get visits
			$sites = Connection::query("SELECT url, title FROM _web_cache ORDER BY visits DESC LIMIT 14");

			// create response
			//$response->setCache("day");
			$response->setLayout('browser.ejs');
			$response->setTemplate("home.ejs", [
				'query'    => '',
				'settings' => $settigns,
				'sites'    => $sites,
			]);
			return $response;
		}

		//
		// download the page if a valid domain name or URL is passed
		//

		if ($this->isValidDomain($query)) {
			// add the scheeme if not passed
			if (!php::startsWith($query, "http")) {
				$query = "http://$query";
			}

			// get the html code of the page
			//$html = $this->browse($query, $request->person->id, $settigns->save_mode);
			$info = $this->getHTTP($request, $query, 'GET', '', $agent = 'default', $config = [
				'default_user_agent'  => 'Mozilla/5.0 (Windows NT 6.2; rv:40.0) Gecko/20100101 Firefox/40.0',
				'mobile_user_agent'   => 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16',
				'max_attachment_size' => 400,
				'cache_life_time'     => 100000,
			]);

			$html = '';
			if ($info !== FALSE) {
				$html = $info['body'];
			}

			// if nothing was passed, let the user know
			if (empty($html)) {
				$response->setLayout('browser.ejs');
				return $response->setTemplate('error.ejs', [
					"query"    => $query,
					'settings' => $settigns,
				]);
			}

			// create response
			//$response->setCache("month");
			$response->setLayout('browser.ejs');
			$response->setTemplate("web.ejs", [
				'query'    => $query,
				'settings' => $settigns,
				'content'     => $html,
			]);
			return $response;
		}

		//
		// else search in the web and return results
		//

		// get the search results
		$results = $this->search($query);

		// if nothing was passed, let the user know
		if (empty($results)) {
			$response->setLayout('browser.ejs');
			return $response->setTemplate('error.ejs', [
				"query"    => $query,
				'settings' => $settigns,
			]);
		}

		// create the response
		//$response->setCache("year");
		$response->setLayout('browser.ejs');
		$response->setTemplate("google.ejs", [
			"query"    => $query,
			'settings' => $settigns,
			"results"  => $results,
		]);
	}

	/**
	 * Get the user's browsing history
	 *
	 * @author salvipascual
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _history(Request $request, Response $response) {
		// get the history for the person
		$pages = Connection::query("
			SELECT title, url, inserted 
			FROM _web_history 
			WHERE person_id = {$request->person->id}
			ORDER BY inserted 
			DESC LIMIT 20");

		// create the response
		$response->setTemplate("history.ejs", ["pages" => $pages]);
	}

	/**
	 * Change the user's save mode
	 *
	 * @author salvipascual
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _set(Request $request, Response $response) {
		// get save mode to change
		$saveMode = empty($request->input->data->save_mode) ? 0 : 1;

		// update the user's settings
		Connection::query("
			UPDATE _web_user_settings 
			SET save_mode = $saveMode 
			WHERE id_person = {$request->person->id}");
	}

	/**
	 * Download the latest version of the website
	 *
	 * @author salvipascual
	 *
	 * @param String $url
	 * @param Integer $userId
	 * @param Boolean $saveMode
	 *
	 * @return String
	 */
	private function browse($url, $personId, $saveMode) {
		// chech if the page is in cache
		$urlHash   = md5($url);
		$fileCache = Utils::getTempDir() . "/web/$urlHash.html";

		// load the page from cache
		if (file_exists($fileCache)) {
			// load from cache
			$html  = file_get_contents($fileCache);
			$title = $this->getTitle($html);

			// increase cache counter
			Connection::query("UPDATE _web_cache SET visits=visits+1 WHERE url_hash='$urlHash'");
		}
		// load the page online
		else {
			// get the page from online
			$html  = $this->getHtmlFromUrl($url);
			$title = $this->getTitle($html);

			// cache the page
			$title = Connection::escape($title);
			Connection::query("INSERT IGNORE INTO _web_cache (url_hash, url, title) VALUES ('$urlHash', '$url', '$title')");
			file_put_contents($fileCache, $html);
		}

		// stop for NO-JS errors or empty DOCTYPES
		if (strlen($html) < 500) {
			return "";
		}

		// save the page as visited by the user
		$title = Connection::escape($title, 250);
		Connection::query("INSERT INTO _web_history (person_id, url, title) VALUES ($personId, '$url', '$title')");

		// compress the page
		if ($saveMode) {
			$html = $this->minify($html, $url);
		}

		return $html;
	}

	/**
	 * Search for a term in Bing and return formatted results
	 *
	 * @author kumahacker
	 *
	 * @param String $q
	 *
	 * @return Array
	 */
	private function search($q) {
		// do not allow empty queries
		if (empty($q)) {
			return [];
		}

		// get the Bing key
		$di  = \Phalcon\DI\FactoryDefault::getDefault();
		$key = $di->get('config')['bing']['key1'];

		// perform the request and get the JSON response
		$context = stream_context_create([
			'http' => [
				'header' => "Ocp-Apim-Subscription-Key: $key\r\n",
				'method' => 'GET',
			],
		]);
		$result  = file_get_contents("https://api.cognitive.microsoft.com/bing/v7.0/search?mkt=es-US&q=" . urlencode($q), FALSE, $context);
		$json    = json_decode($result);

		// format the search results
		$results = [];
		if (isset($json->webPages)) {
			foreach ($json->webPages->value as $v) {
				$v         = get_object_vars($v);
				$results[] = [
					"title" => $v['name'],
					'url'   => $v['url'],
					'note'  => $v['snippet'],
				];
			}
		}

		return $results;
	}

	/**
	 * Get the title of an HTML page
	 *
	 * @author salvipascual
	 *
	 * @param String $html
	 * @param String $url
	 *
	 * @return String
	 */
	private function minify($html, $url) {
		$tidy = new tidy();
		$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
		$html = $tidy->repairString($html, [
			'output-xhtml' => TRUE,
		], 'utf8');

		// create DOM element
		$dom                   = new DomDocument('1.0', 'UTF-8');
		$libxml_previous_state = libxml_use_internal_errors(TRUE);
		@$dom->loadHTML($html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		// use only the BODY tag, we don't need the HEAD
		$body = $dom->getElementsByTagName('body');
		$body = $body->item(0);
		$html = $dom->savehtml($body);

		$html = $tidy->repairString($html, [
			'output-xhtml' => TRUE,
		], 'utf8');

		$libxml_previous_state = libxml_use_internal_errors(TRUE);
		@$dom->loadHTML($html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		// remove unwanted HTML tags
		$tags = [
			'meta',
			'script',
			'link',
			'nav',
			'style',
			'iframe',
			'video',
			'canvas',
			'form',
			'input',
			'select',
			'textarea',
			'button',
			'svg',
			'img',
		];
		foreach ($tags as $tag) {
			while (($r = $dom->getElementsByTagName($tag)) && $r->length) {
				$r->item(0)->parentNode->removeChild($r->item(0));
			}
		}

		// get the domain and directory from the URL
		$urlp      = parse_url($url);
		$urlDomain = $urlp['scheme'] . '://' . $urlp['host'];
		$urlDir    = dirname($url) . '/';

		// replace <a> by onclick
		foreach ($dom->getElementsByTagName('a') as $node) {
			// get the URL to the new page
			$src = trim($node->getAttribute("href"));

			// complete relative links
			if (php::startsWith($src, '/')) {
				$src = $urlDomain . $src;
			}
			elseif (!php::startsWith($src, 'http')) {
				$src = $urlDir . $src;
			}

			// convert the links to onclick
			$node->setAttribute('href', "#!");
			$node->setAttribute('onclick', "parent.send({command:'WEB', data:{query:'$src'}}); return false;");
		}

		// convert DOM back to HTML code
		$html = utf8_decode($dom->saveHTML($dom->documentElement));

		// remove all unwanted attributes
		$attrs = ['style', 'id', 'class', 'align', 'target'];
		foreach ($attrs as $attr) {
			preg_match_all('/ ' . $attr . '.*?=.*?".*?"/', $html, $matches);
			foreach ($matches[0] as $match) {
				$html = str_replace($match, "", $html);
			}
		}

		// remove white spaces and extra chars to minify the HTML
		$search = [
			'/\>[^\S ]+/s', // strip whitespaces after tags, except space
			'/[^\S ]+\</s', // strip whitespaces before tags, except space
			'/(\s)+/s', // shorten multiple whitespace sequences
			'/<!--(.|\s)*?-->/'// Remove HTML comments
		];
		return "<br/><br/>" . preg_replace($search, ['>', '<', '\\1', ''], $html);
	}

	/**
	 * Checks if a domain name is valid
	 *
	 * @param String $domain
	 *
	 * @return Boolean
	 */
	private function isValidDomain($domain) {
		// FILTER_VALIDATE_URL checks length but..why not? so we dont move forward with more expensive operations
		$domain_len = strlen($domain);
		if ($domain_len < 3 OR $domain_len > 253) {
			return FALSE;
		}

		// getting rid of HTTP/S just in case was passed.
		if (stripos($domain, 'http://') === 0) {
			$domain = substr($domain, 7);
		}
		elseif (stripos($domain, 'https://') === 0) {
			$domain = substr($domain, 8);
		}

		// we dont need the www either
		if (stripos($domain, 'www.') === 0) {
			$domain = substr($domain, 4);
		}

		// Checking for a '.' at least, not in the beginning nor end, since http://.abcd. is reported valid
		if (strpos($domain, '.') === FALSE OR $domain[strlen($domain) - 1] == '.' OR $domain[0] == '.') {
			return FALSE;
		}

		// now we use the FILTER_VALIDATE_URL, concatenating http so we can use it, and return BOOL
		return (filter_var('http://' . $domain, FILTER_VALIDATE_URL) === FALSE) ? FALSE : TRUE;
	}

	/**
	 * Get the title of an HTML page
	 *
	 * @author salvipascual
	 *
	 * @param String $html
	 *
	 * @return String
	 */
	private function getTitle($html) {
		// get the title using a regexp
		$res = preg_match("/<title>(.*)<\/title>/siU", $html, $matches);
		if (!$res) {
			return "";
		}

		// remove EOL's and excessive whitespace.
		return trim(preg_replace('/\s+/', ' ', $matches[1]));
	}

	/**
	 * Gets the HTML code for a website
	 *
	 * @author salvipascual
	 *
	 * @param String $html
	 *
	 * @return String
	 */
	private function getHtmlFromUrl($url) {
		// prepare URL for CURL
		$url = str_replace("//", "/", $url);
		$url = str_replace("http:/", "http://", $url);
		$url = str_replace("https:/", "https://", $url);

		// prepare headers for CURL
		$headers = [];
		foreach (
			[
				"Cache-Control" => "max-age=0",
				"Origin"        => "{$url}",
				"User-Agent"    => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36",
				"Content-Type"  => "application/x-www-form-urlencoded",
			] as $key => $val
		) {
			$headers[] = "$key: $val";
		}

		// start CURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$html = curl_exec($ch);
		$info = curl_getinfo($ch);

		// handle 301 redirects
		if ($info['http_code'] == 301 && isset($info['redirect_url']) && $info['redirect_url'] != $url) {
			return $this->getHtmlFromUrl($info['redirect_url']);
		}

		curl_close($ch);
		return $html;
	}

	private function getTempDir() {
		$di      = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwroot = $di->get('path')['root'];
		return $wwwroot . "/temp";
	}

	/**
	 * Return TRUE if url is a HTTP or HTTPS
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	private function isHttpURL($url) {
		$url = trim(strtolower($url));
		return strtolower(substr($url, 0, 7)) === 'http://' || strtolower(substr($url, 0, 8)) === 'https://';
	}

	/**
	 * Return TRUE if url is a FTP
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	private function isFtpURL($url) {
		$url = trim(strtolower($url));
		return strtolower(substr($url, 0, 6)) === 'ftp://';
	}


	/**
	 * Return full HREF
	 */
	private function getFullHref($href, $url) {
		$href = trim($href);
		if ($href == '' || $href == 'javascript(0);' || $href == 'javascript:void(0);') {
			return $url;
		}
		if (strtolower(substr($href, 0, 2) == '//')) {
			return 'http:' . $href;
		}
		if (strtolower(substr($href, 0, 1) == '?')) {
			if (!is_null($this->base)) {
				return $this->base . $href;
			}
			return $url . $href;
		}

		$base = '';

		if ($this->isHttpURL($href) || $this->isFtpURL($href)) {
			return $href;
		}

		if (!$this->isHttpURL($url) && !$this->isFtpURL($url)) {
			$url = 'http://' . $url;
		}

		$url = trim($url);

		if (substr($url, strlen($url) - 1, 1) == "/") {
			$url = substr($url, 0, strlen($url) - 1);
		}

		$parts = parse_url($url);

		if (!is_null($this->base)) {
			$base = $this->base;
		}
		else {
			if ($parts === FALSE) {
				return $href;
			}

			if (!isset($parts['port'])) {
				$parts['port'] = 80;
			}

			$base = $parts['scheme'] . "://" . $parts['host'] . ":" . $parts['port'] . "/";

			if (isset($parts['path'])) {
				$p = $parts['path'];

				$exts = explode(' ', 'html htm php5 exe php jsp asp aspx jsf py');

				foreach ($exts as $ext) {
					if (stripos($p, '.' . $ext) !== FALSE) {
						$base .= dirname($p) . "/";
						break;
					}
				}
			}
		}

		return $base . $href;
	}


	/**
	 * Repair HREF/SRC attributes
	 *
	 * @param DOMElement $node
	 * @param string $url
	 *
	 * @return string
	 */
	private function convertLink(DOMElement &$node, string $url) {
		$href = $node->getAttribute('href');
		if (trim($href) == '') {
			return '';
		}

		$new_href  = '#!';
		$full_href = $this->getFullHref($href, $url);
		$node->setAttribute('onclick', "parent.send({command: 'web', data: {query: '$full_href'}});");
		return $new_href;
	}

	function _http(Request $request, Response $response) {

		$settigns = Connection::query("SELECT save_mode FROM _web_user_settings WHERE id_person = {$request->person->id}")[0];

		$query = "https://www.wikipedia.org";
		$info  = $this->getHTTP($request, $query, 'GET', '', $agent = 'default', $config = [
			'default_user_agent'  => 'Mozilla/5.0 (Windows NT 6.2; rv:40.0) Gecko/20100101 Firefox/40.0',
			'mobile_user_agent'   => 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16',
			'max_attachment_size' => 400,
			'cache_life_time'     => 100000,
		]);
		if ($info !== FALSE) {
			// create response
			$response->setCache("month");
			$response->setLayout('browser.ejs');
			$response->setTemplate("web.ejs", [
				'query'    => $query,
				'settings' => $settigns,
				'html'     => $info['body'],
			]);
		}
		else {
			$response->setTemplate('error.ejs', [
				"query"    => $query,
				'settings' => $settigns,
			]);
		}

		return $response;
	}


 private function domRemoveNode(&$node) {
	$pnode = $node->parentNode;
	$this->domRemoveChildren($node);
	$pnode->removeChild($node);
}

 private function domRemoveChildren(&$node) {
	while ($node->firstChild) {
		while ($node->firstChild->firstChild) {
			$this->domRemoveChildren($node->firstChild);
		}

		$node->removeChild($node->firstChild);
	}
}

	private function getHTTP($request, $url, $method = 'GET', $post = '', $agent = 'default', $config = []) {
		require_once dirname(__FILE__) . "/lib/CSSParser/CSSParser.php";
		require_once dirname(__FILE__) . "/lib/Encoding.php";
		require_once dirname(__FILE__) . "/lib/Fetcher.php";
		require_once dirname(__FILE__) . "/lib/Converter.php";

		// clear $url
		$url = str_replace("///", "/", $url);
		$url = str_replace("//", "/", $url);
		$url = str_replace("http:/", "http://", $url);
		$url = str_replace("https:/", "https://", $url);

		if (substr($url, 0, 2) == '//') {
			$url = 'http:' . $url;
		}
		else if (substr($url, 0, 1) == '/') {
			$url = 'http:/' . $url;
		}

		try {
			// Create http client
			$http_client = new GuzzleHttp\Client([
				'cookies'  => TRUE,
				'defaults' => [
					'verify' => FALSE,
				],
			]);

		} catch (Exception $e) {
			return FALSE;
		}

		// Build POST
		if ($post != '') {
			$arr  = explode("&", $post);
			$post = [];
			foreach ($arr as $v) {
				$arr2 = explode('=', $v);
				if (!isset($arr2[1])) {
					$arr2[1] = '';
				}
				$post[$arr2[0]] = $arr2[1];
			}
		}
		else {
			$post = [];
		}

		$cookies                    = FALSE;
		$options['allow_redirects'] = TRUE;

		// Set user agent
		$options['headers'] = [
			'user-agent' => $config[$agent . '_user_agent'],
		];

		// Sending POST/GET data
		if ($method == 'POST') {
			$options['body'] = $post;
		}

		// Send request
		try {
			$http_response = $http_client->request($method,$url ,$options);
		} catch (Exception $e) {
			return FALSE;
		}

		$http_headers = [];
		// Gedt HTTP headers
		try {
			$http_headers = $http_response->getHeaders();
		} catch (Exception $e) {
		}

		$resources = [];

		// Getting HTML page
		$css  = '';
		$body = $http_response->getBody();

		// Force to UTF8 encoding
		$body = ForceUTF8\Encoding::toUTF8($body);

		$tidy = new tidy();
		$body = $tidy->repairString($body, [
			'output-xhtml' => TRUE,
		], 'utf8');

		$doc = new DOMDocument();

		@$doc->loadHTML($body);

		// Getting BASE of URLs (base tag)
		$base = $doc->getElementsByTagName('base');
		if ($base->length > 0) {
			$this->base = $base->item(0)->getAttribute('href');
		}

		// Get the page's title

		$title = $doc->getElementsByTagName('title');

		if ($title->length > 0) {
			$title = $title->item(0)->nodeValue;
		}
		else {
			$title = $url;
		}

		// Convert links to mailto
		$links = $doc->getElementsByTagName('a');

		if ($links->length > 0) {
			foreach ($links as $link) {
				$href = $link->getAttribute('href');

				if ($href == FALSE || empty($href)) {
					$href = $link->getAttribute('data-src');
				}

				if (substr($href, 0, 1) == '#') {
					$link->setAttribute('href', '');
					continue;
				}
				if (strtolower(substr($href, 0, 7)) == 'mailto:') {
					continue;
				}

				$this->convertLink($link, $url);
			}
		}

		// Array for store replacements of DOM's nodes
		$replace    = [];
		$in_the_end = [];

		// Get scripts
		$scripts = $doc->getElementsByTagName('script');

		if ($scripts->length > 0) {
			foreach ($scripts as $script) {
				$src = $this->getFullHref($script->getAttribute('src'), $url);

				if ($src != $url) {
					$resources[$src] = $src;
				}
			}
		}

		// Get CSS stylesheets
		$styles = $doc->getElementsByTagName('style');

		if ($styles->length > 0) {
			foreach ($styles as $style) {
				$css .= $style->nodeValue;
			}
		}

		// Remove some tags
		$tags = [
			'script',
			'style',
			'noscript',
		];

		foreach ($tags as $tag) {
			$elements = $doc->getElementsByTagName($tag);

			if ($elements->length > 0) {
				foreach ($elements as $element) {

					$replace[] = [
						'oldnode' => $element,
						'newnode' => NULL,
						'parent'  => $element->parentNode,
					];
				}
			}
		}

		// Getting LINK tags and retrieve CSS
		$styles = $doc->getElementsByTagName('link');

		if ($styles->length > 0) {
			foreach ($styles as $style) {

				// Is CSS?
				if ($style->getAttribute('rel') == 'stylesheet') {

					$href = $this->getFullHref($style->getAttribute('href'), $url);

					$r = Utils::file_get_contents_curl($href);

					if ($r !== FALSE) {

						try {
							$oParser = new CSSParser();
							$oDoc = $oParser->parseString($r);
							$aDeclarations = $oDoc->getAllDeclarationBlocks();
							$r = CSSDocument::mergeDeclarations($aDeclarations);
						} catch(Exception $e)
						{
							$r = '';
						}

						$css              .= $r;
						$resources[$href] = $href;
					}
				}
			}
		}

		$images = $doc->getElementsByTagName('img');

		if ($images->length > 0) {
			foreach ($images as $image) {
				$src = $image->getAttribute('src');
				if (substr($src,0,2) == '//') $src = "http:".$src;
				try{
					$inliner = new Milanspv\InlineImages\Converter($src);
					$image->setAttribute('src',utf8_encode($inliner->convert()));
				} catch(Exception $e){
					continue;
				}

			}
		}

		// Replace/remove childs
		foreach ($replace as $rep) {
			try {
				if (is_null($rep['newnode'])) {
					$rep['parent']->removeChild($rep['oldnode']);
				}
				else {
					$rep['parent']->replaceChild($rep['newnode'], $rep['oldnode']);
				}
			} catch (Exception $e) {
				continue;
			}
		}

		$replace = [];
		$body = $doc->saveHTML();

		// Set style to each element in DOM, based on CSS stylesheets

		$css = ForceUTF8\Encoding::toUTF8($css);

		$standard_css = file_get_contents(__DIR__."/standards/firefox.css");
		$css = str_replace(["/*>*/","/**/", '<![CDATA[',']]'], "", $css);
		$emo = new Pelago\Emogrifier($body, /*"body,h1,h2,h3,h4,p,div,a,label,span,nav,ul,li,ol{color:black;}" . $standard_css . "\n".*/ $css);
		$emo->disableInvisibleNodeRemoval();

		try {
			$body = $emo->emogrify();
		} catch (Exception $e) {

		}

		@$doc->loadHTML($body);

		$nodeBody = $doc->getElementsByTagName('body');

		$styleBody = @$nodeBody[0]->getAttribute('style');
		//$styleBody = $this->fixStyle($styleBody);
		$styleBody = str_replace('"', "'", $styleBody);

		$tags_to_fix = explode(' ', 'a p label div pre h1 h2 h3 h4 h5 button i b u li ol ul fieldset small legend form input span button nav table tr th td thead');

		foreach ($tags_to_fix as $tagname) {
			if (array_search($tagname, [
					'input',
				]) !== FALSE) {
				continue;
			}

			$tags = $doc->getElementsByTagName($tagname);
			if ($tags->length > 0) {
				foreach ($tags as $tag) {
					if (trim($tag->nodeValue) == '' && $tag->childNodes->length == 0) {
						$replace[] = [
							'parent'  => $tag->parentNode,
							'oldnode' => $tag,
							'newnode' => NULL,
						];
					}
				}
			}
		}


		// Fixing PRE

		$pres = $doc->getElementsByTagName('pre');

		if ($pres->length > 0) {
			foreach ($pres as $pre) {
				$lines = explode('\n', $pre->nodeValue);

				$newpre = $doc->createElement('div');
				foreach ($lines as $line) {
					$line = str_replace(' ', '&nbsp;', $line);
					$newp = $doc->createElement('p', $line);
					$newp->setAttribute('style', 'line-height:13px;');
					$newpre->appendChild($newp);
				}

				$newpre->setAttribute('style', $pre->getAttribute('style') . ';font-size: 13px;font-family:Courier;');
				$replace[] = [
					'parent'  => $pre->parentNode,
					'oldnode' => $pre,
					'newnode' => $newpre,
				];
			}
		}

		// Fixing styles

		foreach ($tags_to_fix as $tag) {

			$links = $doc->getElementsByTagName($tag);

			if ($links->length > 0) {
				foreach ($links as $link) {
					$sty = $link->getAttribute('style');
					//$sty = $this->fixStyle($sty);
					$link->setAttribute('style', $sty);
					$link->setAttribute('class', '');
				}
			}
		}


		// Replace/remove childs [again]

		foreach ($replace as $rep) {
			try {
				if (is_null($rep['newnode'])) {
					$rep['parent']->removeChild($rep['oldnode']);
				}
				else {
					$rep['parent']->replaceChild($rep['newnode'], $rep['oldnode']);
				}
			} catch (Exception $e) {
				continue;
			}
		}

		// remove unwanted HTML tags
		$tags = [
			'meta',
			'script',
			'link',
			'style',
			'iframe',
			'video',
			'canvas',
			'form',
			'input',
			'select',
			'textarea',
			'button',
			'svg',
		];

		foreach ($tags as $tag) {
			while (($r = $doc->getElementsByTagName($tag)) && $r->length) {
				$r->item(0)->parentNode->removeChild($r->item(0));
			}
		}

		$body = $doc->saveHTML();

		// Get only the body
		$body = $tidy->repairString($body, [
			'output-xhtml'   => TRUE,
			'show-body-only' => TRUE,
		], 'utf8');

		$body = str_ireplace('class=""', '', $body);
		$body = str_ireplace('style=""', '', $body);

		// Compress the returning code
		$body = preg_replace('/\s+/S', " ", $body);

		// Cut large pages
		$limit       = 1024 * 400; // 400KB
		$body_length = strlen($body);
		if ($body_length > $limit) {
			$body = substr($body, 0, $limit);
		}

		foreach ($in_the_end as $id => $code) {
			$body = str_replace('{' . $id . '}', $code, $body);
		}

	//	$body = strip_tags($body, 'div a h1 h2 h3 h4 table tr td th thead tfoot p pre ul li ol img');
		//$css = str_replace(['<![CDATA[',']]'],'',$css);
		//$body = "<style>$css</style>$body";

		// Return results
		return [
			'title'       => $title,
			'body'        => $body,
			'style'       => $styleBody,
			'body_length' => number_format($body_length / 1024, 2),
			'url'         => $url,
			'resources'   => $resources,
		];
	}
}