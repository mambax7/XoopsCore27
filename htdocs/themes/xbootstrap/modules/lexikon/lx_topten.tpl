<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<{$xoops_url}>"><{$smarty.const._MD_LEXIKON_HOME}></a></li>
        <li class="breadcrumb-item"><a href="<{$xoops_url}>/modules/<{$lang_moduledirname}>/index.php"><{$lang_modulename}></a></li>
        <li class="breadcrumb-item active" aria-current="page"><{$intro}></li>
    </ol>
</nav>

<div class="text-center mb-4">
    <h3><{$xoops_pagetitle}></h3>
</div>

<{foreach item=ranking from=$rankings|default:null}>
    <div class="card mb-4">
        <div class="card-header">
            <{if isset($multicats) && $multicats == 1}>
                <{$lang_category}>:
            <{/if}>
            <a href="<{$xoops_url}>/modules/<{$lang_moduledirname}>/category.php?categoryID=<{$ranking.cid}>">
                <{$ranking.title}>
            </a>
                (<{$lang_sortby}>)
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
        <tr>
                        <th scope="col" width="7%"><{$lang_rank}></th>
                        <th scope="col" width="53%"><{$lang_term}></th>
                        <th scope="col" width="5%" class="text-center"><{$lang_hits}></th>
                        <th scope="col" width="9%" class="text-center"><{$lang_date}></th>
        </tr>
                    </thead>
                    <tbody>
        <{foreach item=terms from=$ranking.terms|default:null}>
            <tr>
                            <td><{$terms.rank}></td>
                            <td>
                                <a href="<{$xoops_url}>/modules/<{$lang_moduledirname}>/entry.php?entryID=<{$terms.id}>" title="<{$terms.definition}>">
                                    <{$terms.title}>
                                </a>
                </td>
                            <td class="text-center"><{$terms.counter}></td>
                            <td class="text-center"><{$terms.datesub}></td>
            </tr>
        <{/foreach}>
                    </tbody>
    </table>
            </div>
        </div>
    </div>
<{/foreach}>
