<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change the connection string of a subscription.
 *
 * Rule: PG-SUBSCRIPTION-003. Mirrors `AlterSubscriptionStmt` of kind ALTER_SUBSCRIPTION_CONNECTION.
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the new connection string
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER SUBSCRIPTION s CONNECTION 'host=b'");
 *     $operation->statement->connection->value // => 'host=b'
 */
final class AlterSubscriptionConnection implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The subscription name
     * @param StringConstant $connection The connection string
     */
    public function __construct(public readonly Name $name, public readonly StringConstant $connection)
    {
    }

    /**
     * Derives nothing: subscriptions are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'SUBSCRIPTION')->name($this->name, NameUse::Column)->keyword('CONNECTION')->node($this->connection);
    }
}
