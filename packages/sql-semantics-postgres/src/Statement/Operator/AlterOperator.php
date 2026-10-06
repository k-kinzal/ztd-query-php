<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ChangeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorChangeAttribute;
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
 * Mirrors PostgreSQL's `AlterOperatorStmt`. Each attribute is read as
 * `AlterOperator` reads it (PG-ALTER-ATTRIBUTE-001); a value written `NONE`
 * removes the attribute's function, and PostgreSQL 17 also accepts an
 * attribute without a value.
 * Source: https://www.postgresql.org/docs/17/sql-alteroperator.html.
 *
 * @visibility public
 * @example Reading the changed attributes
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER OPERATOR = (int4, int4) SET (restrict = NONE)');
 *     $operation->statement->attributes[0]->attribute->name->value // => 'restrict'
 */
final class AlterOperator implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<AttributeChange> The changed attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param OperatorSignature $operator The operator
     * @param list<AttributeChange> $attributes The changed attributes, read as ALTER OPERATOR reads them; at least one
     */
    public function __construct(public readonly OperatorSignature $operator, array $attributes)
    {
        $this->attributes = Check::listOf($attributes, AttributeChange::class, 'ALTER OPERATOR ... SET changes at least one attribute.', 1);
        foreach ($this->attributes as $change) {
            Check::input($change->attribute->known === null || $change->attribute->known instanceof OperatorChangeAttribute, 'ALTER OPERATOR reads the attributes it can change.');
        }
    }

    /**
     * Derives the operator and the attribute values; a postfix operator, which no longer exists, and the attributes the command refuses are reported.
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
        (new ChangeChecks())->derive($derivation, $this->attributes, false);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'OPERATOR')->node($this->operator)->keyword('SET')->symbol('(')->list($this->attributes)->symbol(')');
    }
}
