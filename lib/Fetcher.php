<?php
namespace Milanspv\InlineImages;

class Fetcher {
	private $path;
	private $data = NULL;
	private $mime = NULL;

	/**
	 * Take a remote path for a file to be fetched
	 * @param string $path a file path
	 */
	function __construct($path) {
		$this->path = $path;

		if (filter_var($this->path, FILTER_VALIDATE_URL)) {
			//if file path is an URL, we want to download it
			$this->fetchRemote();
		} else if (file_exists($this->path)) {
			//if file is stored locally, we directly uses it
			$this->fetchLocal();
		} else {
			throw new \Exception('Invalid Path/URL provided', 1);
		}
	}

	/**
	 * Fetches a remote file and grab its mime/content using curl
	 * @return void
	 */
	private function fetchRemote() {
		$curl = curl_init($this->path);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$this->data = curl_exec($curl);
		$this->mime = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

		curl_close($curl);
	}

	/**
	 * Fetch a local file and grab its mime type and file content
	 * @return void
	 */
	private function fetchLocal() {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$this->mime = finfo_file($finfo, $this->path);
		finfo_close($finfo);
		$this->data = file_get_contents($this->path, FILE_USE_INCLUDE_PATH);
	}

	/**
	 * Return the current file mime type
	 * @return string the file mime type
	 */
	function getMimeType() {
		return $this->mime;
	}

	/**
	 * Return the current file content
	 * @return string the file content
	 */
	function getFileData() {
		return $this->data;
	}
}
