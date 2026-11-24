<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An absent trigger and the verification scope of its guarded statement.
 *
 * @visibility public
 * @example Representing a checked absence in one schema
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('audit_orders');
 *     $schema = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main');
 *     $scope = \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerVerification::KnownSchema;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\MissingTrigger($name, $scope, $schema))->quoted() // => '"main"."audit_orders"'
 */
final class MissingTrigger
{
    use Snapshot;

    /**
     * Checks that the qualifier matches the resolved kind of schema verification.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly TriggerVerification $verification = TriggerVerification::SearchPath,
        public readonly ?Identifier $schema = null,
    ) {
        Check::input(($schema === null) === ($verification === TriggerVerification::SearchPath), 'Trigger verification requires an exact qualifier only for a qualified scope.');
        Check::input(
            $verification !== TriggerVerification::UnknownSchema || ($schema !== null && $schema->key() !== 'main' && $schema->key() !== 'temp'),
            'The main and temp trigger schemas are always known.'
        );
    }

    /**
     * Emits the absent selector without changing its verification scope.
     */
    public function quoted(): string
    {
        return ($this->schema === null ? '' : $this->schema->quoted() . '.') . $this->name->quoted();
    }
}
