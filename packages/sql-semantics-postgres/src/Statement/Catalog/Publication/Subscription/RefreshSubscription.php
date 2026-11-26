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
 * A request to fetch the current table list of the subscribed publications: `ALTER SUBSCRIPTION name REFRESH PUBLICATION`.
 *
 * Rule: PG-SUBSCRIPTION-004. Mirrors `AlterSubscriptionStmt` of kind ALTER_SUBSCRIPTION_REFRESH.
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RefreshSubscription implements Statement
{
    use Snapshot;

    /**
     * @var list<Definition> The refresh options
     */
    public readonly array $options;

    /**
     * @param Name $name The subscription name
     * @param list<Definition> $options The refresh options
     */
    public function __construct(public readonly Name $name, array $options = [])
    {
        $this->options = Check::listOf($options, Definition::class, 'Refresh options are definitions.');
    }

    /**
     * Derives the option values.
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
        $out->keyword('ALTER', 'SUBSCRIPTION')->name($this->name, NameUse::Column)->keyword('REFRESH', 'PUBLICATION');
        if ($this->options !== []) {
            $out->keyword('WITH')->symbol('(')->list($this->options)->symbol(')');
        }
    }
}
