<?php

class Service
{
	/**
	 * Opens the browser screen
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @param Response $response
	 */
	public function _main (Request $request, Response $response)
	{
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
			$response->setCache("day");
			$response->setLayout('browser.ejs');
			$response->setTemplate("home.ejs", ['query'=>'', 'settings'=>$settigns, 'sites'=>$sites]);
			return $response;
		}

		//
		// download the page if a valid domain name or URL is passed
		//

		if ($this->isValidDomain($query)) {
			// add the scheeme if not passed
			if( ! php::startsWith($query, "http")) $query = "http://$query";

			// get the html code of the page
			$html = $this->browse($query, $request->person->id, $settigns->save_mode);

			// if nothing was passed, let the user know
			if(empty($html)) {
				$response->setLayout('browser.ejs');
				return $response->setTemplate('error.ejs', ["query"=>$query, 'settings'=>$settigns]);
			}

			// create response
			$response->setCache("month");
			$response->setLayout('browser.ejs');
			$response->setTemplate("web.ejs", ['query'=>$query, 'settings'=>$settigns, 'html'=>$html]);
			return $response;
		}

		//
		// else search in the web and return results
		//

		// get the search results
		$results = $this->search($query);

		// if nothing was passed, let the user know
		if(empty($results)) {
			$response->setLayout('browser.ejs');
			return $response->setTemplate('error.ejs', ["query"=>$query, 'settings'=>$settigns]);
		}

