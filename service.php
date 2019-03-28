<?php

class Service
{
	/**
	 * Opens the browser screen
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _main (Request $request, Response $response)
	{
		$request->input->query = "hello world";

		// get the search results
		$results = $this->search($request->input->query);

		// create the response
		$content = ["query" => $request->input->query, "results" => $results];
		$response->setCache("year");
		$response->setTemplate("google.ejs", $content);
		return $response;




		//
		// show welcome message when query is empty
		//
		if (empty($request->input->query)) {
			// get visits
			$result = Connection::query("SELECT * FROM _navegar_visits WHERE site is not null and site <> '' ORDER BY usage_count DESC LIMIT 10;");
			if (empty($result[0])) $result = false;

			// create response
			$response->setCache("month");
			$response->setTemplate("home.ejs", ['visits' => $result]);
			return $response;
		}

		// fix broken URLs
		if (substr($request->query, 0, 2) == '//') $request->query = 'http:' . $request->query;
		elseif (substr($request->query, 0, 1) == '/')  $request->query = 'http:/' . $request->query;

		//
		// if the argument is a URL, open it
		//
		$url = $this->isValidUrl($request->query);
		if($url) {
			set_time_limit(900);

			// get the HTML from the URL
			$html = $this->getWeb($url);

			if (is_object($html))
				if (get_class($html)=="Response")
					return $html;

			// save visit in the database
			$this->saveVisit($url);

			// check if the response is for app or email
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$byEmail = $di->get('environment') != "app";

			$response->setCache("month");
			$response->setEmailLayout('email_minimal.ejs');
			$response->setTemplate("basic.ejs", array("body"=>$html, "url"=>$url, "byEmail"=>$byEmail));
			return $response;
		}

		//
		// else search the web using Google
		//
		// include the service
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwroot = $di->get('path')['root'];
		require_once "$wwwroot/services/google/service.php";

		// call google and get the response
		$google = new Google();
		$google->utils = new Utils();
		$google->pathToService = "$wwwroot/services/google/";
		$response = $google->_main($request);
		$response->template = "google.ejs";
		$response->setCache("month");
		return $response;
	}

	/**
	 *
	 */
	private function getTempDir ()
	{
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwroot = $di->get('path')['root'];

		if (! file_exists("$wwwroot/temp/navegar")) mkdir("$wwwroot/temp/navegar");
		if (! file_exists("$wwwroot/temp/navegar/cookies")) mkdir("$wwwroot/temp/navegar/cookies");
		if (! file_exists("$wwwroot/temp/navegar/files")) mkdir("$wwwroot/temp/navegar/files");
		if (! file_exists("$wwwroot/temp/navegar/searchcache")) mkdir("$wwwroot/temp/navegar/searchcache");

		return "$wwwroot/temp/navegar";
	}

	/**
	 * Return full HREF
	 */
	private function getFullHref ($href, $url)
	{
		$href = trim($href);
		if ($href == '' || $href == 'javascript(0);' || $href == 'javascript:void(0);') return $url;
		if (strtolower(substr($href, 0, 2) == '//')) return 'http:' . $href;
		if (strtolower(substr($href, 0, 1) == '?')) {
			if (! is_null($this->base)) return $this->base . $href;
			return $url . $href;
		}

		$base = '';

		if ($this->isHttpURL($href) || $this->isFtpURL($href)) return $href;

		if (! $this->isHttpURL($url) && ! $this->isFtpURL($url)) $url = 'http://' . $url;

		$url = trim($url);

		if (substr($url, strlen($url) - 1, 1) == "/") $url = substr($url, 0, strlen($url) - 1);

		$parts = parse_url($url);

		if (! is_null($this->base))
			$base = $this->base;
		else {
			if ($parts === false) return $href;

			if (! isset($parts['port'])) $parts['port'] = 80;

			$base = $parts['scheme'] . "://" . $parts['host'] . ":" . $parts['port'] . "/";

			if (isset($parts['path'])) {
				$p = $parts['path'];

				$exts = explode(' ', 'html htm php5 exe php jsp asp aspx jsf py');

				foreach ($exts as $ext)
					if (stripos($p, '.' . $ext) !== false) {
						$base .= dirname($p) . "/";
						break;
					}
			}
		}

		return $base . $href;
	}

