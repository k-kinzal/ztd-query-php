<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * OPTIONS ( ... ): changes the options of a foreign table.
 *
 * Mirrors `AT_GenericOptions`; each option is added, set or dropped.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Changing foreign table options
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER FOREIGN TABLE f OPTIONS (SET a 'b', DROP c)");
 *     $statement->toString() // => "ALTER FOREIGN TABLE f OPTIONS (SET a 'b', DROP c)"
 */
final class ForeignOptions implements AlterCommand
{
    use Snapshot;

    /**
     * @var non-empty-list<AlteredOption> The changes
     */
    public readonly array $options;

    /**
     * @param list<AlteredOption> $options The changes
     */
    public function __construct(array $options)
    {
        $this->options = Check::listOf($options, AlteredOption::class, 'Option changes are altered options.', 1);
    }

    /**
     * Derives nothing: options are constants.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes OPTIONS and the changes.
     */
    public function render(Output $out): void
    {
        $out->keyword('OPTIONS')->symbol('(')->list($this->options)->symbol(')');
    }
}
