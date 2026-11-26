<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * A request to remove a subscription.
 *
 * Rule: PG-SUBSCRIPTION-007. Mirrors `DropSubscriptionStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-dropsubscription.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropSubscription implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The subscription name
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior The drop behavior, when written
     */
    public function __construct(public readonly Name $name, public readonly bool $ifExists = false, public readonly ?DropBehavior $behavior = null)
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
        $out->keyword('DROP', 'SUBSCRIPTION');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Column);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
