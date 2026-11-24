<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;

/**
 * Publishes a native flag request with its resolved operation, value and schema.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class FlagPragmaLowering
{
    /**
     * Binds the exact built-in name and the optional explicit database.
     * @throws InvalidConstruction When no flag rule or schema matches the parsed operands
     */
    public function lower(BoundStatement $operation, Pragma $statement): FlagPragma
    {
        $qualified = $statement->name;
        $name = FlagName::tryFrom(Comparison::AsciiInsensitive->fold($qualified->name->value));
        Check::input($name !== null, 'This SQLite PRAGMA name has no complete flag representation.');
        $schema = $qualified->schema === null ? null : (new MaintenanceLookup())->scopes($operation->context, $qualified->schema)[0];

        return new FlagPragma($name, (new FlagArgument())->change($name, $statement->value), $schema === null ? null : new Identifier($schema->value));
    }
}
