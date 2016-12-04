<h1>Sitios webs en Apretaste!</h1>
{space10}
{foreach item=$item from=$sites}
<h3>{link href="WEB http://{$item->domain}.apretaste.com" caption ="{$item->title}"}</h3>
<p>{$item->summary}</p>
<font style="color: #545454;font-size: small;"><small>{$item->owner}</small>{separator}<small>{$item->inserted}</small></font>
{/foreach}
{space10}
<hr/>
<center>{foreach item=p from=$pagging}{link href="WEB PAGINAS $p" caption="{$p}"}&nbsp;{/foreach}</center>