<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Copies SQL syntax into owned productions, retaining meaningful annotations only.
 *
 * @visibility SqlSemantics
 */
final class Source
{
    /**
     * Does not retain parser objects, byte offsets, or whitespace trivia in the result.
     */
    public static function read(Node|Token $source): Tree
    {
        if ($source instanceof Token) {
            return new Tree($source->name, [...self::annotations($source->leading), new Atom($source->name, $source->text)]);
        }
        return new Tree($source->name, [...array_map(self::read(...), $source->children), ...self::annotations($source->trailing)]);
    }

    /**
     * Complete executable comments are inactive for this grammar release; active bodies
     * are already SQL terminals and do not retain their surrounding comment markers.
     *
     * @return list<Atom>
     */
    public static function annotations(string $trivia): array
    {
        $result = [];
        $offset = 0;
        $length = strlen($trivia);
        while ($offset < $length) {
            $prefix = substr($trivia, $offset, 2);
            if ($prefix === '--' || $trivia[$offset] === '#') {
                $newline = strpos($trivia, "\n", $offset);
                $offset = $newline === false ? $length : $newline;
            } elseif ($prefix === '/*') {
                $end = self::commentEnd($trivia, $offset);
                if ($end === null) {
                    break;
                }
                if (in_array($trivia[$offset + 2] ?? '', ['+', '!'], true)) {
                    $result[] = new Atom('annotation', substr($trivia, $offset, $end - $offset));
                }
                $offset = $end;
            } else {
                ++$offset;
            }
        }
        return $result;
    }

    /**
     * Recognizes nested block comments without treating their contents as annotations.
     */
    public static function commentEnd(string $trivia, int $offset): ?int
    {
        $depth = 1;
        $position = $offset + 2;
        while (preg_match('~/\*|\*/~', $trivia, $match, PREG_OFFSET_CAPTURE, $position) === 1) {
            $depth += $match[0][0] === '/*' ? 1 : -1;
            $position = $match[0][1] + 2;
            if ($depth === 0) {
                return $position;
            }
        }
        return null;
    }
}
