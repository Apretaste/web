<h1>Hemos descargado su website</h1>
<p>Su website <b>{$url}</b> fue descargada y adjunta a este email como PDF.</p>

{if not $images}
	<p>La website adjunta <b>no</b> contiene im&aacute;genes. Env&iacute;e un email con el asunto: "WEB FULL {$url}" si desea recibir la website con im&aacute;genes. Las im&aacute;genes incrementan el tama&ntilde;o del adjunto y <u>pueden consumir masivamante su cr&eacute;dito</u> si usa Nauta.</p>
	<center>
		{button href="WEB FULL {$url}" caption="Ver im&aacute;genes"}
	</center>
{/if}