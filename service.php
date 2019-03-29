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
		$query = $request->input->data->query;
		$save = $request->input->data->save;

		//
		// show welcome message when query is empty
		//

		if (empty($query)) {
			// get visits
			$result = Connection::query("SELECT * FROM _navegar_visits WHERE site is not null and site <> '' ORDER BY usage_count DESC LIMIT 10;");
			if (empty($result[0])) $result = false;

			// create response
			$response->setCache("day");
			$response->setLayout('browser.ejs');
			$response->setTemplate("home.ejs", ['query'=>'', 'visits' => $result]);
			return $response;
		}

		//
		// download the page if a valid domain name or URL is passed
		//

		if ($this->isValidDomain($query)) {
			// add the scheeme if not passed
			if( ! php::startsWith($query, "http")) $query = "http://$query";

			// get the html code of the page
			$html = $this->browseCompress($query);

			// create response
			$response->setCache("month");
			$response->setLayout('browser.ejs');
			$response->setTemplate("web.ejs", ['query'=>$query, 'html'=>$html]);
			return $response;
		}

		//
		// else search in the web and return results
		//

		// get the search results
		$results = $this->search($query);

		// if nothing was passed, let the user know
		if(empty($results)) {
			$content = [
				"header"=>"No hay resultados",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "No encontramos resultados para el término '{$query}'. Por favor modifique su búsqueda e intente nuevamente",
				"button" => ["href"=>"WEB", "caption"=>"Cambiar búsqueda"]];
			return $response->setTemplate('message.ejs', $content);
		}

		// create the response
		$response->setCache("year");
		$response->setLayout('browser.ejs');
		$response->setTemplate("google.ejs", ["query"=>$query, "results"=>$results]);
	}

	/**
	 * Download the compressed version of a website
	 *
	 * @author salvipascual
	 * @param Request $url
	 * @return String
	 */
	private function browseCompress($url)
	{
		// get the website
		$html = file_get_contents($url);

		// create DOM element
		$dom = new DOMDocument;
		@$dom->loadHTML($html);

		// remove <meta> tags
		while (($r = $dom->getElementsByTagName("meta")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}

		// remove <script> tags
		while (($r = $dom->getElementsByTagName("script")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}

		// remove outside css
		while (($r = $dom->getElementsByTagName("link")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}

		// remove embebed css
		while (($r = $dom->getElementsByTagName("style")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}

		// remove iframes
		while (($r = $dom->getElementsByTagName("iframe")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}

		// remove forms and form elements
		while (($r = $dom->getElementsByTagName("form")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}
		while (($r = $dom->getElementsByTagName("input")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}
		while (($r = $dom->getElementsByTagName("select")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}
		while (($r = $dom->getElementsByTagName("textarea")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}

		// remove all comments
		$xpath = new DOMXPath($dom);
		foreach ($xpath->query('//comment()') as $comment) {
			$comment->parentNode->removeChild($comment);
		}
		$body = $xpath->query('//body')->item(0);
		$dom->saveXml($body);

		// remove <img> tags
		while (($r = $dom->getElementsByTagName("img")) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}

		// replace <a> by mailto or onclick
		foreach ($dom->getElementsByTagName('a') as $node) {
			// get place where the link points
			$src = $node->getAttribute("href");
			$src = (substr($src,0,1)!="/")?$src:substr($src,1);
			$part = substr($src,0,stripos($src,"/"));
			$last = substr($url,strlen($url)-strlen($part)-1,strlen($part));

			if ($part == $last) {
				$src = str_ireplace($part,"",$src);
				$src = (substr($src,0,1) != "/")?$src:substr($src, 1);
			}

			// // replace inner links by their full vesion
			// if( ! $this->isValidUrl($src)) $src = "$url/$src";
			// str_replace("//", "/", $src);

			// convert the links to onclick
			$node->setAttribute('href', "#!");
			$node->setAttribute('onclick', "apretaste.send({})"); //@TODO
		}

		// convert DOM back to HTML code
		$html = $dom->saveHTML();

		// remove inline css
		preg_match_all('/ style.*?=.*?".*?"/', $html, $matches);
		foreach ($matches[0] as $match) {
			$html = str_replace($match, "", $html);
		}

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
		$result = file_get_contents("https://api.cognitive.microsoft.com/bing/v7.0/search?q=" . urlencode($q), false, $context);
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

		//getting rid of HTTP/S just in case was passed.
		if(stripos($domain, 'http://') === 0) $domain = substr($domain, 7); 
		elseif(stripos($domain, 'https://') === 0) $domain = substr($domain, 8);

		//we dont need the www either
		if(stripos($domain, 'www.') === 0) $domain = substr($domain, 4);

		//Checking for a '.' at least, not in the beginning nor end, since http://.abcd. is reported valid
		if(strpos($domain, '.') === FALSE OR $domain[strlen($domain)-1]=='.' OR $domain[0]=='.') return FALSE;

		//now we use the FILTER_VALIDATE_URL, concatenating http so we can use it, and return BOOL
		return (filter_var ('http://' . $domain, FILTER_VALIDATE_URL)===FALSE)? FALSE : TRUE;
	}
}