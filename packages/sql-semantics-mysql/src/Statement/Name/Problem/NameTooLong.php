<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A decoded object name exceeds the identifier domain of its declaration.
 *
 * @visibility public
 * @example Describing an oversized decoded name
 *     (new \SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong(new \SqlSemantics\Statement\Identifier\Name(str_repeat('p', 65))))->name->value // => str_repeat('p', 65)
 */
final class NameTooLong implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The decoded identifier without SQL quoting
     */
    public function __construct(public readonly Name $name)
    {
        \SqlSemantics\Diagnostic\Check::input((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnNameRule())->length($name->value) > 64, 'An oversized identifier exceeds 64 characters.');
    }

    /**
     * Describes the refused identifier domain.
     */
    public function message(): string
    {
        return "Identifier name '" . $this->name->value . "' is too long";
    }
}
