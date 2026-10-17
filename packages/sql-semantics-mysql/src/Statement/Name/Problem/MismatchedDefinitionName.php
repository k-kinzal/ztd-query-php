<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name\Problem;

use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A legacy column, index or constraint qualifier identifies another definition target.
 *
 * The retained target and qualifier distinguish an explicit contradiction from
 * a default-database or name-comparison dependency without keeping input text.
 *
 * @visibility public
 * @example Reading a mismatched definition target
 *     $name = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('other')));
 *     (new \SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName($name, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))->message() // => 'The definition name qualifier does not identify table t.'
 */
final class MismatchedDefinitionName implements Diagnostic
{
    use Snapshot;

    /**
     * @param ColumnName $name The qualified definition name
     * @param QualifiedName $target The table being defined or changed
     */
    public function __construct(public readonly ColumnName $name, public readonly QualifiedName $target)
    {
    }

    /**
     * Describes the mismatch without assuming a current database or case comparison.
     */
    public function message(): string
    {
        return 'The definition name qualifier does not identify table ' . $this->target->name->value . '.';
    }
}
