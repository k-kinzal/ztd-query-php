<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SnapshotLoader as SnapshotLoaderContract;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\PostgreSql\Grammar\PgGrammar;

final class SnapshotLoader implements SnapshotLoaderContract
{
    public function __construct(
        private readonly ?string $version = null,
    ) {
    }

    public function load(): Grammar
    {
        return ContractGrammarProjector::project(PgGrammar::load($this->version), NonTerminal::class);
    }
}
