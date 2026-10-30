<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Name;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Relates legacy definition-name qualifiers to the table being defined or changed.
 *
 * Different written table names, or different explicit database names, cannot
 * identify one definition target. Case-only differences remain conditional on
 * the context's name comparison. An omitted target database is resolved from
 * that context; it is not an intrinsic contradiction. Native MySQL 5.6 and 5.7
 * reject mismatches even in unexecuted stored-program branches.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/identifier-qualifiers.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnQualifiers
{
    /**
     * Excludes contradictions independently of the current database or case mode.
     *
     * @param list<Node> $parts The definition elements or alteration commands
     */
    public function check(QualifiedName $target, array $parts): void
    {
        Check::input($this->accepts($target, $parts), 'A definition name qualifier must identify the table of its statement.');
    }

    /**
     * Tells whether every column-name qualifier among the parts identifies the table of the statement.
     *
     * @param list<mixed> $parts
     */
    public function accepts(QualifiedName $target, array $parts): bool
    {
        $pending = $parts;
        while ($pending !== []) {
            $value = array_pop($pending);
            if ($value instanceof ColumnName) {
                if ($this->problem($value, $target) !== null) {
                    return false;
                }
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($value)));
            } elseif (is_array($value)) {
                array_push($pending, ...array_values($value));
            }
        }

        return true;
    }

    /**
     * Resolves a qualifier using explicit operands and, when supplied, a default database.
     */
    public function problem(ColumnName $name, QualifiedName $target, Comparison $comparison = Comparison::AsciiInsensitive, ?Name $database = null): ?MismatchedDefinitionName
    {
        $qualifier = $name->table;
        if ($qualifier === null) {
            return null;
        }
        $schema = $target->schema ?? $database;
        $mismatch = ($schema !== null && $qualifier->schema !== null && !$comparison->equal($schema->value, $qualifier->schema->value))
            || !$comparison->equal($target->name->value, $qualifier->name->value);
        return $mismatch ? new MismatchedDefinitionName($name, $target) : null;
    }
}
