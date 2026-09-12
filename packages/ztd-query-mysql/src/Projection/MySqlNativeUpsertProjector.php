<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Sql\SqlLexerProfile;

/**
 * Implements the My Sql Native Upsert Projector contract for MySQL.
 */
final class MySqlNativeUpsertProjector
{
    private readonly IdentifierQuoter $quoter;

    private readonly SqlLexerProfile $lexerProfile;


    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct()
    {
        $this->quoter = new MySqlIdentifierQuoter();
        $this->lexerProfile = MySqlLexerProfile::create();
    }

    /**
     * @param list<string> $tableColumns
     * @param array<string, array<int, string>> $candidateKeys
     * @param array<string, string> $assignments
     */
    public function project(
        string $incomingSql,
        string $tableName,
        array $tableColumns,
        array $candidateKeys,
        array $assignments,
        ?string $predicate = null,
        ?string $conflictPredicate = null,
        ?string $incomingNamespace = null,
    ): string {
        if ($assignments === [] || $candidateKeys === []) {
            return $incomingSql;
        }

        $incomingAlias = $this->quoter->quote(Projection\Upsert\ExpressionBinder::INCOMING_ALIAS);
        $existingAlias = $this->quoter->quote(Projection\Upsert\ExpressionBinder::EXISTING_ALIAS);
        $conflict = (new Projection\Upsert\ConflictPredicate($this->quoter))->conflictPredicate($candidateKeys, $existingAlias, $incomingAlias);
        if ($conflictPredicate !== null) {
            $existingPredicate = (new Projection\Upsert\ExpressionBinder($this->quoter, $this->lexerProfile))->bindExpression(
                $conflictPredicate,
                $tableName,
                $tableColumns,
                Projection\Upsert\ExpressionBinder::EXISTING_ALIAS,
                $incomingNamespace,
            );
            $incomingPredicate = (new Projection\Upsert\ExpressionBinder($this->quoter, $this->lexerProfile))->bindExpression(
                $conflictPredicate,
                $tableName,
                $tableColumns,
                Projection\Upsert\ExpressionBinder::INCOMING_ALIAS,
                $incomingNamespace,
            );
            $conflict = "($conflict AND ($existingPredicate) AND ($incomingPredicate))";
        }
        $selects = [];
        foreach ($tableColumns as $column) {
            $quoted = $this->quoter->quote($column);
            $selects[] = "$incomingAlias.$quoted AS $quoted";
        }

        $metadata = new Projection\Upsert\MetadataColumns($this->quoter, new Projection\Upsert\ExpressionBinder($this->quoter, $this->lexerProfile));
        array_push($selects, ...$metadata->render($tableName, $tableColumns, $assignments, $conflict, $predicate, $incomingNamespace));

        return 'SELECT ' . implode(', ', $selects) . " FROM ($incomingSql) AS $incomingAlias";
    }

}
