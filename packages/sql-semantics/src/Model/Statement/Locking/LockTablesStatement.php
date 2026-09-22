<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Locking;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Locking\MySqlTableLock;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Acquires MySQL session table locks with an independent access mode for each occurrence.
 * @visibility public
 * @example Inspecting session table locks
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('LOCK TABLES t AS a READ, t AS b WRITE');
 *     count($statement->locks) // => 2
 */
final class LockTablesStatement extends BoundStatement
{
    /**
     * @var non-empty-list<MySqlTableLock> Ordered table access requests
     */
    public readonly array $locks;

    /**
     * @param list<MySqlTableLock> $locks Each occurrence has a mandatory table and mode
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $locks)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Session table locks require MySQL.');
        }
        Collections::objects($locks, MySqlTableLock::class);
        $this->locks = Collections::nonEmpty($locks);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Lock;
    }

    /**
     * Retains each table's access mode while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->locks);
    }

    /**
     * @param non-empty-list<MySqlTableLock> $locks Replacement lock requests
     */
    public function withLocks(array $locks): self
    {
        return $this->changed(new self($this->origin, $locks));
    }
}
