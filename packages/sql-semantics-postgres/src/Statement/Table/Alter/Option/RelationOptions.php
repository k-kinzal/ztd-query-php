<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * SET ( ... ) or RESET ( ... ): the storage parameters of the relation.
 *
 * Mirrors `AT_SetRelOptions` and `AT_ResetRelOptions`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Setting storage parameters
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t SET (fillfactor = 70)');
 *     $statement->toString() // => 'ALTER TABLE t SET (fillfactor = 70)'
 */
final class RelationOptions implements AlterCommand
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The parameters
     */
    public readonly array $options;

    /**
     * @param bool $reset Whether the parameters are reset; set otherwise
     * @param list<Definition> $options The parameters
     */
    public function __construct(public readonly bool $reset, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'Storage parameters are definitions.', 1);
    }

    /**
     * Derives the parameters.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->reset ? 'RESET' : 'SET');
        (new Writing())->definitions($out, $this->options);
    }
}
