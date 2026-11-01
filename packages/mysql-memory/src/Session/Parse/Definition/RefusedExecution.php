<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable;

/**
 * Carries a definition the analysis refuses from the input boundary to its execution.
 *
 * The server executes such a definition only to refuse it, but in its own order: the tables
 * the definition reads are opened, its options and the database and the existing table are
 * checked first. This control transfer is neither a SQL error nor a semantic value.
 *
 * @visibility MySqlMemory
 */
final class RefusedExecution extends RuntimeException
{
    /**
     * @param RefusedCreateTable $definition The refused definition and its written problem
     */
    public function __construct(public readonly RefusedCreateTable $definition)
    {
        parent::__construct($definition->message());
    }
}
