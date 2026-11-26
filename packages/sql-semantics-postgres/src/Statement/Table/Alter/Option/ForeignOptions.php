<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;

/**
 * OPTIONS ( ... ): changes the options of a foreign table.
 *
 * Mirrors `AT_GenericOptions`; each option is added, set or dropped.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
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
