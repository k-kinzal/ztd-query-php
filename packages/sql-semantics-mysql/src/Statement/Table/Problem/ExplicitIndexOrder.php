<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A modern input requests ordering from an index that cannot order key parts.
 *
 * The target and incompatible direction survive without an ordinary modern
 * index, parser tree or original SQL. No later operand is needed to diagnose
 * this intrinsic input condition, including in an unreachable stored branch.
 *
 * @visibility public
 * @example Describing an incompatible index direction
 *     $table = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText, \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending, $table))->message() // => 'Incorrect usage of spatial/fulltext/hash index and explicit index order'
 */
final class ExplicitIndexOrder implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<IndexOption> Complete trailing index options
     */
    public readonly array $options;

    /**
     * @var list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement> Completed earlier declarations in this table definition
     */
    public readonly array $elements;

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement> $elements Completed earlier declarations
     * @param Direction $direction The first incompatible direction
     * @param QualifiedName $table The table or enclosing program identity
     * @param list<IndexOption> $options The trailing index options
     */
    public function __construct(public readonly IndexKind $kind, public readonly Direction $direction, public readonly QualifiedName $table, public readonly ?IndexAlgorithm $algorithm = null, array $options = [], array $elements = [])
    {
        $this->elements = Check::listOf($elements, \SqlSemantics\Platform\MySql\Statement\Table\TableElement::class, 'An index condition retains its earlier table declarations.');
        $this->options = Check::listOf($options, IndexOption::class, 'An index condition retains its options.');
        Check::input((new IndexOrdering())->unordered($kind, $algorithm, $this->options), 'An index ordering condition requires an incompatible direction.');
    }

    /**
     * Describes the incompatible ordering request.
     */
    public function message(): string
    {
        return 'Incorrect usage of spatial/fulltext/hash index and explicit index order';
    }
}
