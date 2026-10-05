<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to remove a subscription.
 *
 * Rule: PG-SUBSCRIPTION-007. Mirrors `DropSubscriptionStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-dropsubscription.html. Status: Implemented.
 *
 * @visibility public
 * @example Dropping a subscription if it exists
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP SUBSCRIPTION IF EXISTS s CASCADE');
 *     $operation->toString() // => 'DROP SUBSCRIPTION IF EXISTS s CASCADE'
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