	/**
	 * Return TRUE if url is a HTTP or HTTPS
	 *
	 * @param string $url
	 * @return boolean
	 */
	private function isHttpURL ($url)
	{
		$url = trim(strtolower($url));
		return strtolower(substr($url, 0, 7)) === 'http://' || strtolower(substr($url, 0, 8)) === 'https://';
	}

	/**
	 * Return TRUE if url is a FTP
	 *
	 * @param string $url
	 * @return boolean
	 */
	private function isFtpURL ($url)
	{
		$url = trim(strtolower($url));
		return strtolower(substr($url, 0, 6)) === 'ftp://';
	}

	/**
	 * Save visit stats
	 *
	 * @param string $url
	 */
	private function saveVisit ($url)
	{
		try {
			$site = parse_url($url, PHP_URL_HOST);

			if ($site === false) $site = $url;

			if (! empty(trim($site))) return false;

			$r = Connection::deepQuery("SELECT * FROM _navegar_visits WHERE site = '$site';");

			if (empty($r)) {

				$sql = "INSERT INTO _navegar_visits (site) VALUES ('$site');";
			} else {
				$sql = "UPDATE _navegar_visits SET usage_count = usage_count + 1, last_usage = CURRENT_TIMESTAMP WHERE site = '$site';";
			}

			Connection::deepQuery($sql);

			return true;
		} catch (Exception $e) {}
	}

	/**
	 * Return web page has PDF
	 *
	 * @author salvi
	 * @param Request $request
	 */
	public function _pdf(Request $request)
	{
		// do not allow empty pages\
		$url = $request->query;
		if(empty($url))
		{
			$response->createFromText("Usted no ha insertado ninguna website a buscar. Inserte la direcci&oacute;n web en el asunto del email justo despu&eacute;s de la palabra WEB.<br/><br/>Por ejemplo:<br/>Asunto: WEB google.com");
			return $response;
		}

		// check if the url exist
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// do not work for non-existing websites or webs giving errors
		if($statusCode != 200)
		{
			$response->createFromText("La website <b>$url</b> no existe o se encuentra temporalmente ca&iacute;da. Por favor compruebe la sintaxis o intente nuevamente en algunos minutos.");
			return $response;
		}

		// get path to the www folder
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$www_root = $di->get('path')['root'];

		// download the website as pdf
		$file = "$www_root/temp/" . $this->utils->generateRandomHash() . ".pdf";
		$command = " -lq --no-background --images --disable-external-links --disable-forms --disable-javascript --viewport-size 1600x900 $url $file";
		shell_exec("/usr/local/bin/wkhtmltopdf $command");

		// error if the web could not be downloaded
		if( ! file_exists($file))
		{
			shell_exec("/usr/bin/wkhtmltopdf $command");

			if( ! file_exists($file))
			{
				$response->createFromText("Tuvimos un error descargando la website <b>$url</b>. Por favor intente nuevamente en algunos minutos.");
				return $response;
			}
		}

		// respond to the user with the pdf of website attached
		$response->setTemplate("pdf.ejs", array("url"=>$url), array(), array($file));
		return $response;
	}

