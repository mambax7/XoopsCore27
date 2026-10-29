<{* smilies selector popup *}>
<{if isset($closeHead) && $closeHead == true}>
<{$headContents|default:''}>
<script>window.resizeTo(400, 580)</script>
</head>
<body>
<{/if}>

<h4 class="text-center"><{$lang_smiles}></h4>

<{if isset($closeButton) && $closeButton == true}>
    <div class="text-center m-3"><input class="btn btn-primary w-100" value="<{$lang_close}>" type="button" onclick="window.close();" /></div>
<{/if}>

<table class="table table-hover table-sm">
    <thead class="thead-dark">
        <tr>
            <th class="text-center" scope="col"><{$lang_code}></th>
            <th class="text-center" scope="col"><{$lang_emotion}></th>
            <th class="text-center" scope="col"><{$lang_image}></th>
        </tr>
    </thead>
    <tbody>
        <{foreach item=smile from=$smilies|default:null}>
            <tr>
                <td class="text-center"><{$smile.code|escape}></td>
                <td class="text-center"><{$smile.emotion|escape}></td>
                <td class="text-center">
                    <img onmouseover="style.cursor='hand'" data-code="<{$smile.code|escape}>" onclick="doSmilie(' ' + this.getAttribute('data-code') + ' ');" src="<{$upload_url}><{$smile.smile_url|escape}>" alt="<{$smile.emotion|escape}>" title="<{$smile.emotion|escape}>" />
                </td>
            </tr>
        <{/foreach}>
    </tbody>
</table>

<p class="text-center mx-2"><{$lang_clicksmile}></p>

<{if isset($closeButton) && $closeButton == true}>
    <div class="text-center m-3"><input class="btn btn-primary w-100" value="<{$lang_close}>" type="button" onclick="window.close();" /></div>
<{/if}>
