<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationAction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to add, replace or remove the publications a subscription subscribes to.
 *
 * Rule: PG-SUBSCRIPTION-005. Mirrors `AlterSubscriptionStmt` of kind
 * ALTER_SUBSCRIPTION_ADD_PUBLICATION, _SET_PUBLICATION or _DROP_PUBLICATION.
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html. Status: Implemented.
 *
 * @visibility public
 * @example Adding a publication
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s ADD PUBLICATION p3');
 *     [$operation->statement->action, $operation->statement->publications[0]->value] // => [\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationAction::Add, 'p3']
 */
final class AlterSubscriptionPublications implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The publications
     */
    public readonly array $publications;

    /**
     * @var list<Definition> The options
     */
    public readonly array $options;

    /**
     * @param Name $name The subscription name
     * @param PublicationAction $action Whether the publications are added, replace the list, or are removed
     * @param list<Name> $publications The publications; at least one
     * @param list<Definition> $options The options
     */
    public function __construct(public readonly Name $name, public readonly PublicationAction $action, array $publications, array $options = [])
    {
        $this->publications = Check::listOf($publications, Name::class, 'ALTER SUBSCRIPTION names at least one publication.', 1);
        $this->options = Check::listOf($options, Definition::class, 'Subscription options are definitions.');
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
        $out->keyword('ALTER', 'SUBSCRIPTION')->name($this->name, NameUse::Column)->keyword($this->action->value, 'PUBLICATION');
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
