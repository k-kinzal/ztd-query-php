<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\IndexAlgorithm;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops one MySQL index from its required owning table.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DROP INDEX ix ON t ALGORITHM=INPLACE LOCK=NONE', strict: false);
 *     $statement->toString() // => 'DROP INDEX `ix` ON `t` ALGORITHM = INPLACE LOCK = NONE'
 *
 * @visibility public
 */
final class DropTableIndexStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly QualifiedName $table, public readonly IndexAlgorithm $algorithm = IndexAlgorithm::Default, public readonly IndexLock $lock = IndexLock::Default)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Dropping an index by table requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the drop operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->table, $this->algorithm, $this->lock);
    }
}
