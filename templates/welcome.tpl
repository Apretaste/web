<h1>Bienvenido a navegar en Internet</h1>
<p align="justify">Este servicio te permite <b>buscar y visitar direcciones en Internet mediante el email y publicar webs est&aacute;ticas</b>, navegando de correo en correo. 
Haga clic en los siguientes ejemplos para probarlos.</p>

<center>
	{button href="WEB http://revolico.com/computadoras" caption="Visitar sitio web" size="small"} 
	{button href="WEB PDF http://revolico.com/computadoras" caption="Sitio como PDF" size="small"}
	{button href="WEB cuba" caption="Buscar en Internet" size="small"}
</center>
{space5}
<center>
	{button href="WEB ftp://ftp.4d.com/" caption="Servidor de archivos FTP" size="small"}
	{button href="WEB NOTICIAS cuba" caption="Buscar Noticias" size="small"}
	{button href="WEB http://feeds.bbci.co.uk/news/england/rss.xml" caption="Canal de Noticias" size="small"}
</center>
{space5}
<center>
	{button href="WEB MOVIL https://m.facebook.com" caption="Navegar como m&oacute;vil" size="small"}
	{button href="WEB http://goes.gsfc.nasa.gov/goescolor/goeseast/hurricane2/color_med/latest.jpg" caption="Ver im&aacute;genes de la web" size="small"}
	{button href="WEB http://ftp.drupal.org/files/projects/gratis-7.x-1.0.tar.gz" caption="Descargar archivo" size="small"}
</center>
 
{if $sites !== false}
{space10}
	<h1>Sitios publicados en Apretaste</h1>
	{foreach item=item from=$sites}
		<li>{link href="WEB {$item->domain}.apretaste.com" caption="{$item->title}"}</li>
	{/foreach}
	{space10}
	<center>
	{button href="WEB PAGINAS" caption="Explorar sitios" color="blue"}
	</center>
{/if}
{space5}

{if $visits !== false}
	<h2>Sitios m&aacute;s visitados</h2>
	<p>Estos son los sitios m&aacute;s visitados por los usuarios de este servicio:</p>
	<ul>
	{foreach item=item from=$visits}
		<li>{link href="WEB {$item->site}" caption="{$item->site}"}</li>
	{/foreach}
	</ul>
{/if}
{space5}
<h1>C&oacute;mo publicar un sitio web en Apretaste?</h1>
<p>Escribe un correo y en el asunto pon la palabra WEB PUBLICAR seguida de una palabra que identifica tu sitio. 
Luego adjunta los archivos que componen tu sitio. Por ejemplo:</p>

Para: <b>{apretaste_email}</b><br/>
Asunto: <b>WEB PUBLICAR misitio</b><br/>
Ajuntos: <b>index.html, imagen.jpg, style.css</b><br/>

<p>El adjunto index.html es requerido y si no se env&iacute;a se crear&aacute; uno por defecto. Tu sitio web quedar&aacute; publicado en la direcci&oacute;n:
http://<b>misitio</b>.apretaste.com. 
</ul>

<center>{button href="WEB PUBLICAR misitio" caption="Probar publicar"}</center>
{space10}
<center>{link href="WEB terminos.apretaste.com" caption="T&eacute;rminos de Uso"} | 
{link href="WEB credito.apretaste.com" caption="C&oacute;mo obtener cr&eacute;dito"}</center>