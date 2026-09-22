<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Assignment;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Storage\Path;

/**
 * Assigns ordered query outputs to an explicit tuple of destinations.
 *
 * @visibility public
  * @example Inspecting TupleQueryAssignment
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UPDATE t SET (id,n)=(SELECT n,id FROM t) RETURNING id');
 *     $statement->writes[0] instanceof \SqlSemantics\Model\Write\Assignment\TupleQueryAssignment // => true
 */
final class TupleQueryAssignment extends Assignment
{
    /**
     * @var non-empty-list<Path>
     */
    public readonly array $targets;

    /**
     * @param list<Path> $targets
     * @throws InvalidStructure
     */
    public function __construct(array $targets, public readonly \SqlSemantics\Model\BoundQuery $query, Node $source)
    {
        parent::__construct($source);
        \SqlSemantics\Model\Validation\Collections::objects($targets, Path::class);
        if ($targets === []) {
            throw new InvalidStructure('A tuple assignment requires its destination list.');
        }
        foreach ($targets as $target) {
            if ($target->type()->dialect !== $query->origin->dialect) {
                throw new InvalidStructure('A tuple assignment must use one dialect.');
            }
        }
        $width = \SqlSemantics\Model\Validation\RowShape::width($query);
        if ($width !== null && count($targets) !== $width) {
            throw new InvalidStructure('A tuple query assignment requires one result column per destination.');
        }
        $this->targets = $targets;
    }

    /**
     * @return non-empty-list<Path>
     */
    #[Override]
    public function destinations(): array
    {
        return $this->targets;
    }
}
