<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;

/**
 * CREATE ASSERTION: a condition over the database, which the grammar reads and the server does not implement.
 *
 * The grammar keeps the SQL-standard statement; the server rejects it with "CREATE ASSERTION is not yet
 * implemented", which is reported. The condition is derived where no relation is visible.
 * Source: https://www.postgresql.org/docs/17/unsupported-features-sql-standard.html.
 *
 * @visibility SqlSemantics
 */
final class CreateAssertion implements Statement
{
    use Snapshot;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param DottedName $name The assertion name
     * @param Scalar $condition The condition
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     */
    public function __construct(public readonly DottedName $name, public readonly Scalar $condition, array $attributes = [])
    {
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Reports that the server does not implement the statement and derives the condition.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->report(new DefinitionProblem(DefinitionRule::Unimplemented, new Name('CREATE ASSERTION')));
        $derivation->scalar($this->condition, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'ASSERTION')->node($this->name)->keyword('CHECK')->symbol('(')->node($this->condition)->symbol(')');
        (new Writing())->sequence($out, $this->attributes);
    }
}
