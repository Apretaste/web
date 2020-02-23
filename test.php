<?php

$doc = new DOMDocument();
try {
	@$doc->loadHTML('<html><body><p>bla bla</p></body></html>');
} catch(Exception $e){

}

$tags = $doc->getElementsByTagName('p');

foreach ($tags as $tag){
	$tag->setAttribute('align','center');
}

echo $doc->saveHTML();