		// create the response
		$response->setCache("year");
		$response->setLayout('browser.ejs');
		$response->setTemplate("google.ejs", ["query"=>$query, 'settings'=>$settigns, "results"=>$results]);
	}

	/**
	 * Get the user's browsing history
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @param Response $response
	 */
	public function _history (Request $request, Response $response)
	{
		// get the history for the person
		$pages = Connection::query("
			SELECT title, url, inserted 
			FROM _web_history 
			WHERE person_id = {$request->person->id}
			ORDER BY inserted 
			DESC LIMIT 20");

		// create the response
		$response->setTemplate("history.ejs", ["pages"=>$pages]);
	}

	/**
	 * Change the user's save mode
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @param Response $response
	 */
	public function _set (Request $request, Response $response)
	{
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
	 * @param String $url
	 * @param Integer $userId
	 * @param Boolean $saveMode
	 * @return String
	 */
	private function browse($url, $personId, $saveMode)
	{
		// chech if the page is in cache
		$urlHash = md5($url);
		$fileCache = Utils::getTempDir() . "/web/$urlHash.html";

		// load the page from cache
		if(file_exists($fileCache)) {
			// load from cache
			$html = file_get_contents($fileCache);
			$title = $this->getTitle($html);

			// increase cache counter
			Connection::query("UPDATE _web_cache SET visits=visits+1 WHERE url_hash='$urlHash'");
		}
		// load the page online
		else {
			// get the page from online
			$html = $this->getHtmlFromUrl($url);
			$title = $this->getTitle($html);

			// cache the page
			$title = Connection::escape($title);
			Connection::query("INSERT IGNORE INTO _web_cache (url_hash, url, title) VALUES ('$urlHash', '$url', '$title')");
			file_put_contents($fileCache, $html);
		}

		// stop for NO-JS errors or empty DOCTYPES
		if(strlen($html) < 500) return "";

		// save the page as visited by the user
		$title = Connection::escape($title, 250);
		Connection::query("INSERT INTO _web_history (person_id, url, title) VALUES ($personId, '$url', '$title')");

		// compress the page
		if($saveMode) $html = $this->minify($html, $url);

		return $html;
	}

	/**
	 * Search for a term in Bing and return formatted results
	 *
	 * @author kumahacker
	 * @param String $q
	 * @return Array
	 */
	private function search($q)
	{
		// do not allow empty queries
		if(empty($q)) return [];

		// get the Bing key
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$key = $di->get('config')['bing']['key1'];

		// perform the request and get the JSON response
		$context = stream_context_create(['http' => ['header' => "Ocp-Apim-Subscription-Key: $key\r\n", 'method' => 'GET']]);
		$result = file_get_contents("https://api.cognitive.microsoft.com/bing/v7.0/search?mkt=es-US&q=" . urlencode($q), false, $context);
		$json = json_decode($result);

		// format the search results
		$results = [];
		if(isset($json->webPages)) {
			foreach($json->webPages->value as $v) {
				$v = get_object_vars($v);
				$results[] = ["title" => $v['name'], 'url' => $v['url'], 'note' => $v['snippet']];
			}
		}

		return $results;
	}

	/**
	 * Get the title of an HTML page 
	 *
	 * @author salvipascual
	 * @param String $html 
	 * @param String $url
	 * @return String
	 */
	private function minify($html, $url)
	{
		$tidy = new tidy();
		$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
		$html = $tidy->repairString($html, [
			'output-xhtml' => true,
		], 'utf8');

		// create DOM element
		$dom = new DomDocument('1.0', 'UTF-8');
		$libxml_previous_state = libxml_use_internal_errors(true);
		@$dom->loadHTML($html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		// use only the BODY tag, we don't need the HEAD
		$body = $dom->getElementsByTagName('body');
		$body = $body->item(0);
		$html = $dom->savehtml($body);

		$html = $tidy->repairString($html, [
			'output-xhtml' => true,
		], 'utf8');

		$libxml_previous_state = libxml_use_internal_errors(true);
		@$dom->loadHTML($html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		// remove unwanted HTML tags
		$tags = ['meta','script','link','nav','style','iframe','video','canvas','form','input','select','textarea','button','svg','img'];
		foreach ($tags as $tag) {
			while (($r = $dom->getElementsByTagName($tag)) && $r->length) {
				$r->item(0)->parentNode->removeChild($r->item(0));
			}
		}

		// get the domain and directory from the URL
		$urlp = parse_url($url);
		$urlDomain = $urlp['scheme'].'://'.$urlp['host'];
		$urlDir = dirname($url).'/';

		// replace <a> by onclick
		foreach ($dom->getElementsByTagName('a') as $node) {
			// get the URL to the new page
			$src = trim($node->getAttribute("href"));

			// complete relative links
			if(php::startsWith($src, '/')) $src = $urlDomain.$src;
			elseif( ! php::startsWith($src, 'http')) $src = $urlDir.$src;

			// convert the links to onclick
			$node->setAttribute('href', "#!");
			$node->setAttribute('onclick', "parent.send({command:'WEB', data:{query:'$src'}}); return false;");
		}

		// convert DOM back to HTML code
		$html = utf8_decode($dom->saveHTML($dom->documentElement));

		// remove all unwanted attributes
		$attrs = ['style','id','class','align','target'];
		foreach ($attrs as $attr) {
			preg_match_all('/ '.$attr.'.*?=.*?".*?"/', $html, $matches);
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
	 * @return Boolean
	 */
	private function isValidDomain($domain)
	{
		// FILTER_VALIDATE_URL checks length but..why not? so we dont move forward with more expensive operations
		$domain_len = strlen($domain);
		if ($domain_len < 3 OR $domain_len > 253) return FALSE;

		// getting rid of HTTP/S just in case was passed.
		if(stripos($domain, 'http://') === 0) $domain = substr($domain, 7); 
		elseif(stripos($domain, 'https://') === 0) $domain = substr($domain, 8);

		// we dont need the www either
		if(stripos($domain, 'www.') === 0) $domain = substr($domain, 4);

		// Checking for a '.' at least, not in the beginning nor end, since http://.abcd. is reported valid
		if(strpos($domain, '.') === FALSE OR $domain[strlen($domain)-1]=='.' OR $domain[0]=='.') return FALSE;

		// now we use the FILTER_VALIDATE_URL, concatenating http so we can use it, and return BOOL
		return (filter_var ('http://' . $domain, FILTER_VALIDATE_URL)===FALSE)? FALSE : TRUE;
	}

	/**
	 * Get the title of an HTML page 
	 *
	 * @author salvipascual
	 * @param String $html 
	 * @return String
	 */
	private function getTitle($html)
	{
		// get the title using a regexp
		$res = preg_match("/<title>(.*)<\/title>/siU", $html, $matches);
		if ( ! $res) return ""; 

		// remove EOL's and excessive whitespace.
		return trim(preg_replace('/\s+/', ' ', $matches[1]));
	}

	/**
	 * Gets the HTML code for a website
	 *
	 * @author salvipascual
	 * @param String $html 
	 * @return String
	 */
	private function getHtmlFromUrl($url)
	{
		// prepare URL for CURL
		$url = str_replace("//", "/", $url);
		$url = str_replace("http:/","http://", $url);
		$url = str_replace("https:/","https://", $url);

		// prepare headers for CURL
		$headers = [];
		foreach ([
			"Cache-Control" => "max-age=0",
			"Origin" => "{$url}",
			"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36",
			"Content-Type" => "application/x-www-form-urlencoded"
		] as $key => $val) $headers[] = "$key: $val";

		// start CURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$html = curl_exec($ch);
		$info = curl_getinfo($ch);

		// handle 301 redirects
		if ($info['http_code'] == 301 && isset($info['redirect_url']) && $info['redirect_url'] != $url) {
			return $this->getHtmlFromUrl($info['redirect_url']);
		}

		curl_close($ch);
		return $html;
	}
}