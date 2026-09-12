<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\Upsert;

use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Shadow\Mutation\UpsertMutationRow;

/**
 * Adds evaluated UPSERT assignments and its optional predicate to result rows.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class MetadataColumns
{
    /**
     * Use the same identifier and expression bindings as the surrounding projection.
     */
    public function __construct(private IdentifierQuoter $quoter, private ExpressionBinder $binder)
    {
    }

    /**
     * @param list<string> $tableColumns
     * @param array<string, string> $assignments
     * @return list<string>
     */
    public function render(string $tableName, array $tableColumns, array $assignments, string $conflict, ?string $predicate, ?string $incomingNamespace): array
    {
        $selects = [];
        $table = $this->quoter->quote($tableName);
        $existingAlias = $this->quoter->quote(ExpressionBinder::EXISTING_ALIAS);
        $codec = new UpsertMutationRow();
        foreach (array_values($assignments) as $index => $expression) {
            $evaluated = $this->binder->bindExpression(
                $expression,
                $tableName,
                $tableColumns,
                incomingNamespace: $incomingNamespace,
            );
            $metadata = $this->quoter->quote($codec->valueColumn($index));
            $selects[] = "(SELECT $evaluated FROM $table AS $existingAlias WHERE $conflict LIMIT 1) AS $metadata";
        }
        if ($predicate !== null) {
            $evaluated = $this->binder->bindExpression(
                $predicate,
                $tableName,
                $tableColumns,
                incomingNamespace: $incomingNamespace,
            );
            $metadata = $this->quoter->quote($codec->predicateColumn());
            $selects[] = "(SELECT $evaluated FROM $table AS $existingAlias WHERE $conflict LIMIT 1) AS $metadata";
        }

        return $selects;
    }
}
