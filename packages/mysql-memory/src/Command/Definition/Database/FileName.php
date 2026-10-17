<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Database;

use SqlSemantics\Diagnostic\Check;

/**
 * Encodes identifiers displayed in legacy table-file diagnostics.
 *
 * ASCII letters, digits and underscores retain their spelling. Other characters
 * use the documented UCS-2 hexadecimal or compact letter spelling. The compact
 * map was observed through RENAME TABLE errors on MySQL 5.6.51 and 5.7.44; no
 * server implementation is used. Reserved device names receive the portable
 * suffix even on Unix. This class never accesses a database's physical files.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/identifier-mapping.html.
 *
 * @visibility MySqlMemory
 */
final class FileName
{
    /**
     * @var array<string, string>|null Compact character spellings shared by legacy releases
     */
    private static ?array $letters = null;

    /**
     * Answers the filename spelling of one database or table identifier.
     */
    public function encoded(string $identifier): string
    {
        if (str_starts_with($identifier, '#mysql50#') && strlen($identifier) > 9) {
            $raw = substr($identifier, 9);
            return strpbrk($raw, './\\') === false ? $raw : '';
        }
        $letters = self::$letters ??= $this->letters();
        $name = '';
        foreach (mb_str_split($identifier, 1, 'UTF-8') as $character) {
            $point = mb_ord($character, 'UTF-8');
            $name .= preg_match('/\A[A-Za-z0-9_]\z/', $character) === 1 ? $character : ($letters[$character] ?? sprintf('@%04x', $point));
        }
        return preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])\z/i', $identifier) === 1 ? $name . '@@@' : $name;
    }

    /**
     * Loads the character map observed independently from both legacy releases.
     *
     * @return array<string, string>
     */
    public function letters(): array
    {
        $contents = file_get_contents(dirname(__DIR__, 4) . '/resources/identifier-letters.json');
        Check::invariant($contents !== false, 'The installed identifier character map is readable.');
        $data = json_decode($contents, true);
        Check::invariant(is_array($data), 'The identifier character map is an object.');
        $letters = [];
        foreach ($data as $character => $encoded) {
            Check::invariant(is_string($character) && is_string($encoded), 'Identifier map entries contain character spellings.');
            $letters[$character] = $encoded;
        }
        return $letters;
    }
}
