<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

/**
 * A named insertion destination with its table and optional column declaration.
 *
 * The declaration describes storage, not the type of the expression assigned to it.
 * An absent declaration explicitly leaves column resolution to database state.
 *
 * @visibility public
 * @example Inspecting the declared insertion destination
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (amount INT)');
 *     $insert = $semantics->analyze("INSERT INTO t (amount) VALUES ('42')", [$table]);
 *     $insert->target->columns[0]->declaration === $table->declarations()[0]->columns[0] // => true
 */
final class DestinationColumn implements Node
{
    use Snapshot;

    /**
     * @param Name $name The written column name
     * @param TableResolution $table The table this destination belongs to
     * @param Column|null $declaration The resolved column, absent when unresolved
     * @param Span|null $source The location of the written column name
     */
    public function __construct(public readonly Name $name, public readonly TableResolution $table, public readonly ?Column $declaration = null, public readonly ?Span $source = null)
    {
        Check::input($declaration === null || ($table instanceof DeclaredTable && in_array($declaration, $table->table->columns, true)), 'A resolved destination belongs to its declared table.');
        Check::input($declaration === null || strcasecmp($name->value, $declaration->name->value) === 0, 'The destination name denotes its declared column.');
    }

    /**
     * Writes the destination column name.
     */
    public function render(Output $out): void
    {
        $out->name($this->name);
    }
}
