<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;

/**
 * A request to set parameters of a subscription, or to skip a remote transaction: `ALTER SUBSCRIPTION name { SET | SKIP } ( ... )`.
 *
 * Rule: PG-SUBSCRIPTION-002. Mirrors `AlterSubscriptionStmt` of kind
 * ALTER_SUBSCRIPTION_OPTIONS or ALTER_SUBSCRIPTION_SKIP.
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterSubscriptionOptions implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The parameters
     */
    public readonly array $options;

    /**
     * @param Name $name The subscription name
     * @param SubscriptionOptionAction $action Whether parameters are set or a transaction is skipped
     * @param list<Definition> $options The parameters; at least one
     */
    public function __construct(public readonly Name $name, public readonly SubscriptionOptionAction $action, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'ALTER SUBSCRIPTION names at least one parameter.', 1);
    }

    /**
     * Derives the parameter values.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'SUBSCRIPTION')->name($this->name, NameUse::Column)->keyword($this->action->value)->symbol('(')->list($this->options)->symbol(')');
    }
}
