<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a subscription to publications of another server.
 *
 * Rule: PG-SUBSCRIPTION-001. Mirrors `CreateSubscriptionStmt`: name,
 * connection string, publication names on the publisher and parameters.
 * Source: https://www.postgresql.org/docs/17/sql-createsubscription.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the publications of a subscription
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE SUBSCRIPTION s CONNECTION 'host=a' PUBLICATION p1, p2 WITH (enabled = false)");
 *     count($operation->statement->publications) // => 2
 */
final class CreateSubscription implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The publications on the publisher
     */
    public readonly array $publications;

    /**
     * @var list<Definition> The parameters
     */
    public readonly array $options;

    /**
     * @param Name $name The subscription name
     * @param StringConstant $connection The connection string
     * @param list<Name> $publications The publications on the publisher; at least one
     * @param list<Definition> $options The parameters
     */
    public function __construct(public readonly Name $name, public readonly StringConstant $connection, array $publications, array $options = [])
    {
        $this->publications = Check::listOf($publications, Name::class, 'A subscription names at least one publication.', 1);
        $this->options = Check::listOf($options, Definition::class, 'Subscription parameters are definitions.');
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
        $out->keyword('CREATE', 'SUBSCRIPTION')->name($this->name, NameUse::Column)->keyword('CONNECTION')->node($this->connection)->keyword('PUBLICATION');
        foreach ($this->publications as $position => $publication) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($publication, NameUse::Column);
        }
        if ($this->options !== []) {
            $out->keyword('WITH')->symbol('(')->list($this->options)->symbol(')');
        }
    }
}
