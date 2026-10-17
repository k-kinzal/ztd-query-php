<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable as ShowStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Builds the SHOW CREATE TABLE statement for a table name.
 *
 * A name that reads as one table reference, such as `db.users` or a quoted
 * name, is kept as written. Any other text is taken as the name of one table,
 * and the statement is built from that name, so it is quoted as needed and
 * can never add a second statement.
 *
 * @visibility root
 */
final class ShowCreateTable
{
    private Semantics $semantics;

    /**
     * Loads the grammar of the release once for every name the builder will read.
     */
    public function __construct(?string $version = null)
    {
        $this->semantics = new Semantics(Dialect::MySql, $version);
    }

    /**
     * Returns the statement that reads the definition of the named table.
     */
    public function statement(string $tableName): string
    {
        return ($this->written($tableName) ?? $this->built($tableName))->toString();
    }

    /**
     * Returns the statement for a name that reads as one table reference, or null when it does not.
     */
    public function written(string $tableName): ?Operation
    {
        try {
            $operations = $this->semantics->analyzeAll('SHOW CREATE TABLE ' . $tableName);
        } catch (AnalysisException) {
            return null;
        }

        return count($operations) === 1 && $operations[0]->statement instanceof ShowStatement ? $operations[0] : null;
    }

    /**
     * Returns the statement for a table whose whole name is the given text.
     */
    public function built(string $tableName): Operation
    {
        return new Operation($this->semantics->context([]), new ShowStatement(new InspectedTable(new QualifiedName(new Name($tableName)))));
    }
}
