Usage of custom xoopscode

Step 1, build the class for a custom code or an extension, e.g. mycode:
in /class/textsanitizer/mycode/mycode.php (see below)

Step 2, enable the extension in textsanitizer:
in /class/textsanitizer/config.custom.php


mycode.php:
class MytsMycode extends MyTextSanitizerExtension
{
    // The encode function for dhtml editor
    function encode($textarea_id)
    {
        // If the extension has config data, load it
        $config = parent::loadConfig(__DIR__);
        // Make sure that the icon is available /images/form/mycode.gif
        // Arguments crossing from PHP into a JavaScript string literal MUST be json_encode()d
        // with these four flags, NOT htmlspecialchars()d. The browser decodes HTML entities
        // BEFORE the JS parser runs, so an entity-escaped quote becomes a real quote and can
        // close the literal. json_encode emits \uXXXX escapes, which survive that decode.
        //
        // The surrounding onclick attribute MUST be single-quoted: json_encode wraps its own
        // output in double quotes, so onclick="..." would be closed by the first argument and
        // JSON_HEX_QUOT would not help -- it escapes quotes in the VALUE, not the wrapping pair.
        $jsFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
        $args = json_encode((string) $textarea_id, $jsFlags)
              . ', ' . json_encode(_XOOPS_FORM_ENTERMYCODETERM, $jsFlags);
        $code = "<img src='{$this->image_path}/mycode.gif' alt='"
              . htmlspecialchars(_XOOPS_FORM_ALTMYCODE, ENT_QUOTES | ENT_HTML5, 'UTF-8')
              . "' onclick='xoopsCodeMycode({$args});'/>&nbsp;";
        $javascript = <<<EOH
            function xoopsCodeMycode(id, enterMycodePhrase){
                if (enterMycodePhrase == null) {
                    enterMycodePhrase = "Enter the content for the code.";
                }
                var selection = xoopsGetSelect(id);
                if (selection.length > 0) {
                    var text = selection;
                }else {
                    var text = prompt(enterMycodePhrase, "");
                }
                var domobj = xoopsGetElementById(id);
                if ( text != null && text != "" ) {
                    var result = "[mycode]" + text + "[/mycode]";
                    xoopsInsertText(domobj, result);
                }
                domobj.focus();
            }
EOH;
        // Return the scripts to be displayed in editor form and the javascript for relevant actions
        return array($code, $javascript);
    }

    // The code parser
    function load($myts)
    {
        $myts->patterns[] = "/\[mycode\]([^\]]*)\[\/mycode\]/esU";
        $myts->replacements[] = self::class."::decode( '\\1' )";
    }

    // Processing the text
    static function decode($text, $width, $height)
    {
        // Load config data if any
        $config = parent::loadConfig(__DIR__);
        if ( empty($text) || empty($config['enabled']) ) return $text;
        $ret = someFunctionToConvertTheTextToDefinedFormat($text);
        return $ret;
    }
}

config.custom.php:
return $config = array(
        "extensions" => array(
                        "iframe"    => 0,
                        "image"     => 1,
                        "flash"     => 1,
                        "youtube"   => 1,
                        "mp3"       => 1,
                        "wmp"       => 0,
                        "wiki"      => 1,
                        "mms"       => 0,
                        "rtsp"      => 0,
                        "mycode"    => 1,   // Enable the extension
                        ),
    );
