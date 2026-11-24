<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum;

use SqlSemantics\Platform\Sqlite\Ir\Expression\Binding\NumberedRegistrations;
use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum as ParsedVacuum;

/**
 * Publishes the resolved VACUUM scope and its actual output role.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class VacuumLowering
{
    /**
     * Binds the database and, when relevant, the destination expression.
     * @throws InvalidConstruction When the database, expression or binding cannot be represented
     */
    public function lower(BoundStatement $operation, ParsedVacuum $statement): Vacuum
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->schema) ?? new Identifier('main');
        $into = $statement->into;
        if ($into === null) {
            return new Vacuum($schema);
        }
        if ($statement->ignoresDestination()) {
            $bindings = (new NumberedRegistrations())->collect($into, 'VACUUM temp requires numbered bindings.');

            return new Vacuum($schema, $bindings === [] ? null : new VacuumRegistrations($bindings));
        }
        $value = (new ExpressionLowering())->lower($into, new SourceBinding($operation, null));

        return new Vacuum($schema, new VacuumDestination(new ValueRow([$value])));
    }
}
