<nav aria-label="breadcrumb">
<ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<{$xoops_url}>"><{$smarty.const._MD_LEXIKON_HOME}></a></li>
        <li class="breadcrumb-item"><a href="<{$xoops_url}>/modules/<{$lang_moduledirname}>/index.php"><{$lang_modulename}></a></li>
        <li class="breadcrumb-item active" aria-current="page"><{$smarty.const._MD_LEXIKON_ASKFORDEF}></li>
</ol>
</nav>

<div class="row">
  <div class="col-md-12">
        <div class="card">
            <div class="card-header">
        <h4><{$smarty.const._MD_LEXIKON_ASKFORDEF}></h4>
      </div>
            <div class="card-body">
        <p><{$smarty.const._MD_LEXIKON_INTROREQUEST}></p>
      </div>
    </div>
  </div>
</div>

<div class="row" >
  <div class="col-md-6 col-sm-12">
    <{$requestform.javascript}>
    <h3><{$requestform.title}></h3>
    <form id="sub-lex" name="<{$requestform.name}>" action="<{$requestform.action}>" method="<{$requestform.method}>" <{$requestform.extra}>>
        <{foreach item=element from=$requestform.elements|default:null}>
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
        $('#sub-lex input[type=text]').addClass('form-control');
        $('input[type=submit]').addClass('btn btn-success btn-sm');
});
</script>
