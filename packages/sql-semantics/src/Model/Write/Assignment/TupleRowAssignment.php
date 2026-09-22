<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Assignment;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Storage\Path;

/**
 * Assigns ordered row expressions to an explicit tuple of destinations.
 *
 * @visibility public
  * @example Inspecting TupleRowAssignment
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER DEFAULT 7,b INTEGER)'));
 *     $statement = $binder->bind('UPDATE t SET (a,b) = (DEFAULT,1)');
 *     $assignment = $statement->writes[0];
 *     $assignment instanceof \SqlSemantics\Model\Write\Assignment\TupleRowAssignment // => true
 */
final class TupleRowAssignment extends Assignment
{
    /**
     * @var non-empty-list<Path>
     */
    public readonly array $targets;

    /**
     * @param list<Path> $targets
     * @throws InvalidStructure
     */
    public function __construct(array $targets, public readonly \SqlSemantics\Model\Write\InputRow $row, Node $source)
    {
        parent::__construct($source);
        \SqlSemantics\Model\Validation\Collections::objects($targets, Path::class);
        if ($targets === []) {
            throw new InvalidStructure('A tuple assignment requires its destination list.');
        }
        foreach ($targets as $target) {
            if ($target->type()->dialect !== $row->dialect) {
                throw new InvalidStructure('A tuple assignment must use one dialect.');
            }
        }
        if (count($targets) !== count($row->items)) {
            throw new InvalidStructure('A tuple row assignment requires one value per destination.');
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
