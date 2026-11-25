<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A checked projection over the immediately enclosing local input.
 *
 * @visibility public
 * @example Projecting a slot from a derived query
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Identifier('a');
 *     $column = new \SqlSemantics\Platform\MySql\Ir\ResultColumn(new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('1'), $name);
 *     $definition = new \SqlSemantics\Platform\MySql\Ir\Select([$column]);
 *     (new \SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection($definition, [new \SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn(0, $name)]))->columns[0]->name->value // => 'a'
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
     * @param bool $inheritsRequestedAliases Uses the checked native heading on MySQL 5.6 or 5.7
     */
    public function __construct(
        public readonly Select|self|WithProjection $definition,
        array $columns,
        public readonly bool $inheritsRequestedAliases = false,
    ) {
        (new LocalProjectionAliases())->checkDerived($definition, $inheritsRequestedAliases);
        Check::input(!$definition instanceof Select || ($definition->orderBy === [] && $definition->limit === null), 'The local definition requires an unbounded projection without ordering.');
        $layers = 1;
        for ($ancestor = $definition; $ancestor instanceof self; $ancestor = $ancestor->definition) {
            Check::input(++$layers <= 16, 'A local projection has at most sixteen definition boundaries.');
        }
        $checked = Check::listOf($columns, LocalResultColumn::class, 'A local projection needs a nonempty ordered result.', 1);
        Check::input(count($checked) <= 1024, 'A local projection can have at most 1024 columns.');
        $width = $definition instanceof WithProjection ? $definition->width() : count($definition->columns);
        foreach ($checked as $column) {
            Check::input($column->slot < $width, 'The local slot must belong to the definition.');
            if ($inheritsRequestedAliases && $definition instanceof Select) {
                $name = $definition->columns[$column->slot]->name;
                Check::input(
                    !$name instanceof RequestedResultAlias || (new NativeLocalNames())->readable($name->requested),
                    'An inherited local field needs a complete UTF-8 name.'
                );
            }
            Check::input($column->name->value === ltrim($column->name->value, " \t\r\n\v\f"), 'A MySQL result name cannot start with SQL whitespace.');
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
