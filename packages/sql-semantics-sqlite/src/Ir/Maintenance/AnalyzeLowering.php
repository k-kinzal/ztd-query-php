<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze as ParsedAnalyze;

/**
 * Resolves native ANALYZE schema, index and relation selectors.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class AnalyzeLowering
{
    /**
     * Emits a completed statistics selector with native name precedence.
     *
     * @throws InvalidConstruction When the static selector cannot be resolved
     */
    public function lower(BoundStatement $operation, ParsedAnalyze $statement): Analyze
    {
        $target = $statement->target;
        if ($target === null) {
            return new Analyze();
        }
        $context = $operation->context;
        $lookup = new MaintenanceLookup();
        if ($target->schema === null) {
            $schema = $lookup->schema($target->name, $lookup->path($context));
            if ($schema !== null) {
                return new Analyze(new Identifier($schema->value));
            }
        }
        $scopes = $lookup->scopes($context, $target->schema);
        $selected = $lookup->index($context, $target, $scopes) ?? $lookup->relation($context, $target, $scopes);
        Check::input($selected !== null, 'ANALYZE requires a resolved schema, relation or index.');

        return new Analyze($selected);
    }
}
