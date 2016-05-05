<?php

class Web extends Service
{
	/**
	 * Function executed when the service is called. Do not get images by default
	 *
	 * @author salvipascual
	 * @param Request
	 * @return Response
	 * */
	public function _main(Request $request)
	{
		return $this->createPDFanfgetResponse($request->query, false);
	}


	/**
	 * Get the response with images
	 * 
 	 * @author salvipascual
 	 * @param Request
	 * @return Response
	 * */
	public function _full(Request $request)
	{
		return $this->createPDFanfgetResponse($request->query, true);
	}


	/**
	 * Does all the dirty job and returns the Response object
	 * 
	 * @author salvipascual
	 * @param String $url
	 * @param Boolean $images: true to include images on the attached PDF
	 * */
	private function createPDFanfgetResponse($url, $images=false)
	{
		// do not allow empty pages
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
		$wwwroot = $di->get('path')['root'];

		// download the website as pdf
		$file = "$wwwroot/temp/" . $this->utils->generateRandomHash() . ".pdf";
		$showimage = $images ? "--images" : "--no-images";
		shell_exec("/usr/local/bin/wkhtmltopdf -lq --no-background $showimage --disable-external-links --disable-forms --disable-javascript --viewport-size 1600x900 $url $file");

		// error if the web could not be downloaded
		if( ! file_exists($file))
		{
			$response = new Response();
			$response->setResponseSubject("Error descargando su website");
			$response->createFromText("Tuvimos un error descargando la website <b>$url</b>. Por favor intente nuevamente en algunos minutos.");
			return $response;
		}

		// respond to the user with the pdf of website attached
		$content = array("url"=>$url, "images"=>$images);
		$response = new Response();
		$response->setResponseSubject("Aqui esta su website");
		$response->createFromTemplate("basic.tpl", $content, array(), array($file));
		return $response;
	}
}
