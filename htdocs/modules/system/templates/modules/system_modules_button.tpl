<div class="btn-group float-<{$position}>" role="group" aria-label="Basic example">
    <{foreach from=$buttons key=i item=btn}>
        <a class="btn btn-secondary" href="<{$btn.link}>" title="<{$btn.title}>">
            <img src="<{$path}><{$btn.icon}>" width="16" alt="<{$btn.title}>" class="icon" title="<{$btn.title}>">
            <{$btn.title}>
        </a>
    <{/foreach}>
</div>
<div class="clearfix pb-2"></div>