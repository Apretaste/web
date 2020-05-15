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
	 * Opens the browser screen
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws \Framework\Alert
	 * @author salvipascual
	 */
	public function _main(Request $request, Response $response)
	{
		// get data from the request
		$query = isset($request->input->data->query) ? $request->input->data->query : '';

		//
		// show welcome message when query is empty
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
		// download the page if a valid domain name or URL is passed
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
		// else search the web and return results
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
		$response->setCache("year");
		$response->setTemplate('google.ejs', ['query' => $query, 'results' => $results]);
	}

	/**
	 * Get the user's browsing history
	 *
	 * @param Request $request
	 * @param Response $response
	 * @author salvipascual
	 */
	public function _history(Request $request, Response &$response)
	{
		// get the history for the person
		$pages = Database::query("
			SELECT url, inserted 
			FROM _web_history 
			WHERE person_id = {$request->person->id}
			ORDER BY inserted DESC
			LIMIT 20");

		// create the response
		$response->setTemplate('history.ejs', ['pages' => $pages]);
	}

	/**
	 * Download a website and return the array of files
	 *
	 * @author salvipascual
	 *
	 * @param String $url
	 * @param Integer $personId
	 * @return String[]
	 */
	private function browse($url, $personId)
	{
		// get the code of the page
		$page = Crawler::getCache($url);

		// convert links to navigate using Apretaste
		// @TODO

		// get the files for the page
		// @TODO save all the page resources like img and css
		$file = LOCAL_TEMP_FOLDER . 'index.html';
		file_put_contents($file, $page);

		// get the page domain
		$parse = parse_url($url);
		$domain = $parse['host'];

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
	 * @param String $q
	 * @return array
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
}
