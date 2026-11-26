<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument\NativePragmaInteger;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Resolves SQLite's two independent reclamation argument conventions.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ReclaimPragmaLowering
{
    /**
     * Resolves the schema and preserves an explicit request even when it decodes to None.
     * @throws InvalidConstruction When the qualifier does not identify a known database
     */
    public function automatic(BoundStatement $operation, Pragma $statement): AutoVacuum
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main');
        $value = $statement->value;
        if ($value === null) {
            return new AutoVacuum($schema);
        }
        $text = $value instanceof PragmaNumber ? $value->nativeText() : $value->value;
        $mode = match (Comparison::AsciiInsensitive->fold($text)) {
            'none' => VacuumMode::None,
            'full' => VacuumMode::Full,
            'incremental' => VacuumMode::Incremental,
            default => VacuumMode::tryFrom((string) (new NativePragmaInteger())->parse($text)) ?? VacuumMode::None,
        };

        return new AutoVacuum($schema, $mode);
    }

    /**
     * Resolves the schema and the effective positive cap.
     * @throws InvalidConstruction When the qualifier does not identify a known database
     */
    public function incremental(BoundStatement $operation, Pragma $statement): IncrementalVacuum
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main');
        $value = $statement->value;
        $text = $value instanceof PragmaNumber ? $value->nativeText() : ($value->value ?? '');
        $limit = (new NativePragmaInteger())->parse($text);

        return new IncrementalVacuum($schema, new PageReclaimLimit($limit !== null && $limit > 0 ? $limit : PageReclaimLimit::MAXIMUM));
    }
}
