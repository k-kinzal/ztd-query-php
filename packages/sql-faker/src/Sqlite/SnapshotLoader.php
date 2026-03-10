<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SnapshotLoader as SnapshotLoaderContract;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;

final class SnapshotLoader implements SnapshotLoaderContract
{
    public function __construct(
        private readonly ?string $version = null,
    ) {
    }

    public function load(): Grammar
    {
        return ContractGrammarProjector::project(SqliteGrammar::load($this->version), NonTerminal::class);
    }
}
