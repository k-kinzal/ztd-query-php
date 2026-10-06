<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A GENERATED ... AS IDENTITY column constraint: the column takes its values from an implicit sequence.
 *
 * Mirrors `CONSTR_IDENTITY` with `generated_when` and the sequence `options`.
 * An identity column is implicitly NOT NULL ("the column is implicitly NOT
 * NULL"); its type must be smallint, integer or bigint.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example An identity column is never NULL
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a bigint GENERATED ALWAYS AS IDENTITY (START WITH 10))');
 *     [$create->declarations()[0]->columns[0]->nullability, count($create->statement->definition->elements[0]->qualifiers[0]->options)] // => [\SqlSemantics\Statement\Type\Nullability::NotNull, 1]
 */
final class Identity implements Constraint
{
    use Snapshot;

    /**
     * @var list<SequenceOption> The options of the sequence; none means no parentheses are written
     */
    public readonly array $options;

    /**
     * @param GeneratedWhen $when When the column takes a generated value
     * @param list<SequenceOption> $options The options of the sequence
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly GeneratedWhen $when, array $options = [], public readonly ?Name $name = null)
    {
        $this->options = Check::listOf($options, SequenceOption::class, 'The options of an identity sequence are sequence options.');
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Identity;
    }

    /**
     * Derives the sequence options.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        (new Writing())->constraintName($out, $this->name);
        $out->keyword('GENERATED', ...$this->when->keywords())->keyword('AS', 'IDENTITY');
        if ($this->options !== []) {
            $out->symbol('(');
            (new Writing())->sequence($out, $this->options);
            $out->symbol(')');
        }
    }
}
