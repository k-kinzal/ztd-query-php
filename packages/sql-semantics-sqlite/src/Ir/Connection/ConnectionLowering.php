<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Connection;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\MaintenanceLookup;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Attach as ParsedAttach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Detach as ParsedDetach;

/**
 * Publishes the native declaration or resolves an existing attachment.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ConnectionLowering
{
    /**
     * Resolves the selected connection command and its typed operands.
     * @throws InvalidConstruction When a constant detach target or operand cannot be established
     */
    public function lower(BoundStatement $operation, ParsedAttach|ParsedDetach $statement): Attach|Detach
    {
        $operands = new ConnectionOperands();
        $name = $operands->name($operation, $statement->schema);
        if ($statement instanceof ParsedAttach) {
            $file = $operands->row($operation, $statement->file);
            $key = $statement->key === null ? null : $operands->row($operation, $statement->key);

            return new Attach($file, $name, $key);
        }
        if ($name instanceof Parameter) {
            return new Detach($name);
        }
        $scope = (new MaintenanceLookup())->scopes($operation->context, new Name($name->name->value))[0];

        return new Detach(new DetachTarget(new Identifier($scope->value), $name));
    }
}
