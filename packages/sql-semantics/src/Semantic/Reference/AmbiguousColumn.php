<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Reference;

/**
 * More than one declared column has the requested name.
 * @example Reading semantic relationships
 *     $dialect = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite;
 *     $column = new \SqlSemantics\Semantic\Schema\Column(new \SqlSemantics\Semantic\Name('foo'), new \SqlSemantics\Core\Type\TypeDescriptor($dialect, 'integer'));
 *     $table = new \SqlSemantics\Semantic\Schema\Table($dialect, new \SqlSemantics\Semantic\QualifiedName(new \SqlSemantics\Semantic\Name('bar')), $column);
 *     $statement = (new \SqlSemantics\Facade\Semantics($dialect))->analyze('SELECT foo FROM bar a, bar b', [$table]);
 *     count($statement->field('foo')->expression->binding->matches) // => 2
 *
 * @visibility public
 */
final class AmbiguousColumn
{
    /**
     * @var non-empty-list<ResolvedColumn>
     */
    public readonly array $matches;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(ResolvedColumn $first, ResolvedColumn $second, ResolvedColumn ...$rest)
    {
        $this->matches = [$first, $second, ...array_values($rest)];
    }
}
