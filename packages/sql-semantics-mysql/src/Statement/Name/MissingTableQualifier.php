<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Snapshot;

/**
 * A qualified column reference in a legacy scope with no visible query tables.
 *
 * MySQL distinguishes this missing table qualifier (1109) from a missing
 * column among visible tables (1054), including inside an isolated derived table.
 *
 * @visibility public
 * @example Describing an unresolved table qualifier
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
 *     $problem->message() // => 'Table t is not visible.'
 */
final class MissingTableQualifier implements Resolution, Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The column name written after the qualifier
     * @param QualifiedName $qualifier The table qualifier that has no visible table
     */
    public function __construct(public readonly Name $name, public readonly QualifiedName $qualifier)
    {
    }

    /**
     * Describes the qualifier that no table in scope can supply.
     */
    public function message(): string
    {
        return 'Table ' . ($this->qualifier->schema === null ? '' : $this->qualifier->schema->value . '.') . $this->qualifier->name->value . ' is not visible.';
    }
}
