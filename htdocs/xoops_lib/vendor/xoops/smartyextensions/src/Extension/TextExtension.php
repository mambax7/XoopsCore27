<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * Text processing Smarty modifiers.
 *
 * Pure PHP — no XOOPS dependencies.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class TextExtension extends AbstractExtension
{
    public function getModifiers(): array
    {
        return [
            'excerpt' => $this->excerpt(...),
            'truncate_words' => $this->truncateWords(...),
            'nl2p' => $this->nl2p(...),
            'highlight_text' => $this->highlightText(...),
            'reading_time' => $this->readingTime(...),
            'pluralize' => $this->pluralize(...),
            'extract_hashtags' => $this->extractHashtags(...),
        ];
    }

    public function excerpt(string $text, int $length = 100, string $ending = '...'): string
    {
        if (\mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        $truncated = \mb_substr($text, 0, $length - \mb_strlen($ending, 'UTF-8'), 'UTF-8');

        $lastSpace = \mb_strrpos($truncated, ' ', 0, 'UTF-8');
        if ($lastSpace !== false) {
            $truncated = \mb_substr($truncated, 0, $lastSpace, 'UTF-8');
        }

        return $truncated . $ending;
    }

    public function truncateWords(string $string, int $limit = 20, string $ending = '...'): string
    {
        $words = \preg_split('/\s+/', \trim($string), -1, PREG_SPLIT_NO_EMPTY);

        if (\count($words) <= $limit) {
            return $string;
        }

        return \implode(' ', \array_slice($words, 0, $limit)) . $ending;
    }

    public function nl2p(string $text): string
    {
        $text = \trim($text);

        if ($text === '') {
            return '';
        }

        $text = \str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = \preg_split('/\n{2,}/', $text);

        $html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = \trim($paragraph);
            if ($paragraph !== '') {
                $html .= '<p>' . \nl2br($paragraph, false) . '</p>' . "\n";
            }
        }

        return $html;
    }

    public function highlightText(string $text, string $highlight, string $class = 'highlight'): string
    {
        if ($highlight === '') {
            return $text;
        }

        $safeClass = \htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $pattern = '/(' . \preg_quote($highlight, '/') . ')/iu';

        return \preg_replace($pattern, '<span class="' . $safeClass . '">$1</span>', $text);
    }

    public function readingTime(string $text, int $wordsPerMinute = 200): string
    {
        $wordCount = \str_word_count(\strip_tags($text));
        $minutes = (int) \ceil($wordCount / \max(1, $wordsPerMinute));

        return $minutes . ' min read';
    }

    public function pluralize(int $count, string $singular, string $plural = ''): string
    {
        if ($plural === '') {
            $plural = $singular . 's';
        }

        return $count === 1 ? $singular : $plural;
    }

    /**
     * @return list<string>
     */
    public function extractHashtags(string $text): array
    {
        \preg_match_all('/#([\w]+)/u', $text, $matches);

        return $matches[1];
    }
}
