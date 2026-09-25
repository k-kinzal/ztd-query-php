<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

/**
 * Removes ordinary comments while retaining directives and executable comment bodies.
 *
 * @visibility SqlFormatter
 */
final class Trivia
{
    /**
     * Whether the next token belongs to an executable comment body.
     */
    public bool $executable = false;

    /**
     * Selects the dialect's block comment nesting rules.
     */
    public function __construct(private readonly bool $postgres, private readonly bool $mysql = false)
    {
    }

    /**
     * Processes trivia in source order, including markers split across token boundaries.
     */
    public function clean(string $text): string
    {
        $result = '';
        $offset = 0;
        while ($offset < strlen($text)) {
            if ($this->executable) {
                $result .= $this->executablePart($text, $offset);
            } elseif (substr($text, $offset, 2) === '/*') {
                $end = $this->blockEnd($text, $offset);
                $directive = in_array(substr($text, $offset + 2, 1), ['+', '!'], true)
                    || substr($text, $offset + 2, 2) === 'M!';
                if ($directive) {
                    $result .= substr($text, $offset, $end - $offset);
                    $this->executable = substr($text, $end - 2, 2) !== '*/';
                }
                $offset = $end;
            } elseif ($text[$offset] === '#' || substr($text, $offset, 2) === '--') {
                $offset += strcspn($text, "\r\n", $offset);
            } else {
                $offset++;
            }
        }
        return $result;
    }

    /**
     * Copies trivia inside executable SQL without confusing inner and outer comments.
     */
    public function executablePart(string $text, int &$offset): string
    {
        $start = $offset;
        if (substr($text, $offset, 2) === '/*') {
            $offset = $this->blockEnd($text, $offset);
        } elseif (substr($text, $offset, 2) === '*/') {
            $offset += 2;
            $this->executable = false;
        } elseif ($text[$offset] === '#' || substr($text, $offset, 2) === '--') {
            $offset += strcspn($text, "\r\n", $offset);
        } else {
            $offset++;
        }
        return substr($text, $start, $offset - $start);
    }

    /**
     * Finds the end of a comment, respecting PostgreSQL's nested block comments.
     */
    public function blockEnd(string $text, int $start): int
    {
        $depth = 1;
        for ($offset = $start + 2, $length = strlen($text); $offset < $length - 1; $offset++) {
            $pair = substr($text, $offset, 2);
            if ($pair === '/*' && ($this->postgres || ($this->mysql && !$this->executable && $depth === 1 && substr($text, $start + 2, 1) !== '!' && substr($text, $offset + 2, 1) === '!'))) {
                $depth++;
                $offset++;
            } elseif ($pair === '*/') {
                $depth--;
                if ($depth === 0) {
                    return $offset + 2;
                }
                $offset++;
            }
        }
        return strlen($text);
    }
}
