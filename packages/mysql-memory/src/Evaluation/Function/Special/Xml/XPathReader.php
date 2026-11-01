<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;

/**
 * The tokens of an XPath expression of EXTRACTVALUE and UPDATEXML, read one at a time.
 *
 * A token is an operator, a string literal in single or double quotes, a number of digits with an
 * optional fraction, a name (prefixed names included), or an axis: a name followed by `::`, which
 * must be one of the axes the server knows. An expression that does not read is "XPATH syntax
 * error" quoting the text from the problem on (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XPathReader
{
    /**
     * The axes the server knows.
     */
    public const AXES = ['ancestor', 'ancestor-or-self', 'attribute', 'child', 'descendant', 'descendant-or-self', 'following', 'following-sibling', 'parent', 'preceding', 'preceding-sibling', 'self'];

    /**
     * The position of the reader.
     */
    public int $at = 0;

    /**
     * @param string $text The expression
     */
    public function __construct(public readonly string $text)
    {
    }

    /**
     * Answers the error of an expression that does not read, quoting the text from the reader on.
     */
    public function syntax(?int $at = null): SqlError
    {
        $this->blank();

        return StatementError::UnknownError->error("XPATH syntax error: '" . substr($this->text, $at ?? $this->at) . "'");
    }

    /**
     * Skips blanks at the reader.
     */
    public function blank(): void
    {
        $this->at += strspn($this->text, " \t\r\n", $this->at);
    }

    /**
     * Answers the next token without reading it: its kind, its text and where it starts.
     *
     * @return array{string, string, int}
     *
     * @throws SqlError When a string literal is not closed, or a name is followed by an unknown axis
     *
     * @phpstan-impure
     */
    public function peek(): array
    {
        $this->blank();
        $at = $this->at;
        if ($at >= strlen($this->text)) {
            return ['END', '', $at];
        }
        foreach (['//', '..', '!=', '<=', '>=', '::'] as $pair) {
            if (substr_compare($this->text, $pair, $at, 2) === 0) {
                return [$pair, $pair, $at];
            }
        }
        $char = $this->text[$at];
        if (str_contains('/()[]@,|.*+-=<>$', $char)) {
            return [$char, $char, $at];
        }
        if ($char === '"' || $char === "'") {
            $end = strpos($this->text, $char, $at + 1);
            if ($end === false) {
                throw $this->syntax($at);
            }

            return ['STRING', substr($this->text, $at + 1, $end - $at - 1), $at];
        }
        if (preg_match('/\G[0-9]+(?:\.[0-9]+)?/', $this->text, $number, 0, $at) === 1) {
            return ['NUMBER', $number[0], $at];
        }
        if (preg_match('/\G[A-Za-z_\x80-\xFF][A-Za-z0-9_.\-\x80-\xFF]*(?::[A-Za-z_\x80-\xFF][A-Za-z0-9_.\-\x80-\xFF]*)*/', $this->text, $name, 0, $at) === 1) {
            if (substr_compare($this->text, '::', $at + strlen($name[0]), 2) === 0) {
                if (!in_array($name[0], self::AXES, true)) {
                    throw $this->syntax($at + strlen($name[0]) + 1);
                }

                return ['AXIS', $name[0], $at];
            }

            return ['NAME', $name[0], $at];
        }

        return ['UNKNOWN', $char, $at];
    }

    /**
     * Reads the next token.
     *
     * @return array{string, string, int}
     *
     * @throws SqlError When the token does not read
     *
     * @phpstan-impure
     */
    public function next(): array
    {
        $token = $this->peek();
        $this->at = $token[2] + match ($token[0]) {
            'STRING' => strlen($token[1]) + 2,
            'AXIS' => strlen($token[1]) + 2,
            default => strlen($token[1]),
        };

        return $token;
    }

    /**
     * Answers the first character after a name and the blanks that follow it.
     */
    public function after(string $name): string
    {
        $at = $this->at + strlen($name);
        $at += strspn($this->text, " \t\r\n", $at);

        return $this->text[$at] ?? '';
    }
}
