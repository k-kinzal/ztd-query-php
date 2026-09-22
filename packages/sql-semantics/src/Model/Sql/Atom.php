<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

/**
 * One SQL terminal with its lexical role, without positions or whitespace trivia.
 *
 * @example Working with SQL structure
 *     $atom = new \SqlSemantics\Model\Sql\Atom('keyword', 'SELECT');
 *     $atom->text // => 'SELECT'
 *
 * @visibility public
 */
final class Atom
{
    /**
     * Keeps the spelling of literals and quoted identifiers semantically intact.
     */
    public function __construct(public readonly string $kind, public readonly string $text)
    {
    }
}
