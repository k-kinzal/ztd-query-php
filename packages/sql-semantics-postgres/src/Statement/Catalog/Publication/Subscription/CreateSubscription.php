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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;

/**
 * A request to create a subscription to publications of another server.
 *
 * Rule: PG-SUBSCRIPTION-001. Mirrors `CreateSubscriptionStmt`: name,
 * connection string, publication names on the publisher and parameters.
 * Source: https://www.postgresql.org/docs/17/sql-createsubscription.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
