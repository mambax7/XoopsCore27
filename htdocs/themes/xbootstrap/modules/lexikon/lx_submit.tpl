<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<{$xoops_url}>"><{$smarty.const._MD_LEXIKON_HOME}></a></li>
        <li class="breadcrumb-item"><a href="<{$xoops_url}>/modules/<{$lang_moduledirname}>/index.php"><{$lang_modulename}></a></li>
        <li class="breadcrumb-item active" aria-current="page"><{$smarty.const._MD_LEXIKON_SUBMITART}></li>
    </ol>
</nav>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4><{$send_def_to}></h4>
            </div>
            <div class="card-body">
                <p><{$smarty.const._MD_LEXIKON_GOODDAY}></p>
                <p><b><{$lx_user_name}></b>, <{$smarty.const._MD_LEXIKON_SUB_SNEWNAMEDESC}></p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-sm-12">
        <{$storyform.javascript}>
        <h3><{$storyform.title}></h3>
        <form id="sub-lex" name="<{$storyform.name}>" action="<{$storyform.action}>" method="<{$storyform.method}>" <{$storyform.extra}>>
            <{foreach item=element from=$storyform.elements|default:null}>
                <{if isset($element.hidden) && $element.hidden == true}>
                    <div class="form-group">
                        <label><{$element.caption|default:''}></label>
                        <{$element.body}>
                    </div>
                <{else}>
                    <{$element.body}>
                <{/if}>
            <{/foreach}>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#sub-lex select').addClass('form-control');
        $('#sub-lex input[type=text]').addClass('form-control');
        $('#sub-lex textarea').addClass('form-control');
        $('#definition_preview_button').addClass('btn btn-info btn-sm');
        $('input[type=submit]').addClass('btn btn-success btn-sm');
    });
</script>
