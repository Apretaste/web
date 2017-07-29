<?php

class Web extends Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _main (Request $request, $agent = 'default')
	{
		//
		// show welcome message when query is empty
		//
		if (empty($request->query))
		{
			// get visits
			$db = new Connection();
			$result = $db->query("SELECT * FROM _navegar_visits WHERE site is not null and site <> '' ORDER BY usage_count DESC LIMIT 10;");
			if (empty($result[0])) $result = false;

			// get internal websites
			$sites = $db->query("SELECT domain FROM _web_sites order by inserted desc LIMIT 10;");
			if (empty($sites[0])) $sites = false;

			// create response
			$response = new Response();
			$response->setResponseSubject("Navegar en Internet");
			$response->createFromTemplate("home.tpl", array('visits' => $result, 'sites' => $sites));
			return $response;
		}

		// fix broken URLs
		if (substr($request->query, 0, 2) == '//') $request->query = 'http:' . $request->query;
		elseif (substr($request->query, 0, 1) == '/')  $request->query = 'http:/' . $request->query;

		//
		// if the argument is a URL, open it
		//
		$url = $this->isValidUrl($request->query);
		if($url)
		{
			set_time_limit(900);

			// get the HTML from the URL
			$html = $this->getWeb($url);

			// save visit in the database
			$this->saveVisit($url);

			// check if the response is for app or email
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$byEmail = $di->get('environment') != "app";

			$response = new Response();
			$response->setResponseSubject("Su web {$request->query}");
			$response->setEmailLayout('email_minimal.tpl');
			$response->createFromTemplate("basic.tpl", array("body"=>$html, "url"=>$url, "byEmail"=>$byEmail));
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
		$response->template = "google.tpl";
		return $response;
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
			$response = new Response();
			$response->setResponseSubject("Debe insertar una website a buscar");
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
			$response = new Response();
			$response->setResponseSubject("La website no existe o esta temporalmente caida");
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
				$response = new Response();
				$response->setResponseSubject("Error descargando su website");
				$response->createFromText("Tuvimos un error descargando la website <b>$url</b>. Por favor intente nuevamente en algunos minutos.");
				return $response;
			}
		}

		// respond to the user with the pdf of website attached
		$response = new Response();
		$response->setResponseSubject("Aqui esta su website");
		$response->createFromTemplate("pdf.tpl", array("url"=>$url), array(), array($file));
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
		$connection = new Connection();
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

		$response = new Response();

		if (is_array($sites))
			if (count($sites) > 0)
			{
				foreach($sites as $k=>$site)
				{
					if (trim($site->title)==='')
						$sites[$k]->title = $site->domain . ".apretaste.com";
						$summary = '';
						$findex = $www_root."{$site->domain}/index.html";
						if (file_exists($findex))
						{
							$summary = file_get_contents($findex);
							$summary = strip_tags($summary);
							$summary = substr($summary, 0, 200) . "...";
						}
						$sites[$k]->summary = $summary;
				}

				$response->setResponseSubject("Directorio de paginas en Apretaste");
				$response->createFromTemplate('sites.tpl', array('sites' => $sites, 'pagging' => $pagging));
				return $response;
			}

		$response->setResponseSubject('No se econtraron paginas en Apretaste');
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
		$connection = new Connection();
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
				$response = new Response();
				$response->setResponseSubject("Web: No se pudo publicar la web en $domain");
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
		$response = new Response();
		$response->setResponseSubject("Su web ha sido publicada en Apretaste");
		$response->createFromTemplate("public.tpl", array(
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
		foreach ($dom->getElementsByTagName('a') as $node)
		{
			// get place where the link points
			$src = $node->getAttribute("href");

			// replace inner links by their full vesion
			if( ! $this->isValidUrl($src)) $src = "$url/$src";
			str_replace("//", "/", $src);

			// if it is working for the app, convert the links to onclick
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			if($di->get('environment') == "app") {
				$node->setAttribute('href', "");
				$node->setAttribute('onclick', "apretaste.doaction('WEB $src', false, '', true);");
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
		if (strpos($uri, '.') == false) return false; // urls must contain a dot
		if ( ! (substr($uri, 0, 4) == 'http')) $uri = "http://$uri"; // force http
		return filter_var($uri, FILTER_VALIDATE_URL);
	}

	/**
	 * Save visit for future stats
	 *
	 * @param string $url
	 */
	private function saveVisit($url)
	{
		try {
			$site = parse_url($url, PHP_URL_HOST);
			if ($site === false) $site = $url;
			if ( ! empty(trim($site))) return false;

			$db = new Connection();
			$r = $db->query("SELECT * FROM _navegar_visits WHERE site = '$site';");
			if (empty($r)) $sql = "INSERT INTO _navegar_visits (site) VALUES ('$site');";
			else $sql = "UPDATE _navegar_visits SET usage_count = usage_count + 1, last_usage = CURRENT_TIMESTAMP WHERE site = '$site';";
			$db->query($sql);

			return true;
		} catch (Exception $e) { return false; }
	}
}
