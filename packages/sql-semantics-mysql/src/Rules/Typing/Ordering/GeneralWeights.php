<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Ordering;

use SqlSemantics\Diagnostic\InvariantViolation;

/**
 * The general_ci identity used by stored-program parameter names.
 *
 * The mapping was sampled through WEIGHT_STRING for every valid BMP code point
 * and compared on MySQL 5.6.51 and 8.4.7, without reading server implementation
 * code. Case and accent variants can therefore denote the same parameter.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-effect.html.
 *
 * @visibility public
 * @example Comparing case and accent variants
 *     $weights = new \SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights();
 *     $weights->key('Résumé') === $weights->key('resume') // => true
 */
final class GeneralWeights
{
    /**
     * @var array<int, string>|null Decoded nonidentity pages of two-byte weights
     */
    private static ?array $pages = null;

    /**
     * Answers a name's comparison key without discarding written trailing spaces.
     */
    public function key(string $name): string
    {
        if (!mb_check_encoding($name, 'UTF-8')) {
            return strtoupper($name);
        }
        $key = '';
        foreach ($name === '' ? [] : mb_str_split($name, 1, 'UTF-8') as $character) {
            $key .= $this->point(mb_ord($character, 'UTF-8'));
        }

        return $key;
    }

    /**
     * Answers the two-byte weight of a Unicode code point.
     */
    public function point(int $point): string
    {
        if ($point > 65535) {
            return "\xff\xfd";
        }
        $page = $this->pages()[$point >> 8] ?? null;

        return $page === null ? pack('n', $point) : substr($page, ($point & 255) * 2, 2);
    }

    /**
     * Loads and checks the sampled nonidentity pages once.
     *
     * @return array<int, string>
     * @throws InvariantViolation When the installed weight resource is malformed
     */
    public function pages(): array
    {
        if (self::$pages !== null) {
            return self::$pages;
        }
        $data = require dirname(__DIR__, 4) . '/resources/general-ci-weights.php';
        if (!is_array($data)) {
            throw new InvariantViolation('The general_ci weight resource contains an array of pages.');
        }
        $pages = [];
        foreach ($data as $number => $hex) {
            if (!is_int($number) || !is_string($hex) || strlen($hex) !== 1024 || !ctype_xdigit($hex)) {
                throw new InvariantViolation('Each general_ci page contains 256 hexadecimal two-byte weights.');
            }
            $decoded = hex2bin($hex);
            if ($decoded === false) {
                throw new InvariantViolation('Each general_ci page decodes to binary weights.');
            }
            $pages[$number] = $decoded;
        }

        return self::$pages = $pages;
    }
}
