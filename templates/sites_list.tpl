<h1>Web publicados en Apretaste</h1>

{space10}

{foreach item=$item from=$sites}
	<h3 style="margin-bottom:5px;">{link href="WEB http://{$item->domain}.apretaste.com" caption="{$item->title}"}</h3>
	{$item->summary|strip}<br/>
	<font style="color: #545454;font-size: small;"><small>Publicado por {$item->owner} el {$item->inserted|date_format:"%e de %B del %Y"}</small></font>
{/foreach}

{space10}

<hr/>

<center>{foreach item=p from=$pagging}{link href="WEB PAGINAS $p" caption="{$p}"}&nbsp;{/foreach}</center>
