<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

/**
 * Checks the definition of a spatial reference system: an OGC well-known text of a geographic (GEOGCS) or projected (PROJCS) coordinate system.
 *
 * A node is a keyword, without regard to letter case, followed by its items in brackets or
 * parentheses. A GEOGCS names a datum with its spheroid, a prime meridian, an angular unit and
 * two axes; a PROJCS names a GEOGCS, a projection, its parameters, a linear unit and two axes.
 * AUTHORITY may end the items of any node but an axis, and TOWGS84 may follow the spheroid of a
 * datum (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html,
 * https://dev.mysql.com/doc/refman/8.4/en/spatial-reference-systems.html.
 *
 * @visibility MySqlMemory
 */
final class SpatialDefinition
{
    /**
     * @var list<array{string, string}> The tokens of the definition being read: each a kind (word, text, number, open, close or comma) and its text
     */
    private array $tokens = [];

    private int $at = 0;

    /**
     * Tells whether a text is a definition the server accepts.
     *
     * @example A geographic system without axes
     *     (new \MySqlMemory\Command\Admin\SpatialDefinition())->valid('GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.25]],PRIMEM["G",0],UNIT["degree",0.0174]]') // => false
     */
    public function valid(string $definition): bool
    {
        $tokens = $this->tokenize($definition);
        if ($tokens === null || $tokens === []) {
            return false;
        }
        $this->tokens = $tokens;
        $this->at = 0;
        $first = strtoupper($tokens[0][1]);
        $valid = $first === 'PROJCS' ? $this->projected() : $this->geographic();

        return $valid && $this->at === count($tokens);
    }

    /**
     * Splits a definition into tokens, or answers null for a character no token starts with.
     *
     * @return list<array{string, string}>|null
     */
    public function tokenize(string $definition): ?array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($definition);
        while ($offset < $length) {
            if (preg_match('/\G\s+/', $definition, $space, 0, $offset) === 1) {
                $offset += strlen($space[0]);
                continue;
            }
            $token = match (true) {
                preg_match('/\G"([^"]*)"/', $definition, $found, 0, $offset) === 1 => ['text', $found],
                preg_match('/\G[+-]?(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', $definition, $found, 0, $offset) === 1 => ['number', $found],
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $definition, $found, 0, $offset) === 1 => ['word', $found],
                preg_match('/\G[\[(]/', $definition, $found, 0, $offset) === 1 => ['open', $found],
                preg_match('/\G[\])]/', $definition, $found, 0, $offset) === 1 => ['close', $found],
                preg_match('/\G,/', $definition, $found, 0, $offset) === 1 => ['comma', $found],
                default => null,
            };
            if ($token === null) {
                return null;
            }
            $tokens[] = [$token[0], $token[1][1] ?? $token[1][0]];
            $offset += strlen($token[1][0]);
        }

        return $tokens;
    }

    /**
     * Reads a GEOGCS node.
     *
     * @phpstan-impure
     */
    public function geographic(): bool
    {
        return $this->opening('GEOGCS') && $this->take('text') && $this->take('comma') && $this->datum() && $this->take('comma')
            && $this->measured('PRIMEM') && $this->take('comma') && $this->measured('UNIT') && $this->take('comma')
            && $this->axis() && $this->take('comma') && $this->axis() && $this->closing();
    }

    /**
     * Reads a PROJCS node.
     *
     * @phpstan-impure
     */
    public function projected(): bool
    {
        if (!($this->opening('PROJCS') && $this->take('text') && $this->take('comma') && $this->geographic() && $this->take('comma')
            && $this->opening('PROJECTION') && $this->take('text') && $this->closing() && $this->take('comma'))) {
            return false;
        }
        while ($this->keyword('PARAMETER')) {
            if (!($this->measured('PARAMETER') && $this->take('comma'))) {
                return false;
            }
        }

        return $this->measured('UNIT') && $this->take('comma') && $this->axis() && $this->take('comma') && $this->axis() && $this->closing();
    }

    /**
     * Reads a DATUM node: a name and a spheroid, then an optional TOWGS84.
     *
     * @phpstan-impure
     */
    public function datum(): bool
    {
        if (!($this->opening('DATUM') && $this->take('text') && $this->take('comma') && $this->opening('SPHEROID') && $this->take('text')
            && $this->take('comma') && $this->take('number') && $this->take('comma') && $this->take('number') && $this->closing())) {
            return false;
        }
        if ($this->peek('comma') && $this->keyword('TOWGS84', 1)) {
            $this->at++;
            if (!($this->opening('TOWGS84') && $this->take('number'))) {
                return false;
            }
            while ($this->peek('comma')) {
                if (!($this->take('comma') && $this->take('number'))) {
                    return false;
                }
            }
            if (!$this->take('close')) {
                return false;
            }
        }

        return $this->closing();
    }

    /**
     * Reads a node of a name and a number: PRIMEM, UNIT or PARAMETER.
     *
     * @phpstan-impure
     */
    public function measured(string $keyword): bool
    {
        return $this->opening($keyword) && $this->take('text') && $this->take('comma') && $this->take('number') && $this->closing();
    }

    /**
     * Reads an AXIS node: a name and a direction.
     *
     * @phpstan-impure
     */
    public function axis(): bool
    {
        return $this->opening('AXIS') && $this->take('text') && $this->take('comma') && $this->take('word') && $this->take('close');
    }

    /**
     * Reads a keyword and its opening bracket.
     *
     * @phpstan-impure
     */
    public function opening(string $keyword): bool
    {
        if (!$this->keyword($keyword)) {
            return false;
        }
        $this->at++;

        return $this->take('open');
    }

    /**
     * Reads the end of a node: an optional AUTHORITY of two texts, then the closing bracket.
     *
     * @phpstan-impure
     */
    public function closing(): bool
    {
        if ($this->peek('comma') && $this->keyword('AUTHORITY', 1)) {
            $this->at += 2;
            if (!($this->take('open') && $this->take('text') && $this->take('comma') && $this->take('text') && $this->take('close'))) {
                return false;
            }
        }

        return $this->take('close');
    }

    /**
     * Tells whether the token at an offset from the current one is a keyword, without regard to letter case.
     */
    public function keyword(string $keyword, int $ahead = 0): bool
    {
        $token = $this->tokens[$this->at + $ahead] ?? null;

        return $token !== null && $token[0] === 'word' && strtoupper($token[1]) === $keyword;
    }

    /**
     * Tells whether the current token is of a kind.
     */
    public function peek(string $kind): bool
    {
        return ($this->tokens[$this->at][0] ?? null) === $kind;
    }

    /**
     * Reads a token of a kind.
     *
     * @phpstan-impure
     */
    public function take(string $kind): bool
    {
        if (!$this->peek($kind)) {
            return false;
        }
        $this->at++;

        return true;
    }
}
