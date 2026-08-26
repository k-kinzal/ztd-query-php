<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Shadow\ShadowTransactions;

/**
 * A structure-aware transaction-control statement.
 */
final class TransactionStatement
{
    /**
     * Binds a statement to what it does, and to the savepoint it names.
     *
     * Every way of building one goes through a named constructor, because which
     * operation it is decides whether a name means anything.
     */
    public function __construct(
        private readonly TransactionOperation $operation,
        private readonly string $savepointName = '',
    ) {
    }

    /**
     * Begin.
     *
     * @return self
     */
    public static function begin(): self
    {
        return new self(TransactionOperation::Begin);
    }

    /**
     * Commit.
     *
     * @return self
     */
    public static function commit(): self
    {
        return new self(TransactionOperation::Commit);
    }

    /**
     * Rollback.
     *
     * @return self
     */
    public static function rollback(): self
    {
        return new self(TransactionOperation::Rollback);
    }

    /**
     * Savepoint.
     *
     * @param string $name
     * @return self
     */
    public static function savepoint(string $name): self
    {
        return new self(TransactionOperation::Savepoint, self::requiredName($name));
    }

    /**
     * Rollback to.
     *
     * @param string $name
     * @return self
     */
    public static function rollbackTo(string $name): self
    {
        return new self(TransactionOperation::RollbackTo, self::requiredName($name));
    }

    /**
     * Release.
     *
     * @param string $name
     * @return self
     */
    public static function release(string $name): self
    {
        return new self(TransactionOperation::Release, self::requiredName($name));
    }

    /**
     * Applies.
     *
     * @param ShadowTransactions $transactions
     */
    public function apply(ShadowTransactions $transactions): void
    {
        match ($this->operation) {
            TransactionOperation::Begin => $transactions->begin(),
            TransactionOperation::Commit => $transactions->commit(),
            TransactionOperation::Rollback => $transactions->rollBack(),
            TransactionOperation::Savepoint => $transactions->savepoint($this->savepointName),
            TransactionOperation::RollbackTo => $transactions->rollBackTo($this->savepointName),
            TransactionOperation::Release => $transactions->release($this->savepointName),
        };
    }

    /**
     * Answers a savepoint name, refusing one the statement never gave.
     *
     * @param string $name Name the statement carried
     *
     * @return string The same name, once it is one
     *
     * @throws InvalidDefinitionException When the statement named no savepoint
     */
    public static function requiredName(string $name): string
    {
        if ($name === '') {
            throw new InvalidDefinitionException('Savepoint name must not be empty.');
        }

        return $name;
    }
}
