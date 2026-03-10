<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SnapshotLoader as SnapshotLoaderContract;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\MySql\Grammar\Grammar as SourceGrammar;
use SqlFaker\MySql\Grammar\NonTerminal;

final class SnapshotLoader implements SnapshotLoaderContract
{
    public function __construct(
        private readonly ?string $version = null,
    ) {
    }

    public function load(): Grammar
    {
        return ContractGrammarProjector::project(SourceGrammar::load($this->version), NonTerminal::class);
    }
}
