<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Values a construct unifies whose types have no common type.
 *
 * Source: https://www.postgresql.org/docs/17/typeconv-union-case.html.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\TypeConflict('CASE', 'integer', 'boolean'))->message() // => 'CASE types integer and boolean cannot be matched.'
 */
final class TypeConflict implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $construct The construct, such as 'CASE'
     * @param string $first The candidate type
     * @param string $second The type that does not match it
     */
    public function __construct(public readonly string $construct, public readonly string $first, public readonly string $second)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->construct . ' types ' . $this->first . ' and ' . $this->second . ' cannot be matched.';
    }
}
