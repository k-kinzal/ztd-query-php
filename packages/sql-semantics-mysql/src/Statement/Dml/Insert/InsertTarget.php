<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

/**
 * The table and optional named destination list of an INSERT.
 *
 * @visibility public
 * @example Keeping an absent table explicit
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSERT missing VALUES ('42')", []);
 *     $insert->target->resolution instanceof \SqlSemantics\Statement\Reference\Table\MissingTable // => true
 */
final class InsertTarget implements Node
{
    use Snapshot;

    /**
     * @var list<DestinationColumn>|null The explicit columns, or null when omitted
     */
    public readonly ?array $columns;

    /**
     * @param list<DestinationColumn>|null $columns The optional written destination list
     */
    public function __construct(public readonly QualifiedName $name, public readonly TableResolution $resolution, ?array $columns = null, public readonly ?Span $source = null)
    {
        $this->columns = $columns === null ? null : Check::listOf($columns, DestinationColumn::class, 'INSERT destinations are column references.');
        foreach ($this->columns ?? [] as $column) {
            Check::input($column->table === $resolution, 'Every destination refers to this insertion target.');
        }
        if ($resolution instanceof DeclaredTable) {
            Check::input($name->name->value === $resolution->table->name->name->value, 'An insertion target names its resolved table.');
            Check::input($name->schema === null || $name->schema->value === $resolution->table->name->schema?->value, 'A qualified insertion target names its resolved schema.');
        }
    }

    /**
     * Writes the table and the explicit column list.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
        if ($this->columns !== null) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        }
    }
}
