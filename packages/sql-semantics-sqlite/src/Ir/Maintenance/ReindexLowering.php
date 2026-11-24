<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex as ParsedReindex;

/**
 * Resolves native SQLite rebuild selection using the collation, relation and index namespaces.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ReindexLowering
{
    /**
     * Publishes a resolved rebuild or refuses an unestablished named selector.
     *
     * @throws InvalidConstruction When native name precedence cannot be established
     */
    public function lower(BoundStatement $operation, ParsedReindex $statement): Reindex
    {
        $target = $statement->target;
        if ($target === null) {
            return new Reindex();
        }
        if ($target->schema === null) {
            $collation = Collation::tryFrom(strtr($target->name->value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
            if ($collation !== null) {
                return new Reindex($collation);
            }
        }
        $context = $operation->context;
        $lookup = new MaintenanceLookup();
        $scopes = $lookup->scopes($context, $target->schema);
        $selected = $lookup->relation($context, $target, $scopes) ?? $lookup->index($context, $target, $scopes);
        Check::input($selected !== null, 'REINDEX requires a resolved collation, relation or index.');

        return new Reindex($selected);
    }
}
