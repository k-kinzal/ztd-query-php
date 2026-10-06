<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\NamedRelation;

/**
 * Reads a table name into the schema and the table the catalog stores.
 *
 * The name is read as the relation of a TABLE statement, so a qualifier is
 * told apart from a dot written inside a quoted name, and an unquoted name is
 * folded to lower case the way the server folds it before the catalog sees
 * it. A name that does not read as one table is left as it was given.
 *
 * @visibility root
 */
final class QualifiedName
{
    /**
     * Reads names with the grammar of the analysis.
     */
    public function __construct(private readonly Semantics $semantics)
    {
    }

    /**
     * Returns the schema, public when the name has none, and the table.
     *
     * @return array{string, string}
     */
    public function split(string $tableName): array
    {
        $name = $this->read($tableName);

        return $name === null ? ['public', $tableName] : [$name->schema->value ?? 'public', $name->name->value];
    }

    /**
     * Returns the name the text spells as one relation, or null when it spells none.
     */
    public function read(string $tableName): ?\SqlSemantics\Statement\Identifier\QualifiedName
    {
        try {
            $operations = $this->semantics->analyzeAll('TABLE ' . $tableName);
        } catch (AnalysisException) {
            return null;
        }
        $input = count($operations) === 1 ? $operations[0]->inputRelation() : null;

        return $input instanceof NamedRelation ? $input->name() : null;
    }
}