	/**
	 * Subservice PAGINAS
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _paginas($request)
	{
		$www_root = $this->pathToService."/../../public/w/";

		$limit = 10;
		$offset = intval(trim($request->query));
		if ($offset < 1) $offset = 1;
		$offset -= 1;
		$offset *= $limit;

		$total = $connection->query("SELECT count(domain) as t FROM _web_sites;");
		$total = $total[0]->t;

		$sites = $connection->query("SELECT *, (SELECT usage_count FROM _navegar_visits WHERE site = concat(_web_sites.domain, '.apretaste.com')) as popularity FROM _web_sites order by popularity desc LIMIT $offset, $limit;");
		$offsets = intval($total / $limit) + 1;

		if ($offsets < 2) $offsets = 0;

		$pagging = array();
		for($i = 1; $i <= $offsets; $i++) $pagging[]= $i;

		if (is_array($sites) && count($sites) > 0)
		{
			foreach($sites as $k=>$site)
			{
				if (trim($site->title)==='')
					$sites[$k]->title = $site->domain . ".apretaste.com";
					$summary = '';
					$findex = $www_root."{$site->domain}/index.html";
					if (file_exists($findex))
					{
						$summary = @file_get_contents($findex);
						$summary = strip_tags($summary);
						$summary = substr($summary, 0, 200) . "...";
					}
					$sites[$k]->summary = $summary;
			}

			$response->setCache("day");
			$response->setTemplate('sites.ejs', array('sites' => $sites, 'pagging' => $pagging));
			return $response;
		}

		$response->createFromText("No se econtraron p&aacute;ginas en Apretaste");
		return $response;
	}

	/**
	 * Publish a web page online under apretaste.com domain
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _publicar(Request $request)
	{
		$www_root = $this->pathToService."/../../public/w/";
		$domain = trim($request->query);
		$title = '';

		$p = strpos($domain, ' ');
		if ($p !== false)
		{
			$domain = substr($domain,0,$p);
			$title = trim(substr($domain,$p));
		}

		$domain = $this->utils->clearStr($domain);
		$domain = strtolower($domain); // super important!

		$owner = $request->email;
		$websites = $connection->query("SELECT * FROM _web_sites WHERE owner = '$owner';");

		if ( ! is_array($websites)) $websites = array();
		if ( ! file_exists($www_root)) mkdir($www_root);

		$exists = false;
		if (file_exists($www_root."$domain"))
		{
			$exists = true;
			$sql = "SELECT * FROM _web_sites WHERE domain ='$domain';";
			$r = $connection->query($sql);

			if (isset($r[0]->owner)) if ($r[0]->owner !== $owner)
			{
				$response->createFromText("Ya existe una web llamada $domain en Apretaste y t&uacute; no eres su due&ntilde;o. Rectifica que est&eacute;s escribiendo bien el nombre que deseas o utiliza otro nombre.");
				return $response;
			}
		}
		else
		{
			mkdir($www_root."$domain");
		}

		$files_changed = [];

		$num_files = 0;
		foreach($request->attachments as $at)
		{
			if (isset($at->type))
			{
				if (strpos("jpg,jpeg,image/jpg,image/jpeg,image/png,png,image/gif,gif,text/plain,text,html,text/html,text/css,application/javascript,otf,application/x-font-ttf,image/svg+xml,application/vnd.ms-fontobject,application/x-font-woff,application/x-font-woff2,application/octet-stream",$at->type)!==false)
				{
					if (isset($at->name))
					{
						$num_files++;
						$filename = $at->name;
						$filename = str_ireplace(".php", "", $filename);
						$files_changed[] = $filename;
						$filePath = $www_root."$domain/$filename";
						if (file_exists($filePath)) unlink($filePath);
						file_put_contents($filePath, base64_decode($at->content));
					}
				}
			}
		}

		$index_default = "<h1>$domain</h1>";

		if ( ! file_exists($www_root."$domain/index.html"))
		{
			file_put_contents($www_root."$domain/index.html", $index_default);
		}

		if ($exists)
		{
			$sql = "UPDATE _web_sites SET title = '$title' WHERE domain = '$domain';";
		}
		else
		{
			$sql = "INSERT IGNORE INTO _web_sites (domain, title, owner) VALUES ('$domain', '$title','$owner');";
		}

		$connection->query($sql);
		$response->setTemplate("public.ejs", array(
			'domain' => $domain,
			'title' => $title,
			'num_files' => $num_files,
			'files_changed' => $files_changed
		));

		// post in pizarra
		$text = ($exists ? "He actualizado mi" : "He publicado un"). " sitio web en Apretaste. Pueden visitarlo en http://$domain.apretaste.com";
		$connection->query("INSERT INTO _pizarra_notes (email, text) VALUES ('{$request->email}', '$text')");

		return $response;
	}

	/*
	 * Open a URL
	 */
	private function getWeb($url)
	{
		// get the contents of the URL
		$html = $this->getUrl($url, $info); //file_get_contents($url);

		// show image

		if (isset($info['content_type']))
		{
			if (substr($info['content_type'], 0, 6) == 'image/')
			{
				// save image file
				$filePath = $this->getTempDir() . "/files/image-" . md5($url) . ".jpg";
				file_put_contents($filePath, $html);

				// save the image in the array for the template
				$images = [$filePath];
				$response->setTemplate('image.ejs', [
						'title' => 'Imagen en la web',
						'type' => 'image',
						'images' => $images,
						'url' => $url
				], $images);

				return $response;
			}
		}

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
		foreach ($dom->getElementsByTagName('a') as $node)
		{
			// get place where the link points
			$src = $node->getAttribute("href");
			$src = (substr($src,0,1)!="/")?$src:substr($src,1);
			$part=substr($src,0,stripos($src,"/"));
			$last=substr($url,strlen($url)-strlen($part)-1,strlen($part));

			if ($part==$last) {
				$src = str_ireplace($part,"",$src);
				$src = (substr($src,0,1)!="/")?$src:substr($src,1);
			}

			// replace inner links by their full vesion
			if( ! $this->isValidUrl($src)) $src = "$url/$src";
			str_replace("//", "/", $src);

			// if it is not working for email, convert the links to onclick
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			if($di->get('environment') != "email") {
				$node->setAttribute('href', "#!");
				$node->setAttribute('onclick', "apretaste.doaction('WEB $src', false, '', true, ''); return false;");
			}
			// else convert the links to mailto
			else{
				$apValidEmailAddress = $this->utils->getValidEmailAddress();
				$node->setAttribute('href', "mailto:$apValidEmailAddress?subject= WEB $src");
			}
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

	/*
	 * Check if URL is valid
	 */
	private function isValidUrl($uri)
	{
		if (strpos(substr($uri,0,strlen($uri)-6), '.') == false) return false; // urls must contain a dot, and not in the end (extension)
		if ( ! (substr($uri, 0, 4) == 'http')) $uri = "http://$uri"; // force http
		return filter_var($uri, FILTER_SANITIZE_URL);
	}

	private function getUrl($url, &$info = [])
	{
		$url = str_replace("//", "/", $url);
		$url = str_replace("http:/","http://", $url);
		$url = str_replace("https:/","https://", $url);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);

		$default_headers = [
			"Cache-Control" => "max-age=0",
			"Origin" => "{$url}",
			"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36",
			"Content-Type" => "application/x-www-form-urlencoded"
		];

		$hhs = [];
		foreach ($default_headers as $key => $val)
			$hhs[] = "$key: $val";

		curl_setopt($ch, CURLOPT_HTTPHEADER, $hhs);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$html = curl_exec($ch);

		$info = curl_getinfo($ch);

		if ($info['http_code'] == 301)
			if (isset($info['redirect_url']) && $info['redirect_url'] != $url)
				return $this->getUrl($info['redirect_url'], $info);

		curl_close($ch);

		return $html;
	}







	/**
	 * Generic searcher
	 *
	 * @author kumahacker
	 * @param string $q
	 * @param integer $engine
	 * @return array
	 */
	private function search($q)
	{
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
	 * Return a remote content
	 *
	 * @author vilfer
	 * @param  string url
	 * @param integer key
	 *	@param string query
	 *	@return array
	 */
	private function BingWebSearch($url, $key, $query)
	{

	}
}
