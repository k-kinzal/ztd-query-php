<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Override;
use PDO;
use PDOStatement;

/**
 * A statement that answers whatever is asked of it, without a driver behind it.
 *
 * What a driver answers about a statement — its error state, its attributes,
 * what it says about a column, whether another result follows — is a driver's
 * own business, and SQLite refuses most of it outright. This answers all of it,
 * so that a test can say what ZTD passes on rather than what SQLite supports.
 */
final class AnsweringPdoStatement extends PDOStatement
{
    /**
     * Builds a statement with no driver behind it.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     *
     * @return string The code a driver reports when nothing went wrong
     */
    #[Override]
    public function errorCode(): string
    {
        return '00000';
    }

    /**
     * {@inheritDoc}
     *
     * @return list<mixed> What a driver reports when nothing went wrong
     */
    #[Override]
    public function errorInfo(): array
    {
        return ['00000', null, null];
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed The one cursor kind this answers for every attribute
     */
    #[Override]
    public function getAttribute(int $name): mixed
    {
        return PDO::CURSOR_FWDONLY;
    }

    /**
     * {@inheritDoc}
     *
     * @return bool Always true, because nothing here refuses an attribute
     */
    #[Override]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @return array{name: string, len: int, flags: array<int, string>, precision: int<0, max>, pdo_type: 0|1|2|3|4|5|6|536870912|1073741824|2147483648} What a driver says about the one column this has
     */
    #[Override]
    public function getColumnMeta(int $column): array
    {
        return ['name' => 'id', 'len' => 0, 'flags' => [], 'precision' => 0, 'pdo_type' => PDO::PARAM_INT];
    }

    /**
     * {@inheritDoc}
     *
     * @return bool Always true, because there is always another result here
     */
    #[Override]
    public function nextRowset(): bool
    {
        return true;
    }
}
