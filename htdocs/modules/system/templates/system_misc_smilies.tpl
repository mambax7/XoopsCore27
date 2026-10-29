<{* smilies selector popup *}>
<{if isset($closeHead) && $closeHead == true}>
<{$headContents|default:''}>
<script>window.resizeTo(300, 475)</script>
</head>
<body>
<{/if}>
<div class="pad5">
<table width="100%" class="outer">
    <tr><th colspan="3"><{$lang_smiles}></th></tr>
    <tr class="head"><td><{$lang_code}></td><td><{$lang_emotion}></td><td><{$lang_image}></td></tr>
    <{foreach item=smile from=$smilies|default:null}>
    <tr><td><{$smile.code|escape}></td><td><{$smile.emotion|escape}></td><td><img onmouseover="style.cursor='hand'" data-code="<{$smile.code|escape}>" onclick="doSmilie(' ' + this.getAttribute('data-code') + ' ');" src="<{$upload_url}><{$smile.smile_url|escape}>" alt="<{$smile.emotion|escape}>" title="<{$smile.emotion|escape}>" /></td></tr>
    <{/foreach}>
</table>
</div>
<p><{$lang_clicksmile}></p>
<{if isset($closeButton) && $closeButton == true}>
    <div style="text-align:center;"><input class="formButton" value="<{$lang_close}>" type="button" onclick="window.close();" /></div>
<{/if}>
