<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<{$xoops_charset}>"/>
    <meta http-equiv="Refresh" content="<{$time}>; url=<{$url}>"/>
    <meta name="generator" content="XOOPS"/>
    <link rel="shortcut icon" type="image/ico" href="<{xoAppUrl url='favicon.ico'}>"/>
    <title><{$xoops_sitename}></title>
    <link rel="stylesheet" type="text/css" media="all" href="<{$xoops_themecss}>"/>
</head>
<body>
<div class="center bold" style="background-color: #ebebeb; border: 1px solid #fff;border-right-color: #aaa;border-bottom-color: #aaa;">
    <h4><{$message}></h4>

    <p><{$lang_ifnotreload}></p>
</div>
<{if !empty($xoops_logdump)}>
    <div><{$xoops_logdump}></div>
<{/if}>
</body>
</html>
