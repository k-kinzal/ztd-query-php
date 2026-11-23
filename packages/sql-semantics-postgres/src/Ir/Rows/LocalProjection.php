<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A checked projection over the immediately enclosing local input.
 *
 * @visibility public
 * @example Projecting a slot from a derived query
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('a');
 *     $column = new \SqlSemantics\Platform\PostgreSql\Ir\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('1'), $name);
 *     $definition = new \SqlSemantics\Platform\PostgreSql\Ir\Select([$column]);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection($definition, [new \SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn(0, $name)]))->columns[0]->name->value // => 'a'
 */
final class LocalProjection implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<LocalResultColumn> Outer fields in result order
     */
    public readonly array $columns;

    /**
     * Closes every slot reference over the corresponding independent definition.
     *
     * @param list<LocalResultColumn> $columns The outer result positions
     */
    public function __construct(public readonly Select|ValuesQuery|WithProjection|self $definition, array $columns)
    {
        Check::input(!$definition instanceof Select || ($definition->orderBy === [] && $definition->limit === null), 'The local definition requires an unbounded projection without ordering.');
        $layers = 1;
        for ($ancestor = $definition; $ancestor instanceof self; $ancestor = $ancestor->definition) {
            Check::input(++$layers <= 16, 'A local projection has at most sixteen definition boundaries.');
        }
        $checked = Check::listOf($columns, LocalResultColumn::class, 'A local projection needs a nonempty ordered result.', 1);
        Check::input(count($checked) <= 1024, 'A local projection can have at most 1024 columns.');
        foreach ($checked as $column) {
            $exists = match (true) {
                $definition instanceof ValuesQuery => isset($definition->rows[0]->cells[$column->slot]),
                $definition instanceof WithProjection => $column->slot < $definition->width(),
                $definition instanceof Select, $definition instanceof self => isset($definition->columns[$column->slot]),
            };
            Check::input($exists, 'The local slot must belong to the definition.');
        }
        $this->columns = $checked;
    }

    /**
     * Identifies the shared native profile of the definition and its consumer.
     */
    public function profileId(): string
    {
        return $this->definition->profileId();
    }

    /**
     * Emits the independent source and the explicit local slot reads.
     */
    public function toString(): string
    {
        return (new LocalProjectionSql())->render($this);
    }
}
