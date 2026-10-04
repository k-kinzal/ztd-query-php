<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER OPERATOR operator (types) SET ( attribute = value, ... )`: changes attributes of an operator.
 *
 * Mirrors PostgreSQL's `AlterOperatorStmt`. A value written `NONE` removes
 * the attribute's function; PostgreSQL 17 also accepts an attribute without
 * a value.
 * Source: https://www.postgresql.org/docs/17/sql-alteroperator.html.
 *
 * @visibility public
 * @example Reading the changed attributes
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER OPERATOR = (int4, int4) SET (restrict = NONE)');
 *     $operation->statement->attributes[0]->name->value // => 'restrict'
 */
final class AlterOperator implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The changed attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param OperatorSignature $operator The operator
     * @param list<Definition> $attributes The changed attributes; at least one, without a namespace
     */
    public function __construct(public readonly OperatorSignature $operator, array $attributes)
    {
        $this->attributes = Check::listOf($attributes, Definition::class, 'ALTER OPERATOR ... SET changes at least one attribute.', 1);
        foreach ($this->attributes as $attribute) {
            Check::input($attribute->qualifier === null, 'An operator attribute has no namespace.');
        }
    }

    /**
     * Derives the operator and the attribute values; a postfix operator, which no longer exists, is reported.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        $this->operator->deriveClause($derivation, $environment);
        if ($this->operator->arity === OperatorArity::Postfix) {
            $derivation->report(new RoutineProblem(RoutineProblemKind::PostfixOperator));
        }
        foreach ($this->attributes as $attribute) {
            $attribute->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'OPERATOR')->node($this->operator)->keyword('SET')->symbol('(')->list($this->attributes)->symbol(')');
    }
}
