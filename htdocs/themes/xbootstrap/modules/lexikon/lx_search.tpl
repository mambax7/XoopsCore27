<nav aria-label="breadcrumb">
<ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<{$xoops_url}>"><{$smarty.const._MD_LEXIKON_HOME}></a></li>
        <li class="breadcrumb-item"><a href="<{$xoops_url}>/modules/<{$lang_moduledirname}>/index.php"><{$lang_modulename}></a></li>
        <li class="breadcrumb-item active" aria-current="page"><{$smarty.const._MD_LEXIKON_SEARCHHEAD}></li>
</ol>
</nav>

<div class="row">
  <div class="col-md-12">
        <div class="card">
            <div class="card-header">
        <h4><{$smarty.const._MD_LEXIKON_SEARCHHEAD}></h4>
      </div>
            <div class="card-body">
        <p><{$intro}></p>
      </div>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-md-6 col-sm-12">
    <h3><{$smarty.const._MD_LEXIKON_WEHAVE}></h3>
        <p>
    <{$smarty.const._MD_LEXIKON_DEFS}><{$publishedwords}><br>
            <{if isset($multicats) && $multicats == 1}>
                <{$smarty.const._MD_LEXIKON_CATS}> <{$totalcats}><br>
            <{/if}>
        </p>
        <button class="btn btn-success btn-sm mb-2" onclick="location.href = 'submit.php'"><{$smarty.const._MD_LEXIKON_SUBMITENTRY}></button>
        <button class="btn btn-info btn-sm" onclick="location.href = 'request.php'"><{$smarty.const._MD_LEXIKON_REQUESTDEF}></button>
  </div>
    <div class="col-md-6 col-sm-12">
        <hr class="d-sm-none">
    <h3><{$smarty.const._MD_LEXIKON_SEARCHENTRY}></h3>
    <{$searchform}>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <{foreach item=eachresult from=$resultset.match|default:null}>
            <div class="mb-4">
                <h4>
                    <img src="<{$xoops_url}>/modules/<{$eachresult.dir}>/assets/images/lx.png" alt="" class="mr-1">
          <a href="<{$xoops_url}>/modules/<{$eachresult.dir}>/entry.php?entryID=<{$eachresult.id}><{if isset($highlight) && $highlight == 1}><{$eachresult.keywords}><{/if}>">
            <{$eachresult.term}>
          </a>
          <{if isset($multicats) && $multicats == 1}>
                        <a href="<{$xoops_url}>/modules/<{$eachresult.dir}>/category.php?categoryID=<{$eachresult.categoryID}>" class="badge badge-secondary ml-2">
                            <{$eachresult.catname}>
            </a>
          <{/if}>
        </h4>
        <p><{$eachresult.definition}></p>
        <{if $eachresult.ref}>
                    <p class="text-muted"><{$eachresult.ref}></p>
        <{/if}>
            </div>
    <{/foreach}>
    <div><{$resultset.navbar}></div>
  </div>
</div>

<script>
    $(document).ready(function() {
        $('select').addClass('form-control').css('margin-bottom', '5px');
        $('input[type=text]').addClass('form-control');
$( "input[name*='term']" ).css("background-position","1px 8px");
$('.btnDefault').addClass( "btn btn-success btn-sm" );
    });
</script>
