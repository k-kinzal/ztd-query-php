<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * A request to change the connection string of a subscription.
 *
 * Rule: PG-SUBSCRIPTION-003. Mirrors `AlterSubscriptionStmt` of kind ALTER_SUBSCRIPTION_CONNECTION.
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
