<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to fetch the current table list of the subscribed publications: `ALTER SUBSCRIPTION name REFRESH PUBLICATION`.
 *
 * Rule: PG-SUBSCRIPTION-004. Mirrors `AlterSubscriptionStmt` of kind ALTER_SUBSCRIPTION_REFRESH.
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html. Status: Implemented.
 *
 * @visibility public
 * @example Refreshing without copying data
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s REFRESH PUBLICATION WITH (copy_data = false)');
 *     $operation->toString() // => 'ALTER SUBSCRIPTION s REFRESH PUBLICATION WITH (copy_data = FALSE)'
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
