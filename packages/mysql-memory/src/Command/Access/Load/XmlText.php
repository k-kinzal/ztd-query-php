<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access\Load;

/**
 * Decodes field bytes in a client XML stream without treating them as SQL.
 * Named entities are decoded once; numeric and unknown entities remain literal.
 * MySQL 8 includes CDATA bytes without entity decoding; 5.6 and 5.7 ignore CDATA.
 * Attribute spacing and fragmented text follow native LOAD XML observations.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-xml.html.
 *
 * @visibility MySqlMemory
 */
final class XmlText
{
    /**
     * @return array<string, string|null>|null Attributes, or null for an unquoted attribute value
     */
    public function attributes(string $tag): ?array
    {
        $unquoted = preg_replace('~"[^"]*"|\'[^\']*\'~s', '""', $tag) ?? $tag;
        if (preg_match('~=[^\s"\']~', $unquoted) === 1) {
            return null;
        }
        preg_match_all('~([^\s=<>]+)(\s*=\s*)("[^"]*"|\'[^\']*\')~s', $tag, $pairs, PREG_SET_ORDER);
        $fields = [];
        foreach ($pairs as $pair) {
            $value = $pair[2] === '=' ? $this->decode(substr($pair[3], 1, -1)) : '';
            $fields[$pair[1]] = $value === '' ? null : $value;
        }
        return $fields;
    }

    /**
     * @return array{string, int} Initial text with adjacent CDATA, and the next scanning offset
     */
    public function content(string $input, int $offset, bool $legacy): array
    {
        $end = strpos($input, '<', $offset);
        $text = $this->decode($end === false ? substr($input, $offset) : substr($input, $offset, $end - $offset));
        while (!$legacy && $end !== false && substr($input, $end, 9) === '<![CDATA[') {
            $close = strpos($input, ']]>', $end + 9);
            if ($close === false) {
                break;
            }
            $text .= substr($input, $end + 9, $close - $end - 9);
            $offset = $close + 3;
            $end = strpos($input, '<', $offset);
            $text .= $this->decode($end === false ? substr($input, $offset) : substr($input, $offset, $end - $offset));
        }
        return [ltrim($text, " \t\n\r"), $offset];
    }

    /**
     * Decodes the five named XML entities once, preserving other text.
     */
    public function decode(string $value): string
    {
        return strtr($value, ['&lt;' => '<', '&gt;' => '>', '&amp;' => '&', '&quot;' => '"', '&apos;' => "'"]);
    }
}
