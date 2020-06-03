<?php

namespace Milanspv\InlineImages;

class Converter
{
	/**
	 * The file path to convert (local or remote path)
	 * @var string
	 */
	private $path;

	/**
	 * Converter constructor
	 * @param string $path The file path to convert (local or http path)
	 */
	public function __construct($path = null)
	{
		$this->path = $path;
	}

	/**
	 * Convert the file to inline value (support any base64 format or raw utf8 format for SVG)
	 * @return string the inline value
	 */
	public function convert()
	{
		$fetcher = new Fetcher($this->path);
		$mime = $fetcher->getMimeType();
		//if image is of type svg, we don't want to base64 it because it will often not work and it is larger
		if (strpos($mime, 'svg') !== false) {
			$content = explode(PHP_EOL, $fetcher->getFileData());
			$output = [];

			foreach ($content as $line) {
				//we dont want to keep <?xml and <!DOCTYPE values from SVG
				if (strpos($line, '<?') === false && strpos($line, '<!') === false) {
					$output[] = str_replace('"', '\'', $line);
				}
			}

			return 'data:'.$mime.';utf-8,'.implode('', $output);
		}

		return 'data:'.$mime.';base64,'.base64_encode($fetcher->getFileData());
	}

	/**
	 * Return the current file path
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * Set the current file path
	 *
	 * @param string $path
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}
}
