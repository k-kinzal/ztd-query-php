<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Plan;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL `EXPLAIN ... FOR DATABASE name statement` (8.2 and later): the plan of a query or data change whose unqualified names resolve in the named database instead of the current one.
 *
 * @visibility public
 * @example Reading the database the explained statement resolves in
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
 *     $statement = $binder->bind('EXPLAIN FOR DATABASE sales SELECT 1', strict: false);
 *     [$statement->database, $statement->statement->kind->value] // => ['sales', 'SELECT']
 */
final class ExplainInDatabaseStatement extends BoundStatement
{
    /**
     * @param string $database Database in which unqualified names of the explained statement resolve
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $database, public readonly BoundStatement $statement, public readonly MySqlPlan $options = new MySqlPlan())
    {
        ReplicationRelease::require($origin, 'EXPLAIN FOR DATABASE', 80200);
        if ($database === '' || $statement->origin->dialect !== $origin->dialect) {
            throw new InvalidStructure('EXPLAIN FOR DATABASE names a database and explains a MySQL statement.');
        }
        if (!$statement instanceof \SqlSemantics\Model\BoundQuery && !in_array($statement->kind, [StatementKind::Select, StatementKind::Insert, StatementKind::Replace, StatementKind::Update, StatementKind::Delete], true)) {
            throw new InvalidStructure('EXPLAIN FOR DATABASE explains a query, INSERT, REPLACE, UPDATE or DELETE.');
        }
        parent::__construct($origin);
    }

    /**
     * Returns the fixed statement category.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Explain;
    }

    /**
     * Retains the operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->database, $this->statement, $this->options);
    }

    /**
     * Replaces the database the explained statement resolves in.
     * @throws InvalidStructure
     */
    public function withDatabase(string $database): self
    {
        return $this->changed(new self($this->origin, $database, $this->statement, $this->options));
    }

    /**
     * Replaces the plan options.
     * @throws InvalidStructure
     */
    public function withOptions(MySqlPlan $options): self
    {
        return $this->changed(new self($this->origin, $this->database, $this->statement, $options));
    }
}
