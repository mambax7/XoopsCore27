<?php

declare(strict_types=1);

/**
 * The [youtube] tag rendered by MytsYoutube.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

namespace xoopsclass;

use MyTextSanitizer;
use MytsYoutube;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The [youtube] tag renders with and without its =width,height size.
 *
 * SCEditor writes a bare [youtube]id[/youtube] for a pasted link, which the
 * sized-only pattern left on the page as literal text.
 */
#[CoversClass(MytsYoutube::class)]
final class YoutubeTagTest extends TestCase
{
    private function render(string $text): string
    {
        require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
        require_once XOOPS_ROOT_PATH . '/class/textsanitizer/youtube/youtube.php';
        $myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->callbackPatterns = [];
        $myts->callbacks        = [];
        (new ReflectionClass(MytsYoutube::class))->newInstanceWithoutConstructor()->load($myts);

        return (string) preg_replace_callback($myts->callbackPatterns[0], $myts->callbacks[0], $text);
    }

    #[Test]
    public function bareTagRendersTheVideo(): void
    {
        $html = $this->render('[youtube]s4I4zaY5B6s[/youtube]');
        self::assertStringContainsString('youtube.com/embed/s4I4zaY5B6s', $html);
        self::assertStringNotContainsString('[youtube]', $html);
    }

    #[Test]
    public function sizedTagStillRenders(): void
    {
        $html = $this->render('[youtube=640,360]https://www.youtube.com/watch?v=s4I4zaY5B6s[/youtube]');
        self::assertStringContainsString('youtube.com/embed/s4I4zaY5B6s', $html);
    }

    #[Test]
    public function textThatIsNotAVideoStaysAsWritten(): void
    {
        self::assertSame('[youtube]not a video[/youtube]', $this->render('[youtube]not a video[/youtube]'));
        self::assertSame('[youtube=4,3]nope[/youtube]', $this->render('[youtube=4,3]nope[/youtube]'));
    }

    #[Test]
    public function overlongIdInAUrlIsNotAVideo(): void
    {
        $tag = '[youtube]https://youtu.be/s4I4zaY5B6sX[/youtube]';
        self::assertSame($tag, $this->render($tag));
    }

    #[Test]
    public function urlOnAnotherHostIsNotAVideo(): void
    {
        foreach ([
            'https://notyoutube.com/watch?v=s4I4zaY5B6s',
            'https://evil.example/youtube.com/watch?v=s4I4zaY5B6s',
            'https://notyoutu.be/s4I4zaY5B6s',
        ] as $url) {
            $tag = "[youtube]{$url}[/youtube]";
            self::assertSame($tag, $this->render($tag), $url);
        }
    }

    #[Test]
    public function youtubeHostVariantsRender(): void
    {
        foreach ([
            'https://youtube.com/watch?v=s4I4zaY5B6s',
            'http://m.youtube.com/watch?v=s4I4zaY5B6s',
            '//www.youtube-nocookie.com/embed/s4I4zaY5B6s',
            'www.youtube.com/watch?v=s4I4zaY5B6s',
            'youtu.be/s4I4zaY5B6s',
        ] as $url) {
            self::assertStringContainsString('youtube.com/embed/s4I4zaY5B6s"', $this->render("[youtube]{$url}[/youtube]"), $url);
        }
    }

    /**
     * SCEditor's preview uses its own copy of the URL pattern; if the two
     * disagree, the editor shows a player the saved post will not (or hides one it will).
     */
    #[Test]
    public function editorPreviewPatternAgreesWithTheServer(): void
    {
        $js = (string) file_get_contents(XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/js/xoops-bbcode.js');
        self::assertSame(1, preg_match('~var match = /(.+?)/i\.exec\(content\)~', $js, $m), 'youtube pattern not found in xoops-bbcode.js');
        $editorPattern = '%' . str_replace('\/', '/', $m[1]) . '%i';

        $this->render(''); // loads MytsYoutube
        $videoId = (new ReflectionClass(MytsYoutube::class))->getMethod('videoId');

        foreach ([
            'https://www.youtube.com/watch?v=s4I4zaY5B6s',
            'https://youtube.com/watch?v=s4I4zaY5B6s&amp;t=30',
            'http://m.youtube.com/watch?v=s4I4zaY5B6s',
            '//www.youtube-nocookie.com/embed/s4I4zaY5B6s',
            'www.youtube.com/v/s4I4zaY5B6s',
            'https://youtu.be/s4I4zaY5B6s?t=30',
            'youtu.be/s4I4zaY5B6s',
            'https://youtu.be/s4I4zaY5B6sX',
            'https://notyoutube.com/watch?v=s4I4zaY5B6s',
            'https://evil.example/youtube.com/watch?v=s4I4zaY5B6s',
            'https://notyoutu.be/s4I4zaY5B6s',
        ] as $url) {
            $editorId = preg_match($editorPattern, $url, $hit) ? $hit[1] : null;
            self::assertSame($videoId->invoke(null, $url), $editorId, $url);
        }
    }

    #[Test]
    public function idOutsideTheYoutubeAlphabetIsNotAVideo(): void
    {
        foreach (['[youtube]abc.def:ghi[/youtube]', '[youtube]https://youtu.be/abc.def:ghi[/youtube]'] as $tag) {
            self::assertSame($tag, $this->render($tag), $tag);
        }
    }

    #[Test]
    public function idFollowedByAUrlSuffixStillRenders(): void
    {
        foreach (['https://youtu.be/s4I4zaY5B6s?t=30', 'https://www.youtube.com/watch?v=s4I4zaY5B6s&amp;t=30', 'https://youtu.be/s4I4zaY5B6s#t=30'] as $url) {
            self::assertStringContainsString('youtube.com/embed/s4I4zaY5B6s"', $this->render("[youtube]{$url}[/youtube]"), $url);
        }
    }

    #[Test]
    public function nonNumericOrZeroSizeFallsBackToTheDefault(): void
    {
        foreach (['100,x', '100,0x', 'x,x', '0,0', '-5,-5'] as $size) {
            $html = $this->render("[youtube={$size}]s4I4zaY5B6s[/youtube]");
            self::assertStringContainsString('youtube.com/embed/s4I4zaY5B6s', $html, $size);
        }
    }

    #[Test]
    public function quotedSizeStillRenders(): void
    {
        $html = $this->render('[youtube="16,9"]s4I4zaY5B6s[/youtube]');
        self::assertStringContainsString('youtube.com/embed/s4I4zaY5B6s', $html);
    }
}
