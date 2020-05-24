<?php

use Apretaste\Request;
use Apretaste\Response;
use Framework\Utils;
use Framework\Alert;
use Framework\Config;
use Framework\Crawler;
use Framework\Database;
use Apretaste\Challenges;

class Service
{

	/**
	 * Check current version or person
	 *
	 * @param  \Apretaste\Request  $request
	 * @param  \Apretaste\Response  $response
	 *
	 * @return bool
	 * @throws \Framework\Alert
	 */
	public function checkVersion(Request $request, Response $response){
		if ($request->input->appVersion < '6.0.5') {
			$response->setTemplate('message.ejs', [
			  'header' => 'Actualice la app',
			  'icon' => 'sentiment_very_dissatisfied',
			  'text' => 'Lo siento pero este servicio exige que se actualice la app a la version 6.0.5 o superior. ',
			  'button' => ['href' => 'WEB', 'caption' => 'Intentar nuevamente'],
			]);
			return false;
		}
		return true;
	}

	/**
	 * Opens the browser screen
	 *
	 * @param  Request  $request
	 * @param  Response  $response
	 *
	 * @return \Apretaste\Response
	 * @throws \Framework\Alert
	 * @author salvipascual
	 */
	public function _main(Request $request, Response $response)
	{

		if (!$this->checkVersion($request, $response)) return;

		// get data from the request
		$query = isset($request->input->data->query) ? $request->input->data->query : '';

		//
		// SHOW welcome message when query is empty
		//

		if (empty($query)) {
			// get most visited websites
			$sites = Database::queryCache('
				SELECT COUNT(*) AS cnt, domain 
				FROM _web_history 
				WHERE domain IS NOT NULL
				GROUP BY domain 
				ORDER BY cnt DESC 
				LIMIT 9');

			// create response
			$response->setCache("day");
			return $response->setTemplate('home.ejs', ['sites'=>$sites]);
		}

		//
		// DOWNLOAD the page if a valid domain name or URL is passed
		//

		if (Utils::isDomainValid($query)) {

			// get the page files
			$files = $this->browse($query, $request->person->id);

			// complete challenge
			Challenges::complete('open-web-page', $request->person->id);

			// return the page
			return $response->setWebsite($files);
		}

		//
		// SEARCH the web and return results
		//

		// get the search results
		$results = $this->search($query);

		// complete challenge
		Challenges::complete('search-in-the-web', $request->person->id);

		// if nothing was passed, let the user know
		if (empty($results)) {
				return $response->setTemplate('message.ejs', [
				'header' => 'No hay resultados',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => 'Lo siento pero no hemos encontrado ningún resultado para su búsqueda. Por favor intente con otro término.',
				'button' => ['href' => 'WEB', 'caption' => 'Intentar nuevamente'],
			]);
		}

		// create the response
		$response->setCache('year');
		$response->setTemplate('google.ejs', ['query' => $query, 'results' => $results]);
	}

	/**
	 * Get the user's browsing history
	 *
	 * @param  Request  $request
	 * @param  Response  $response
	 *
	 * @return \Apretaste\Response
	 * @throws \Framework\Alert
	 * @author salvipascual
	 */
	public function _history(Request $request, Response &$response)
	{
		if (!$this->checkVersion($request, $response)) return;

		// get the history for the person
		$pages = Database::query("
			SELECT url, inserted 
			FROM _web_history 
			WHERE person_id = {$request->person->id}
			ORDER BY inserted DESC
			LIMIT 20");

		// if there is no data, show message
		if (empty($pages)) {
				return $response->setTemplate('message.ejs', [
				'header' => 'Historial vacío',
				'icon' => 'web',
				'text' => 'Aún no ha abrierto ninguna pagina web, por lo cual su historial esta vacío. Navegue un rato por la web y luego regrese acá.',
				'button' => ['href' => 'WEB', 'caption' => 'Navegar'],
			]);
		}

		// create the response
		$response->setTemplate('history.ejs', ['pages' => $pages]);
	}

	/**
	 * Download a website and return the array of files
	 *
	 * @param  String  $url
	 * @param  Integer  $personId
	 *
	 * @return String[]
	 * @throws \Framework\Alert
	 * @author salvipascual
	 *
	 */
	private function browse($url, $personId)
	{
		// get the code of the page
		$page = Crawler::getCache($url);

		// convert links to navigate using Apretaste
		$page = $this->processPage($page, $url);

		// get the files for the page
		foreach ($page['images'] as $name => $content) {
			file_put_contents(LOCAL_TEMP_FOLDER. $name, $content);
		}

		$file = LOCAL_TEMP_FOLDER . 'index.html';
		file_put_contents($file, $page['page']);

		// get the page domain
		$parse = parse_url($url);
		$domain = $parse['host'] ?? $parse['path'];

		// save the search history
		Database::query("
			INSERT INTO _web_history (person_id, url, domain) 
			VALUES ($personId, '$url', '$domain')");

		// return the page files
		return [$file];
	}

	/**
	 * Search for a term in Bing and return formatted results
	 *
	 * @param  String  $q
	 *
	 * @return array
	 * @throws \Framework\Alert
	 * @author kumahacker
	 */
	private function search($query)
	{
		// do not allow empty queries
		if (empty($query)) return [];

		// try to get results from the cache
		$cache = TEMP_PATH  . '/cache/bing_' . md5($query) . '.cache';
		if (file_exists($cache)) {
			$results = unserialize(file_get_contents($cache));
		}

		// get from the Bing API
		else {
			// get the Bing key
			$endpoint = Config::pick('bing')['endpoint'];
			$key = Config::pick('bing')['key'];

			// search using bing 
			try {
				$result = Crawler::get($endpoint . '/search?mkt=es-US&q=' . urlencode($query), 'GET', null, ["Ocp-Apim-Subscription-Key: $key"]);
				$json = json_decode($result);
			} catch (Exception $e) {
				throw new Alert('581', 'No se pudo realizar su búsqueda. El equipo técnico está informado.');
			}

			// format the search results
			$results = [];
			if (isset($json->webPages)) {
				foreach ($json->webPages->value as $v) {
					$v = get_object_vars($v);
					$results[] = [
						'title' => $v['name'],
						'url' => $v['url'],
						'note' => strlen($v['snippet']) > 100 ? substr($v['snippet'], 0, 100) . "..." : $v['snippet']
					];
				}
			}

			// save results in cache
			file_put_contents($cache, serialize($results));
		}

		return $results;
	}

	/**
	 * Prepare HTML page
	 *
	 * @param $page
	 * @param $url
	 *
	 * @return array
	 */
	private function processPage($page, $url){
		$images = [];

		// clear $url
		$url = str_replace("///", "/", $url);
		$url = str_replace("//", "/", $url);
		$url = str_replace("http:/", "http://", $url);
		$url = str_replace("https:/", "https://", $url);

		if (strpos($url, '//') === 0) {
			$url = 'http:' . $url;
		}
		else if (strpos($url, '/') === 0) {
			$url = 'http:/' . $url;
		}

		// repair html
		$tidy = new tidy();
		$page = mb_convert_encoding($page, 'HTML-ENTITIES', 'UTF-8');
		$page = $tidy->repairString($page, [
		  'output-xhtml' => TRUE,
		], 'utf8');

		// create DOM, ignore errors
		$doc  = new DomDocument('1.0', 'UTF-8');
		$libxml_previous_state = libxml_use_internal_errors(TRUE);
		@$doc->loadHTML($page);
		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		// links
		$links = $doc->getElementsByTagName('a');

		if ($links->length > 0) {
			foreach ($links as $link) {
				$href = $link->getAttribute('href');

				if ($href === FALSE || empty($href)) {
					$href = $link->getAttribute('data-src');
				}

				if (strpos($href, '#') === 0) {
					$link->setAttribute('href', '#!');
					$link->setAttribute('anchor-link', $href);
					$link->setAttribute('onclick', 'return false;');
					$link->setAttribute('class', $link->getAttribute('class').' anchor-link');
					continue;
				}

				if (stripos($href, 'mailto:') === 0) {
					continue;
				}

				$href = $link->getAttribute('href');
				$link->setAttribute('onclick', "parent.send({command: 'web', data: {query: '$href'}});");
				$link->setAttribute('href',  '#!');
			}
		}

		// images
		$imagesTags = $doc->getElementsByTagName('img');

		if ($imagesTags->length > 0) {
			foreach ($imagesTags as $image) {
				$src = $image->getAttribute('src');

				$img = null;
				try {
					$name = 'img'.uniqid();
					$img  = Crawler::get($src);
					$images[$name] = $img;
					$image->setAttribute('src', $name);
				} catch (Alert $a) {

				}
			}
		}

		// css stylesheets
		$styles = $doc->getElementsByTagName('link');
		if ($styles->length > 0) {

			// You can modify, and even delete, nodes from a DOMNodeList if you iterate backwards
			for ($i = $styles->length; --$i >= 0; ) {

				/** @var \DOMElement $style */
				$style = $styles->item($i);

				if ($style->getAttribute('rel') === 'stylesheet') {

					try {
						$remoteStyle = Crawler::get($style->getAttribute('href'));
						/** @var \DOMNodeList $head */
						$head = $doc->getElementsByTagName('head');

						$new_elm = $doc->createElement('style', $remoteStyle);
						$elm_type_attr = $doc->createAttribute('type');
						$elm_type_attr->value = 'text/css';

						// append style tag
						$head->appendChild($new_elm);

						// remove external css
						$style->parentNode->removeChild($style);

					} catch(Alert $a){

					}
				}
			}
		}

		// js scripts
		$scripts = $doc->getElementsByTagName('script');

		// You can modify, and even delete, nodes from a DOMNodeList if you iterate backwards
		for ($i = $scripts->length; --$i >= 0; ) {

			/** @var \DOMElement $style */
			$script = $scripts->item($i);
			if ($script === null) {
				continue;
			}

			try {
				$remoteScript = Crawler::get($style->getAttribute('src'));
				/** @var \DOMNodeList $head */
				$body = $doc->getElementsByTagName('body');

				$new_elm = $doc->createElement('script', $remoteScript);
				$elm_type_attr = $doc->createAttribute('type');
				$elm_type_attr->value = 'text/javascript';

				// append style tag
				$body->appendChild($new_elm);

				// remove external css
				$script->parentNode->removeChild($script);

			} catch(Alert $a){

			}
		}

		// get page from DOM
		$page = $doc->saveHTML();
		//$page = strip_tags($page, '<html><meta><body><head><script><style><a><p><label><div><pre><h1><h2><h3><h4><h5><button><i><b><u><li><ol><ul><fieldset><small><legend><form><input><span><button><nav><table><tr><th><td><thead><img><link>');

		return [
		  'page' => $page,
		  'images' => $images
		];
	}
}
