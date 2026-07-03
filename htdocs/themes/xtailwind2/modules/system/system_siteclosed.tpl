<!doctype html>
<html class="no-js" lang="<{$xoops_langcode}>" dir="<{$xoops_text_direction|default:'ltr'}>" data-theme="atelier">
<head>
    <meta charset="<{$xoops_charset}>">
    <meta name="keywords" content="<{$xoops_meta_keywords}>">
    <meta name="description" content="<{$xoops_meta_description}>">
    <meta name="robots" content="<{$xoops_meta_robots}>">
    <meta name="generator" content="XOOPS">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            var stored = null;
            try { stored = localStorage.getItem('xtailwind2-theme'); } catch (e) {}
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = stored || (prefersDark ? 'noctis' : 'atelier');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link href="<{$xoops_url}>/favicon.ico" rel="shortcut icon">
    <link rel="stylesheet" type="text/css" href="<{$xoops_imageurl}>css/styles.css">
    <link rel="stylesheet" type="text/css" media="all" href="<{$xoops_themecss}>">
    <script src="<{$xoops_url}>/browse.php?Frameworks/jquery/jquery.js"></script>
    <link rel="alternate" type="application/rss+xml" title="" href="<{xoAppUrl 'backend.php'}>">
    <title><{if $xoops_dirname == "system"}><{$xoops_sitename}><{if !empty($xoops_pagetitle)}> - <{$xoops_pagetitle}><{/if}><{else}><{if !empty($xoops_pagetitle)}><{$xoops_pagetitle}> - <{$xoops_sitename}><{/if}><{/if}></title>
    <{$xoops_module_header|default:''}>
    <link rel="stylesheet" type="text/css" href="<{$xoops_imageurl}>css/dark-mode.css">
</head>

<body id="siteclosed" class="min-h-screen bg-base-100 text-base-content antialiased">
<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute left-[-12rem] top-[-10rem] h-[28rem] w-[28rem] rounded-full bg-primary/12 blur-3xl"></div>
    <div class="absolute right-[-8rem] bottom-[-10rem] h-[24rem] w-[24rem] rounded-full bg-secondary/12 blur-3xl"></div>
</div>

<main class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="card w-full max-w-md border border-base-300 bg-base-200/60 shadow-xl backdrop-blur">
        <div class="card-body gap-5">
            <h1 class="text-center text-xl font-semibold"><{$xoops_sitename|escape:'html':'UTF-8'}></h1>

            <div class="alert alert-warning text-sm">
                <span><{$lang_siteclosemsg}></span>
            </div>

            <form action="<{xoAppUrl 'user.php'}>" method="post" class="space-y-4">
                <label class="form-control w-full">
                    <span class="label-text"><{$lang_username}></span>
                    <input type="text" name="uname" class="input input-bordered w-full" placeholder="<{$lang_username}>">
                </label>

                <label class="form-control w-full">
                    <span class="label-text"><{$lang_password}></span>
                    <input type="password" name="pass" class="input input-bordered w-full" placeholder="<{$lang_password}>">
                </label>

                <input type="hidden" name="xoops_redirect" value="<{$xoops_requesturi}>">
                <input type="hidden" name="xoops_login" value="1">

                <div class="pt-2">
                    <button type="submit" class="btn btn-primary w-full"><{$lang_login}></button>
                </div>
            </form>
        </div>
    </div>
</main>
</body>
</html>
