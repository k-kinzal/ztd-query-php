<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

use Override;

/**
 * PostgreSQL persistence and table storage declarations.
 *
 * @visibility public
 */
final class PostgreSqlProperties implements Properties
{
    /**
     * @param list<\SqlSemantics\Schema\Storage\Parameter> $storageParameters
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly Persistence $persistence = Persistence::Permanent,
        public readonly CommitAction $onCommit = CommitAction::PreserveRows,
        public readonly ?string $accessMethod = null,
        public readonly ?string $tablespace = null,
        public readonly array $storageParameters = [],
    ) {
    }
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::PostgreSql;
    }
}
