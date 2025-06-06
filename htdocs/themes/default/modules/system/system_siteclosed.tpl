<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns="https://www.w3.org/1999/xhtml" xml:lang="<{$xoops_langcode}>" lang="<{$xoops_langcode}>">
<head>
    <!-- title and metas -->
    <title><{if !empty($xoops_pagetitle)}><{$xoops_pagetitle}> : <{/if}><{$xoops_sitename}></title>
    <meta http-equiv="content-type" content="text/html; charset=<{$xoops_charset}>"/>
    <meta name="robots" content="<{$xoops_meta_robots}>"/>
    <meta name="keywords" content="<{$xoops_meta_keywords}>"/>
    <meta name="description" content="<{$xoops_meta_description}>"/>
    <meta name="rating" content="<{$xoops_meta_rating}>"/>
    <meta name="author" content="<{$xoops_meta_author}>"/>
    <meta name="copyright" content="<{$xoops_meta_copyright}>"/>
    <meta name="generator" content="XOOPS"/>
    <{if !empty($url)}>
        <meta http-equiv="Refresh" content="<{$time}>; url=<{$url}>"/>
    <{/if}>

    <!-- path favicon -->
    <link rel="shortcut icon" type="image/ico" href="<{xoImgUrl url='icons/favicon.ico'}>"/>
    <link rel="icon" type="image/png" href="<{xoImgUrl url='icons/favicon.png'}>"/>

    <!-- include xoops.js and others via header.php -->
    <{$xoops_module_header|default:''}>

    <!-- Xoops style sheet -->
    <link rel="stylesheet" type="text/css" media="screen" href="<{xoAppUrl url='xoops.css'}>"/>

    <!-- Theme style sheets -->
    <link rel="stylesheet" type="text/css" media="screen" title="Color" href="<{xoImgUrl url='style.css'}>"/>
</head>
<body>

<div id="xo-canvas"
        <{if !empty($columns_layout)}> class="<{$columns_layout}>"<{/if}>>
    <div class="xo-wrapper">
        <div id="xo-bgstatic" class="<{$xoops_dirname}>"></div>
        <div id="xo-header" class="<{$xoops_dirname}>">
            <div id="xo-top">
                <!-- include the User block in the header -->
            </div>
            <!-- Start Header -->
            <table cellspacing="0">
                <tr id="header">
                    <td id="headerlogo"><a href="<{xoAppUrl url='/'}>" title="<{$xoops_sitename}>"><img src="<{xoImgUrl url='xoops-logo.png'}>"
                                                                                                  alt="<{$xoops_sitename}>"/></a></td>
                    <td id="headerbanner"><{$xoops_banner}></td>
                    <td id="xo-userbar_siteclosed">
                        <!-- menu in anonymous mode  -->
                        <form method="post" action="<{xoAppUrl url='/user.php?op=login'}>">
                            <input name="uname" type="text" title=""/>
                            <input name="pass" type="password" title=""/>
                            <input type="hidden" name="xoops_redirect" value="<{$smarty.server.REQUEST_URI}>"/>
                            <{if isset($lang_siteclosemsg)}>
                                <input type="hidden" name="xoops_login" value="1"/>
                            <{/if}>
                            <input type="hidden" name="op" value="login"/>
                            <input type="submit" value="<{$lang_login}>"/>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td id="headerbar" colspan="3">&nbsp;</td>
                </tr>
            </table>
            <!-- End header -->
        </div>

        <div id="xo-canvas-content">
            <div id="xo-page">
                <div id="xo-siteclose"><{$lang_siteclosemsg}></div>
                <{if !empty($redirect_message)}>
                <div class="center red"><b><{$redirect_message}></b><br><br></div>
                <{/if}>
            </div>
        </div>

        <!-- Start footer -->
        <table cellspacing="0">
            <tr id="footerbar">
                <td><{$xoops_footer}></td>
            </tr>
        </table>
        <!-- End footer -->

        <!--{xo-logger-output}-->
    </div>
</div>

</body>
</html>
