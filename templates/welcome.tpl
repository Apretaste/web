<h1>Bienvenido a navegar en Internet</h1>
<p align="justify">Este servicio te permite <b>buscar y visitar direcciones en Internet mediante el email y publicar webs est&aacute;ticas</b>, navegando de correo en correo. 
Haga clic en los siguientes ejemplos para probarlos.</p>

{button icon="&#9729;" size="small" href="WEB http://revolico.com/computadoras" caption="Visitar sitio web" style="font-size:15px;margin-bottom:5px;padding:5px;"}
{button icon="&#8473;" size="small" href="WEB PDF http://revolico.com/computadoras" caption="Sitio como PDF" style="font-size:15px;margin-bottom:5px;padding:5px;"}
{button icon="&#8494;" size="small" href="WEB cuba" caption="Buscar en Internet" style="font-size:15px;margin-bottom:5px;padding: 5px;"}
{button icon="&#9783;" size="small" href="WEB ftp://ftp.4d.com/" caption="Servidor de archivos"  style="font-size:15px;margin-bottom:5px;padding:5px;"}
{button icon="&#9741;" size="small" href="WEB NOTICIAS cuba" caption="Buscar Noticias"  style="font-size:15px;margin-bottom:5px;padding:5px;"}
{button icon="&#9732;" size="small" href="WEB http://feeds.bbci.co.uk/news/england/rss.xml" caption="Canal de Noticias"  style="font-size:15px;margin-bottom:5px;padding:5px;"}
{button icon="&#9784;" size="small" href="WEB MOVIL https://m.facebook.com" caption="Navegar como m&oacute;vil" style="font-size:15px;margin-bottom:5px;padding:5px;"}
{button icon="&#10064;" size="small" href="WEB http://goes.gsfc.nasa.gov/goescolor/goeseast/hurricane2/color_med/latest.jpg" caption="Im&aacute;genes de la web" style="font-size:15px;margin-bottom:5px;padding:5px;"}
{button icon="&#9729;" size="small" href="WEB http://ftp.drupal.org/files/projects/gratis-7.x-1.0.tar.gz" caption="Descargar archivo" style="font-size:15px;margin-bottom:5px;padding:5px;"}

{if $sites !== false}
{space10}
	<h1>Sitios m&aacute;s populares publicados en Apretaste</h1>
	{foreach item=item from=$sites}
		<li>{link href="WEB {$item->domain}.apretaste.com" caption="{$item->title}"}</li>
	{/foreach}
	{space10}
	<center>
	{button href="WEB PAGINAS" caption="M&aacute;s sitios" color="green"}
	</center>
{/if}
{space5}

{if $visits !== false}
	<h1>Sitios m&aacute;s visitados</h1>
	<p>Estos son los sitios m&aacute;s visitados por los usuarios de este servicio:</p>
	<ul>
	{foreach item=item from=$visits}
		<li>{link href="WEB {$item->site}" caption="{$item->site}"}</li>
	{/foreach}
	</ul>
{/if}
{space5}
<h1>C&oacute;mo publicar un sitio web en Apretaste?</h1>
<p align="justify">Escribe un correo a Apretaste y en el asunto pon la palabra WEB PUBLICAR seguida
de una palabra que identifica tu sitio y luego un t&iacute;tulo que quieras darle.
Luego adjunta los archivos que componen tu sitio. Por ejemplo:</p>

Para: <b>{apretaste_email}</b><br/>
Asunto: <b>WEB PUBLICAR misitio Mi primer sitio</b><br/>
Ajuntos: <b>index.html, imagen.jpg, style.css</b><br/>

<p align="justify">El adjunto index.html es requerido y si no se env&iacute;a se crear&aacute; uno por defecto. Tu sitio web quedar&aacute; publicado en la direcci&oacute;n:
http://<b>misitio</b>.apretaste.com. 
</ul>

<center>{button href="WEB PUBLICAR misitio" caption="Probar publicar"}</center>
{space10}
<center>{link href="WEB terminos.apretaste.com" caption="T&eacute;rminos de Uso"} | 
{link href="WEB credito.apretaste.com" caption="C&oacute;mo obtener cr&eacute;dito"}</center>