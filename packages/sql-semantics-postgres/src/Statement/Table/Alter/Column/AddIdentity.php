<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\IdentityColumns;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyColumns;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * ALTER [ COLUMN ] ... ADD GENERATED ... AS IDENTITY: makes a column an identity column.
 *
 * Mirrors `AT_AddIdentity` with the sequence options. The column must exist,
 * and its declared type is checked by PG-IDENTITY-TYPE-001.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Adding an identity
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ALTER a ADD GENERATED ALWAYS AS IDENTITY (CACHE 10)');
 *     $statement->toString() // => 'ALTER TABLE t ALTER a ADD GENERATED ALWAYS AS IDENTITY (CACHE 10)'
 */
final class AddIdentity implements AlterCommand
{
    use Snapshot;

    /**
     * @var list<SequenceOption> The options of the sequence; none means no parentheses are written
     */
    public readonly array $options;

    /**
     * @param Name $column The column
     * @param GeneratedWhen $when When the column takes a generated value
     * @param list<SequenceOption> $options The options of the sequence; none means no parentheses are written
     */
    public function __construct(public readonly Name $column, public readonly GeneratedWhen $when, array $options = [])
    {
        $this->options = Check::listOf($options, SequenceOption::class, 'The options of an identity sequence are sequence options.');
    }

    /**
     * Checks that the column exists and has an identity type, and derives the options.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Alterations())->column($derivation, $environment, $this->column);
        foreach ($environment->relations as $relation) {
            $position = $relation->shape->complete() ? (new KeyColumns())->position($derivation, $relation->shape, $this->column) : null;
            $type = $position === null ? null : $relation->shape->slots[$position]->type;
            if ($type instanceof Known) {
                (new IdentityColumns())->check($derivation, $type->descriptor);
            }
        }
        foreach ($this->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER')->name($this->column)->keyword('ADD', 'GENERATED', ...$this->when->keywords())->keyword('AS', 'IDENTITY');
        if ($this->options !== []) {
            $out->symbol('(');
            (new Writing())->sequence($out, $this->options);
            $out->symbol(')');
        }
    }
}
