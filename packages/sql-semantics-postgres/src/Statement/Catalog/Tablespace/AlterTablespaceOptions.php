<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to set or reset parameters of a tablespace.
 *
 * Rule: PG-TABLESPACE-003. Mirrors `AlterTableSpaceOptionsStmt` with its
 * `isReset` flag. RESET names parameters only; a value given to RESET is a
 * diagnostic. Source: https://www.postgresql.org/docs/17/sql-altertablespace.html. Status: Implemented.
 *
 * @visibility public
 * @example Resetting a parameter
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLESPACE fast RESET (seq_page_cost)');
 *     [$operation->statement->reset, $operation->facts->diagnostics] // => [true, []]
 */
final class AlterTablespaceOptions implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The parameters
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param bool $reset Whether the parameters are reset instead of set
     * @param list<Definition> $options The parameters; at least one
     */
    public function __construct(public readonly Name $name, public readonly bool $reset, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'Tablespace parameters are a non-empty list of definitions.', 1);
    }

    /**
     * Derives the values and reports a value given to RESET.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
        foreach ($this->options as $option) {
            if ($this->reset && $option->argument !== null) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::ResetWithValues));

                return;
            }
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TABLESPACE')->name($this->name, NameUse::Column)->keyword($this->reset ? 'RESET' : 'SET')->symbol('(')->list($this->options)->symbol(')');
    }
}
