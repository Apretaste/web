<h1>{$query|capitalize}</h1>

{foreach from=$responses item=response name=google}
    <b>{$smarty.foreach.google.index + 1}) {link href="NAVEGAR {$response['url']}" caption="{$response['title']}"}</b><br/>
	<font style="color: #545454;font-size: small;">{$response['note']}</font><br/>
	<small>
		{link href="NAVEGAR {$response['url']}" caption="Ver como PDF"} {separator}
		{link href="PIZARRA Miren esto: {$response['url']}" caption="Compartir en Pizarra"}
	</small>
	{space15}
{/foreach}