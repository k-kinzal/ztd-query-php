<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the shared target scope of mutation output fields.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ReturningLayout
{
    /**
     * Checks the size limit and each declared slot.
     *
     * @param list<ReturnedColumn> $fields An already owned list of fields
     */
    public function check(TableSource $target, array $fields): void
    {
        Check::input(count($fields) <= 1024, 'A mutation can return at most 1024 fields.');
        $scope = new OccurrenceScope();
        foreach ($fields as $field) {
            $scope->column($field->reference, [$target], 'A returned slot must belong to its target.');
        }
    }
}
