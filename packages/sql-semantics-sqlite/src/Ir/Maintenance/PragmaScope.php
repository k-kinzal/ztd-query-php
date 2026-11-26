<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;

/**
 * Binds the exact native database qualifier of a SQLite pragma.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PragmaScope
{
    /**
     * Resolves an explicit database alias or leaves implicit native access in effect.
     * @throws InvalidConstruction When the explicit schema cannot be established
     */
    public function resolve(AnalysisContext $context, ?Name $qualifier): ?Identifier
    {
        if ($qualifier === null) {
            return null;
        }
        $schema = (new MaintenanceLookup())->scopes($context, $qualifier)[0];

        return new Identifier($schema->value);
    }
}
