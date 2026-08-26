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
    private function __construct(
        private readonly TransactionOperation $operation,
        private readonly string $savepointName = '',
    ) {
    }

    public static function begin(): self
    {
        return new self(TransactionOperation::Begin);
    }

    public static function commit(): self
    {
        return new self(TransactionOperation::Commit);
    }

    public static function rollback(): self
    {
        return new self(TransactionOperation::Rollback);
    }

    public static function savepoint(string $name): self
    {
        return new self(TransactionOperation::Savepoint, self::requiredName($name));
    }

    public static function rollbackTo(string $name): self
    {
        return new self(TransactionOperation::RollbackTo, self::requiredName($name));
    }

    public static function release(string $name): self
    {
        return new self(TransactionOperation::Release, self::requiredName($name));
    }

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
     * @throws InvalidDefinitionException When the statement named no savepoint
     */
    private static function requiredName(string $name): string
    {
        if ($name === '') {
            throw new InvalidDefinitionException('Savepoint name must not be empty.');
        }

        return $name;
    }
}
