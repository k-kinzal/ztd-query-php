<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to enable or disable a subscription.
 *
 * Rule: PG-SUBSCRIPTION-006. Mirrors `AlterSubscriptionStmt` of kind ALTER_SUBSCRIPTION_ENABLED.
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html. Status: Implemented.
 *
 * @visibility public
 * @example Disabling a subscription
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s DISABLE');
 *     $operation->statement->enable // => false
 */
final class EnableSubscription implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The subscription name
     * @param bool $enable Whether the subscription is enabled instead of disabled
     */
    public function __construct(public readonly Name $name, public readonly bool $enable)
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
        $out->keyword('ALTER', 'SUBSCRIPTION')->name($this->name, NameUse::Column)->keyword($this->enable ? 'ENABLE' : 'DISABLE');
    }
}
