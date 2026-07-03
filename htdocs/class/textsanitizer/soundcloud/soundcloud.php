<?php

/**
 * Class MytsSoundcloud
 */
class MytsSoundcloud extends MyTextSanitizerExtension
{
    /**
     * @param $textarea_id
     *
     * @return array
     */
    public function encode($textarea_id)
    {
        //        $config = parent::loadConfig(__DIR__);

        $code = "<button type='button' class='btn btn-default btn-sm' onclick='xoopsCodeSoundCloud(\"{$textarea_id}\",\""
            . htmlspecialchars(_XOOPS_FORM_ENTER_SOUNDCLOUD_URL, ENT_QUOTES | ENT_HTML5)
            . "\");' onmouseover='style.cursor=\"hand\"' title='" . _XOOPS_FORM_ALT_SOUNDCLOUD
            . "'><span class='fa-brands fa-soundcloud' aria-hidden='true'></span></button>";
        $javascript = <<<EOH
            function xoopsCodeSoundCloud(id, enterSoundCloud)
            {
                var selection = xoopsGetSelect(id);
                if (selection.length > 0) {
                    var text = selection;
                } else {
                    var text = prompt(enterSoundCloud, "");
                }

                var domobj = xoopsGetElementById(id);
                if (text.length > 0) {
                xoopsInsertText(domobj, "[soundcloud]"+text+"[/soundcloud]");
                }
                domobj.focus();
            }
EOH;

        return [$code, $javascript];
    }

    /**
     * @param MyTextSanitizer $myts
     */
    public function load(MyTextSanitizer $myts)
    {
        $myts->callbackPatterns[] = "/\[soundcloud\](http[s]?:\/\/[^\"'<>]*)(.*)\[\/soundcloud\]/sU";
        $myts->callbacks[]        = self::class . '::myCallback';
    }

    /**
     * @param $match
     *
     * @return string
     */
    public static function myCallback($match)
    {
        $url    = $match[1] . $match[2];
        $config = parent::loadConfig(__DIR__);
        if (!preg_match("/^http[s]?:\/\/(www\.)?soundcloud\.com\/(.*)/i", $url, $matches)) {
            trigger_error("Not matched: {$url}", E_USER_WARNING);

            return '';
        }

        // $url has already been constrained to a soundcloud.com URL above.
        // Emit the modern HTML5 iframe player (the Flash player is retired) and
        // escape every interpolated value: rawurlencode() for the query
        // parameter, htmlspecialchars() for the HTML attribute/link contexts.
        $playerSrc     = 'https://w.soundcloud.com/player/?url=' . rawurlencode($url) . '&auto_play=false&show_comments=false';
        $safePlayerSrc = htmlspecialchars($playerSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl       = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $code = '<iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay" src="' . $safePlayerSrc . '"></iframe>'
              . '<a href="' . $safeUrl . '" rel="external noopener nofollow">' . $safeUrl . '</a>';

        return $code;
    }

    //   public static function decode($url1, $url2)
    //   {
    //   }
}
