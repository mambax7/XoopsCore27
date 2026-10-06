<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/xoopsfilterinput.php';

/**
 * Tag filtering terminates and leaves no tag opener in stripped text.
 *
 * Xmf\FilterInput::filterTags() re-appended the rest of the string whenever it
 * met "<>", so remove() looped until PHP ran out of memory on any request value
 * such as "a<>b" read through Xmf\Request::getString().
 */
#[CoversClass(\XoopsFilterInput::class)]
class XoopsFilterInputTest extends TestCase
{
    #[Test]
    public function emptyTagIsKeptWithoutGrowing(): void
    {
        $this->assertSame('a<>b', \XoopsFilterInput::clean('a<>b', 'string'));
        $this->assertSame('x<> y<>', \XoopsFilterInput::clean('x<> y<>', 'string'));
        $this->assertSame('<>keep', \XoopsFilterInput::clean('<><b>keep</b>', 'string'));
    }

    #[Test]
    public function regularTagsAreStillStripped(): void
    {
        $this->assertSame('xbold y', \XoopsFilterInput::clean('x<b>bold</b> y', 'string'));
    }

    #[Test]
    public function incompleteTagDoesNotSurviveAsTag(): void
    {
        // text before a nested or missing ">" is kept, but without a "<" that would open a tag
        $this->assertSame('img src="<>" onerror=alert(1)>', \XoopsFilterInput::clean('<img src="<>" onerror=alert(1)>', 'string'));
        $this->assertSame('img src=x onerror=alert(1) ', \XoopsFilterInput::clean('<img src=x onerror=alert(1) <b>', 'string'));
        $this->assertSame('ximg src=x onerror=alert(1) ', \XoopsFilterInput::clean('x<img src=x onerror=alert(1) ', 'string'));
        $this->assertSame('a < b', \XoopsFilterInput::clean('a < b', 'string'));
    }

    #[Test]
    public function allowedHtmlBareTagTerminates(): void
    {
        $filter = \XoopsFilterInput::getInstance([], [], 1, 1);
        $this->assertSame('<a />', $filter->cleanVar('<a>', 'html'));
        $this->assertSame('<a href="x">t</a>', $filter->cleanVar('<a href="x" onclick="y">t</a>', 'html'));
    }

    #[Test]
    public function allowedHtmlDropsEventHandlersAndEncodedScriptUrls(): void
    {
        $filter = \XoopsFilterInput::getInstance([], [], 1, 1);
        $this->assertSame('<img src="x" />', $filter->cleanVar('<img src=x ONERROR=alert(1)>', 'html'));
        $this->assertSame('<a>x</a>', $filter->cleanVar('<a href="javascript&colon;alert(1)">x</a>', 'html'));
        $this->assertSame('<a>x</a>', $filter->cleanVar('<a href="java&Tab;script:alert(1)">x</a>', 'html'));
    }

    #[Test]
    public function namesWithATrailingNewlineAreRejected(): void
    {
        $filter = \XoopsFilterInput::getInstance([], [], 1, 1);
        $this->assertSame('alert(1)', $filter->cleanVar("<script\n>alert(1)</script\n>", 'html'));
        $this->assertSame('<form />', $filter->cleanVar("<form action\n=\"https://evil.example\">", 'html'));
    }
}
