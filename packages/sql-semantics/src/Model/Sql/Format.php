<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

/**
 * Chooses token boundaries without retaining source whitespace or ordinary comments.
 *
 * @visibility SqlSemantics
 */
final class Format
{
    /**
     * @param list<Atom> $atoms
     */
    public static function write(array $atoms): string
    {
        $sql = '';
        $previous = null;
        foreach ($atoms as $atom) {
            if ($atom->text === '') {
                continue;
            }
            $sql .= $previous === null || self::adjacent($previous, $atom) ? '' : ' ';
            $sql .= $atom->text;
            $previous = $atom;
        }
        return $sql;
    }

    /**
     * Keeps punctuation tight while preventing separate operators from becoming a comment.
     */
    public static function adjacent(Atom $left, Atom $right): bool
    {
        if ($left->kind === 'annotation' || $right->kind === 'annotation') {
            return false;
        }
        if (in_array($right->text, [',', ';', ')', ']', '['], true) || in_array($left->text, ['(', '[', '@', '@@'], true)) {
            return true;
        }
        if ($right->text === '(') {
            return preg_match('/^[\\p{L}_`"]|[)\\]]$/u', $left->text) === 1
                && !in_array(strtoupper($left->text), ['SELECT', 'AS', 'WHERE', 'AND', 'OR', 'NOT', 'IN', 'ON', 'VALUES', 'VALUE', 'RETURNING', 'HAVING', 'BY', 'UNION', 'INTERSECT', 'EXCEPT', 'ALL', 'DISTINCT', 'THEN', 'ELSE', 'WHEN', 'SET', 'DEFAULT', 'CHECK', 'FILTER', 'OVER', 'WITH'], true);
        }
        if ($left->text === '.' || $right->text === '.') {
            return preg_match('/^[0-9]/', $left->text) !== 1 && preg_match('/^[0-9]/', $right->text) !== 1;
        }
        return false;
    }
}
