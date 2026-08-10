<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/language/english/logger.php';
require_once XOOPS_ROOT_PATH . '/class/logger/xoopslogger.php';
require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsload.php';

class MyTextSanitizerTest extends TestCase
{
    protected MyTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new \MyTextSanitizer(); // Initialize your class
    }

    public function testEmailConversion()
    {
        $input    = "Contact us at info@example.com for more information.";
        $expected = 'Contact us at <a href="mailto:info@example.com">info@example.com</a> for more information.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testHttpUrlConversion()
    {
        $input    = "Visit our website at http://www.example.com.";
        $expected = 'Visit our website at <a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a>.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testFtpUrlConversion()
    {
        $input    = "Our ftp is ftp://ftp.example.com.";
        $expected = 'Our ftp is <a href="ftp://ftp.example.com" target="_blank" rel="external">ftp://ftp.example.com</a>.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testEmptyString()
    {
        $this->assertEquals('', $this->sanitizer->makeClickable(''));
    }

    public function testStringWithoutUrlsOrEmails()
    {
        $this->assertEquals('Hello World', $this->sanitizer->makeClickable('Hello World'));
    }

    public function testMultipleUrlsAndEmails()
    {
        $input    = "Visit us at http://www.example.com or https://secure.example.com. Contact us at info@example.com.";
        $expected = 'Visit us at <a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a> or <a href="https://secure.example.com" target="_blank" rel="external noopener nofollow">https://secure.example.com</a>. Contact us at <a href="mailto:info@example.com">info@example.com</a>.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testInvalidUrlsAndEmails()
    {
        $input    = "Visit us at http:/www.example.com. Contact us at info@.com.";
        $expected = 'Visit us at http:/www.example.com. Contact us at info@.com.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testMultiLineText()
    {
        $text     = "Check this link:<br>http://example.com\nand this email:<br />test@example.com";
        $expected = 'Check this link:<br><a href="http://example.com" target="_blank" rel="external noopener nofollow">http://example.com</a> and this email:<br /><a href="mailto:test@example.com">test@example.com</a>';
        $result   = $this->sanitizer->makeClickable($text);
        $this->assertEquals($expected, $result);
    }

    public function testMultiLineText2()
    {
        $text     = "Check this link:<br>http://example.com\nand this email:<br />test@example.com";
        $expected = 'Check this link:<br><a href="http://example.com" target="_blank" rel="external noopener nofollow">http://example.com</a> and this email:<br /><a href="mailto:test@example.com">test@example.com</a>';
        $result   = $this->sanitizer->makeClickable($text);
        $this->assertEquals($expected, $result);
    }

    public function testUrlsEndingWithPunctuation()
    {
        $input    = "Visit our website at http://www.example.com. It's great!";
        $expected = 'Visit our website at <a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a>. It\'s great!';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testVariousUrls()
    {
        $testCases = [
            "Visit https://www.example.com for more info"                                      => 'Visit <a href="https://www.example.com" target="_blank" rel="external noopener nofollow">https://www.example.com</a> for more info',
            "Check out www.example.org"                                                        => 'Check out <a href="http://www.example.org" target="_blank" rel="external noopener nofollow">http://www.example.org</a>',
            "Email me at test@example.net"                                                     => 'Email me at <a href="mailto:test@example.net">test@example.net</a>',
            "FTP link: ftp://ftp.example.com/files"                                            => 'FTP link: <a href="ftp://ftp.example.com/files" target="_blank" rel="external">ftp://ftp.example.com/files</a>',
            "This is some text with a link on the next line:<br />https://www.another-example.com" => 'This is some text with a link on the next line:<br /><a href="https://www.another-example.com" target="_blank" rel="external noopener nofollow">https://www.another-example.com</a>',
            "This is some text with a link on the next line:<br>https://www.another-example.com" => 'This is some text with a link on the next line:<br><a href="https://www.another-example.com" target="_blank" rel="external noopener nofollow">https://www.another-example.com</a>',
        ];

        foreach ($testCases as $input => $expected) {
            $output = $this->sanitizer->makeClickable($input);
            $this->assertEquals($expected, $output);
        }
    }

    public function testUrlsWithParentheses()
    {
        $input    = "Visit our website (http://www.example.com) for more info.";
        $expected = 'Visit our website (<a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a>) for more info.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testUrlsWithBrackets()
    {
        $input    = "Check out this link [http://www.example.com].";
        $expected = 'Check out this link [<a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a>].';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testUrlsWithAngularBrackets()
    {
        $input    = "Visit <http://www.example.com> for more info.";
        $expected = 'Visit <<a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a>> for more info.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }


    public function testUrlsWithAngularBrackets2()
    {
        $input    = "Visit <http://www.example.com> for more info.";
        $expected = 'Visit <<a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a>> for more info.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testUrlsWithAngularBrackets3()
    {
        $input    = "Visit <http://www.example.com> for more info.";
        $expected = 'Visit <<a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a>> for more info.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testUrlsWithoutProtocol()
    {
        $input    = "Visit www.example.com for more info.";
        $expected = 'Visit <a href="http://www.example.com" target="_blank" rel="external noopener nofollow">http://www.example.com</a> for more info.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testSftpUrlConversion()
    {
        $input    = "Our sftp is sftp://sftp.example.com.";
        $expected = 'Our sftp is <a href="sftp://sftp.example.com" target="_blank" rel="external">sftp://sftp.example.com</a>.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testEmailAddressWithPlusSign()
    {
        $input    = "Contact us at john+doe@example.com for more information.";
        $expected = 'Contact us at <a href="mailto:john+doe@example.com">john+doe@example.com</a> for more information.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testUrlWithTildeCharacter()
    {
        $input    = "Visit https://www.example.com/~user for more info.";
        $expected = 'Visit <a href="https://www.example.com/~user" target="_blank" rel="external noopener nofollow">https://www.example.com/~user</a> for more info.';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testMakeClickableMultiLine3()
    {
        $text     = "No elitr elit quis nobis soluta cum sanctus fugiat dolor liber facer, sint exercitation kasd et nonumy assum commodi laboris culpa, commodo diam labore nisl illum consectetur nihil elitr invidunt non tempor. Invidunt facilisi soluta nisi te anim soluta labore, cillum elitr quis tempor congue vel liber est aliquyam cupiditat obcaecat tempor obcaecat sint no elit. Nostrud dignissim aliquid. https://www.monxoops.fr/modules/newbb/viewtopic.php?topic_id=139&post_id=1440#forumpost1440 and this email: test@example.com Vel ipsum eiusmod. Kasd accusam nisi.";
        $expected = 'No elitr elit quis nobis soluta cum sanctus fugiat dolor liber facer, sint exercitation kasd et nonumy assum commodi laboris culpa, commodo diam labore nisl illum consectetur nihil elitr invidunt non tempor. Invidunt facilisi soluta nisi te anim soluta labore, cillum elitr quis tempor congue vel liber est aliquyam cupiditat obcaecat tempor obcaecat sint no elit. Nostrud dignissim aliquid. ' .
                    '<a href="https://www.monxoops.fr/modules/newbb/viewtopic.php?topic_id=139&amp;post_id=1440#forumpost1440" target="_blank" rel="external noopener nofollow">https://www.monxoops.fr/modules/newbb/viewtopic.php?topic_id=139&amp;post_id=1440#forumpost1440</a>' .
                    ' and this email: ' . '<a href="mailto:test@example.com">test@example.com</a> ' . 'Vel ipsum eiusmod. Kasd accusam nisi.';

        $result = $this->sanitizer->makeClickable($text);

        $this->assertEquals($expected, $result);
    }

    public function testNewLine0()
    {
        $input    = '<span class="fas fa-bug mx-2 text-warning"></span> block id</button></h2>
<div id="accordion-blockid"';
        $expected = '<span class="fas fa-bug mx-2 text-warning"></span> block id</button></h2> <div id="accordion-blockid"';
        $this->assertEquals($expected, $this->sanitizer->makeClickable($input));
    }

    public function testNewLine()
    {
        $text     = '<span class="fas fa-bug mx-2 text-warning"></span> block id</button></h2>
<div id="accordion-blockid"';
        $expected = '<span class="fas fa-bug mx-2 text-warning"></span> block id</button></h2> <div id="accordion-blockid"';
        $result   = $this->sanitizer->makeClickable($text);
        $this->assertEquals($expected, $result);
    }

    public function testFilePathsAndCustomProtocol()
    {
        $testCases = [
            // Test for file paths
            "Check this file path: file:///usr/local/bin" => 'Check this file path: <a href="file:///usr/local/bin" target="_blank" rel="external">file:///usr/local/bin</a>',

            // Test for custom protocol
            "Use the custom protocol: custom://myapp/resource" => 'Use the custom protocol: <a href="custom://myapp/resource" target="_blank" rel="external">custom://myapp/resource</a>',
        ];

        foreach ($testCases as $input => $expected) {
            $output = $this->sanitizer->makeClickable($input);
            $this->assertEquals($expected, $output);
        }
    }

    public function testInvalidUrls()
    {
        $testCases = [
            // Prevent javascript URLs
            "Don't click this: javascript:alert('XSS')" => "Don't click this: javascript:alert('XSS')",

            // Disallow unsupported protocols
            "Unsupported protocol: gopher://example.com" => "Unsupported protocol: gopher://example.com",
        ];

        foreach ($testCases as $input => $expected) {
            $output = $this->sanitizer->makeClickable($input);
            $this->assertEquals($expected, $output);
        }
    }

    // ---------------------------------------------------------------------
    // Output-encoding regression guards (finding C-1).
    //
    // displayTarea() encodes untrusted textarea content for safe display and
    // then calls makeClickable() to linkify it. makeClickable() must keep that
    // encoding intact for the surrounding text and only round-trip the URL/email
    // tokens it linkifies, so encoded markup never becomes live markup again.
    // ---------------------------------------------------------------------

    public function testDisplayTareaWithHtmlDisabledKeepsMarkupEncoded()
    {
        // Snapshot first, and pop only what actually moved.
        //
        // displayTarea()'s legacy path reaches XoopsLogger::getInstance(), which installs
        // an error and an exception handler and never restores them -- but only on its
        // FIRST call in the process. `static $instance` means every later call returns the
        // cached logger and installs nothing.
        //
        // So whether this test has anything to pop depends on what ran before it. Run this
        // file alone and it does; run the full suite and something earlier has already
        // built the logger, so an unconditional restore_*_handler() pair pops frames
        // belonging to PHPUnit instead -- which PHPUnit reports as "removed error handlers
        // other than its own", and failOnRisky would turn red. That is what the previous
        // version of this teardown did, and it passed in isolation, which is why it stood.
        //
        // set_*_handler(null) followed by restore_*_handler() is the only way to read the
        // current handler; the pair is net zero frames. Bounded, because a runaway loop in
        // a test is a hung build rather than a failed one.
        $errorBefore = set_error_handler(null);
        restore_error_handler();
        $exceptionBefore = set_exception_handler(null);
        restore_exception_handler();

        // finally, so a throw out of displayTarea() cannot skip the cleanup and leak the
        // logger's handlers into every later test in the process.
        try {
            $out = $this->sanitizer->displayTarea('<script>alert(1)</script> visit https://xoops.org', 0);
        } finally {
            for ($i = 0; $i < 16; ++$i) {
                $current = set_error_handler(null);
                restore_error_handler();
                if ($current === $errorBefore) {
                    break;
                }
                restore_error_handler();
            }
            for ($i = 0; $i < 16; ++$i) {
                $current = set_exception_handler(null);
                restore_exception_handler();
                if ($current === $exceptionBefore) {
                    break;
                }
                restore_exception_handler();
            }
        }

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('href="https://xoops.org', $out);
    }

    public function testMakeClickableLeavesEncodedMarkupEncoded()
    {
        $out = $this->sanitizer->makeClickable('&lt;img src=x onerror=alert(1)&gt;');

        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('&lt;img', $out);
    }

    public function testMakeClickableDoesNotDoubleEncodeAmpersandInUrl()
    {
        // A URL arriving already-encoded (as it would after displayTarea) must
        // render with a single &amp;, not &amp;amp;.
        $out = $this->sanitizer->makeClickable('see https://example.com/?a=1&amp;b=2 here');

        $this->assertStringContainsString('https://example.com/?a=1&amp;b=2', $out);
        $this->assertStringNotContainsString('&amp;amp;', $out);
    }


//    public function testNestedTagsAndIncompleteTags()
//    {
//        $testCases = [
//            // Nested tags
//            "Nested link: <a href='http://example.com'>http://example.com</a>" => "Nested link: <a href='http://example.com'>http://example.com</a>",
//
//            // Incomplete tags
//            "Incomplete tag: <http://example.com" => "Incomplete tag: <a href=\"http://example.com\" target=\"_blank\" rel=\"external noopener nofollow\">http://example.com</a>",
//        ];
//
//        foreach ($testCases as $input => $expected) {
//            $output = $this->sanitizer->makeClickable($input);
//            $this->assertEquals($expected, $output);
//        }
//    }

    private function trimBlockBreaks(string $text): string
    {
        // No setAccessible(): it has had no effect since PHP 8.1 and is deprecated in 8.5.
        $m = new ReflectionMethod(MyTextSanitizer::class, 'trimBlockBreaks');

        return $m->invoke($this->sanitizer, $text);
    }

    public function testTrimBlockBreaksRemovesTrailingBreakAfterCodeBlock()
    {
        $input    = '<div class="xoopsCode"><code>x</code></div><br />after';
        $expected = '<div class="xoopsCode"><code>x</code></div>after';
        $this->assertEquals($expected, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksRemovesTrailingBreakAfterQuoteBlock()
    {
        $input    = '<div class="xoopsQuote"><blockquote>q</blockquote></div><br />after';
        $expected = '<div class="xoopsQuote"><blockquote>q</blockquote></div>after';
        $this->assertEquals($expected, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksRemovesTrailingBreakAfterPreBlock()
    {
        $input    = '<div class="xoopsCode"><pre>x</pre></div><br />after';
        $expected = '<div class="xoopsCode"><pre>x</pre></div>after';
        $this->assertEquals($expected, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksRemovesOnlyOneOfSeveralBreaks()
    {
        $input    = '<div class="xoopsCode"><code>x</code></div><br /><br /><br />after';
        $expected = '<div class="xoopsCode"><code>x</code></div><br /><br />after';
        $this->assertEquals($expected, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksLeavesLeadingBreakUntouched()
    {
        $input = 'before<br /><div class="xoopsCode"><code>x</code></div>';
        $this->assertEquals($input, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksLeavesUnrelatedDivUntouched()
    {
        $input = '<div class="user"><span>x</span></div><br />after';
        $this->assertEquals($input, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksAcceptsBrVariantAndWhitespace()
    {
        $input  = '<div class="xoopsCode"><code>x</code></div>' . "\n" . '<br>after';
        $result = $this->trimBlockBreaks($input);
        $this->assertStringNotContainsString('<br', $result);
    }

    public function testTrimBlockBreaksHandlesMultipleIndependentBlocks()
    {
        $input    = '<div class="xoopsCode"><code>x</code></div><br />mid<div class="xoopsQuote"><blockquote>q</blockquote></div><br />end';
        $expected = '<div class="xoopsCode"><code>x</code></div>mid<div class="xoopsQuote"><blockquote>q</blockquote></div>end';
        $this->assertEquals($expected, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksLeavesAuthoredCodeDivUntouched()
    {
        // Reachable whenever HTML is permitted: the author's own div closes </code></div>
        // exactly like a generated block, but carries no xoopsCode class, so their break stays.
        $input = '<div class="example"><code>x</code></div><br />after';
        $this->assertEquals($input, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksStillTrimsNestedQuote()
    {
        $input    = '<div class="xoopsQuote"><blockquote>o <div class="xoopsQuote"><blockquote>i</blockquote></div> t</blockquote></div><br />after';
        $expected = '<div class="xoopsQuote"><blockquote>o <div class="xoopsQuote"><blockquote>i</blockquote></div> t</blockquote></div>after';
        $this->assertEquals($expected, $this->trimBlockBreaks($input));
    }

    public function testTrimBlockBreaksReturnsNonStringUntouched()
    {
        $method = new ReflectionMethod(MyTextSanitizer::class, 'trimBlockBreaks');

        $array = ['<div class="xoopsCode"><code>x</code></div><br />after'];
        $this->assertSame($array, $method->invoke($this->sanitizer, $array));
        $this->assertNull($method->invoke($this->sanitizer, null));
    }

}
