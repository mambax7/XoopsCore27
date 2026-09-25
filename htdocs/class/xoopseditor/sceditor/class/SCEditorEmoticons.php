<?php

declare(strict_types=1);

/**
 * SCEditor's upstream emoticon set, registered as ordinary XOOPS smileys.
 *
 * Posts store the code (:sick:), never an image link, and MyTextSanitizer::smiley()
 * renders it from the smiles table like any other smiley. The installer and the
 * 2.7.4 upgrade both call install(); admins manage the result in System > Smilies.
 *
 * @category  Xoops
 * @package   Xoops\Editor
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

final class SCEditorEmoticons
{
    /** Copied files get this prefix in uploads/smilies, so they never collide with XOOPS ones. */
    public const FILE_PREFIX = 'sceditor_';

    /**
     * code => [image file in sceditor/emoticons, description, display].
     * Order and grouping follow SCEditor's defaults: display 1 = its dropdown,
     * 0 = its "more" and hidden sets. '8-)' is absent: XOOPS ships it already.
     * Codes are :name: only: smiley() replaces codes anywhere in a message, so
     * SCEditor's short codes would change text such as Re:Post (:P) or x<3.
     */
    private const LIST = [
        ':smile:'     => ['smile.png', 'Smile', 1],
        ':angel:'     => ['angel.png', 'Angel', 1],
        ':angry:'     => ['angry.png', 'Angry', 1],
        ':crying:'    => ['cwy.png', 'Crying', 1],
        ':ermm:'      => ['ermm.png', 'Ermm', 1],
        ':grin:'      => ['grin.png', 'Grin', 1],
        ':heart:'     => ['heart.png', 'Heart', 1],
        ':sad:'       => ['sad.png', 'Sad', 1],
        ':shocked:'   => ['shocked.png', 'Shocked', 1],
        ':tongue:'    => ['tongue.png', 'Tongue', 1],
        ':wink:'      => ['wink.png', 'Wink', 1],
        ':alien:'     => ['alien.png', 'Alien', 0],
        ':blink:'     => ['blink.png', 'Blink', 0],
        ':blush:'     => ['blush.png', 'Blush', 0],
        ':cheerful:'  => ['cheerful.png', 'Cheerful', 0],
        ':devil:'     => ['devil.png', 'Devil', 0],
        ':dizzy:'     => ['dizzy.png', 'Dizzy', 0],
        ':getlost:'   => ['getlost.png', 'Get lost', 0],
        ':happy:'     => ['happy.png', 'Happy', 0],
        ':kissing:'   => ['kissing.png', 'Kissing', 0],
        ':ninja:'     => ['ninja.png', 'Ninja', 0],
        ':pinch:'     => ['pinch.png', 'Pinch', 0],
        ':pouty:'     => ['pouty.png', 'Pouty', 0],
        ':sick:'      => ['sick.png', 'Sick', 0],
        ':sideways:'  => ['sideways.png', 'Sideways', 0],
        ':silly:'     => ['silly.png', 'Silly', 0],
        ':sleeping:'  => ['sleeping.png', 'Sleeping', 0],
        ':unsure:'    => ['unsure.png', 'Unsure', 0],
        ':woot:'      => ['w00t.png', 'W00t', 0],
        ':wassat:'    => ['wassat.png', 'Wassat', 0],
        ':whistling:' => ['whistling.png', 'Whistling', 0],
        ':love:'      => ['wub.png', 'Love', 0],
    ];

    /**
     * The emoticons as smiles rows.
     *
     * @return array<int, array{code: string, file: string, smile_url: string, emotion: string, display: int}>
     */
    public static function list(): array
    {
        $rows = [];
        foreach (self::LIST as $code => [$file, $emotion, $display]) {
            $rows[] = [
                'code'      => $code,
                'file'      => $file,
                'smile_url' => 'smilies/' . self::FILE_PREFIX . $file,
                'emotion'   => $emotion,
                'display'   => $display,
            ];
        }

        return $rows;
    }

    /**
     * Codes from list() that have no smiles row yet, or whose row uses the bundled
     * image and that image is not in uploads. A row with its own image is the
     * admin's smiley and counts as present.
     *
     * @param XoopsMySQLDatabase $db         database connection
     * @param string             $uploadPath uploads directory; '' = the site's
     *
     * @return array<int, array{code: string, file: string, smile_url: string, emotion: string, display: int}>|null
     *         null when the smiles table cannot be read
     */
    public static function missing(XoopsMySQLDatabase $db, string $uploadPath = ''): ?array
    {
        $uploadPath = $uploadPath !== '' ? $uploadPath : self::uploadPath();
        $existing = self::existingCodes($db);
        if (null === $existing) {
            return null;
        }
        $missing = [];
        foreach (self::list() as $row) {
            $url = $existing[$row['code']] ?? null;
            if (null === $url || ($url === $row['smile_url'] && !is_file($uploadPath . '/' . $row['smile_url']))) {
                $missing[] = $row;
            }
        }

        return $missing;
    }

    /**
     * Copy missing images to uploads/smilies and insert missing smiles rows.
     * Idempotent; an existing row is never changed. Its image is copied only when
     * the row uses the bundled one, so an admin's own smiley is left alone.
     *
     * @param XoopsMySQLDatabase $db         database connection
     * @param array<int, string>       $logs       receives one line per failure
     * @param string             $uploadPath uploads directory; '' = the site's
     *
     * @return bool true when every emoticon has its row and image afterwards
     *
     * @throws \mysqli_sql_exception when MySQLi is set to throw and an INSERT fails
     */
    public static function install(XoopsMySQLDatabase $db, array &$logs = [], string $uploadPath = ''): bool
    {
        $uploadPath = $uploadPath !== '' ? $uploadPath : self::uploadPath();
        $existing = self::existingCodes($db);
        if (null === $existing) {
            $logs[] = 'Could not read the smiles table';

            return false;
        }
        $ok = true;
        foreach (self::list() as $row) {
            $url = $existing[$row['code']] ?? null;
            if (null !== $url && $url !== $row['smile_url']) {
                continue;
            }
            $target = $uploadPath . '/' . $row['smile_url'];
            $source = XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/emoticons/' . $row['file'];
            if (!is_file($target) && !copy($source, $target)) {
                $logs[] = sprintf('Could not copy %s to %s', $row['file'], $row['smile_url']);
                $ok     = false;
                continue;
            }
            if (null !== $url) {
                continue;
            }
            $sql = 'INSERT INTO ' . $db->prefix('smiles') . ' (code, smile_url, emotion, display) VALUES ('
                 . $db->quote($row['code']) . ', ' . $db->quote($row['smile_url']) . ', '
                 . $db->quote($row['emotion']) . ', ' . $row['display'] . ')';
            if (!$db->exec($sql)) {
                $logs[] = sprintf('Could not insert smiley %s: %s', $row['code'], $db->error());
                $ok     = false;
            }
        }

        return $ok;
    }

    /**
     * @param XoopsMySQLDatabase $db database connection
     *
     * @return array<string, string>|null smile_url of each code already in the smiles table
     */
    private static function existingCodes(XoopsMySQLDatabase $db): ?array
    {
        $result = $db->query('SELECT code, smile_url FROM ' . $db->prefix('smiles'));
        if (!$db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }
        $codes = [];
        while (is_array($row = $db->fetchArray($result))) {
            $codes[(string) $row['code']] = (string) $row['smile_url'];
        }

        return $codes;
    }

    private static function uploadPath(): string
    {
        return defined('XOOPS_UPLOAD_PATH') ? XOOPS_UPLOAD_PATH : XOOPS_ROOT_PATH . '/uploads';
    }
}
