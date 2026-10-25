<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
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
     * The fully qualified declaration identity, absent for unresolved destinations.
     * The written name remains independent of this binding.
     */
    public readonly ?QualifiedName $resolvedName;

    /**
     * @param Name|null $declarationSchema The schema assigned to an unqualified declaration
     * @param Comparison $comparison The name comparison used for this binding
     * @param list<DestinationColumn>|null $columns The optional written destination list
     */
    public function __construct(public readonly QualifiedName $name, public readonly TableResolution $resolution, ?array $columns = null, public readonly ?Span $source = null, ?Name $declarationSchema = null, Comparison $comparison = Comparison::Sensitive)
    {
        Check::input($name->catalog === null, 'An INSERT table name has at most a database qualifier.');
        $this->columns = $columns === null ? null : Check::listOf($columns, DestinationColumn::class, 'INSERT destinations are column references.');
        foreach ($this->columns ?? [] as $column) {
            Check::input($column->table === $resolution, 'Every destination refers to this insertion target.');
        }
        if ($resolution instanceof DeclaredTable) {
            $schema = $resolution->table->name->schema ?? $declarationSchema;
            Check::input($schema !== null, 'A resolved insertion target identifies its declaration schema.');
            Check::input($comparison->equal($name->name->value, $resolution->table->name->name->value), 'An insertion target names its resolved table.');
            Check::input($name->schema === null || $comparison->equal($name->schema->value, $schema->value), 'A qualified insertion target names its resolved schema.');
            $this->resolvedName = new QualifiedName($resolution->table->name->name, $schema);
        } else {
            $this->resolvedName = null;
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
