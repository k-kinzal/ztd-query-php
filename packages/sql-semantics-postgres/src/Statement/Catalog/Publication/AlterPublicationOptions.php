<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication;

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
 * A request to change the parameters of a publication: `ALTER PUBLICATION name SET ( parameter = value, ... )`.
 *
 * Rule: PG-PUBLICATION-003. Mirrors `AlterPublicationStmt` with options.
 * Source: https://www.postgresql.org/docs/17/sql-alterpublication.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterPublicationOptions implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The parameters
     */
    public readonly array $options;

    /**
     * @param Name $name The publication name
     * @param list<Definition> $options The parameters; at least one
     */
    public function __construct(public readonly Name $name, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'ALTER PUBLICATION SET changes at least one parameter.', 1);
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
        $out->keyword('ALTER', 'PUBLICATION')->name($this->name, NameUse::Column)->keyword('SET')->symbol('(')->list($this->options)->symbol(')');
    }
}
