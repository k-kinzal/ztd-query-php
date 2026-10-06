<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * An operator named with its operand types, as `operator_with_argtypes` writes it.
 *
 * Mirrors PostgreSQL's `ObjectWithArgs` for operators. The operator name may
 * be schema-qualified without the `OPERATOR(...)` syntax.
 * Source: https://www.postgresql.org/docs/17/sql-dropoperator.html.
 *
 * @visibility public
 * @example Reading a prefix operator signature
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('int4')])));
 *     $signature = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature(new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('-')), \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity::Prefix, [$type]);
 *     [$signature->left(), $signature->right() === $type] // => [null, true]
 */
final class OperatorSignature implements ObjectReference
{
    use Snapshot;

    /**
     * @var non-empty-list<TypeName> The written operand types
     */
    public readonly array $types;

    /**
     * @param OperatorName $operator The operator, written without the OPERATOR(...) syntax
     * @param OperatorArity $arity How the operand types are written
     * @param list<TypeName> $types The written operand types, as many as the arity writes
     */
    public function __construct(public readonly OperatorName $operator, public readonly OperatorArity $arity, array $types)
    {
        Check::input(!$operator->explicit, 'An operator signature names the operator without the OPERATOR(...) syntax.');
        $this->types = Check::listOf($types, TypeName::class, 'An operator signature has operand types.', 1);
        Check::input(count($this->types) === $arity->types(), 'The number of operand types matches how they are written.');
    }

    /**
     * Answers the left operand type, or null when there is none.
     */
    public function left(): ?TypeName
    {
        return match ($this->arity) {
            OperatorArity::Binary, OperatorArity::Postfix => $this->types[0],
            OperatorArity::Prefix, OperatorArity::Incomplete => null,
        };
    }

    /**
     * Answers the right operand type, or null when there is none.
     */
    public function right(): ?TypeName
    {
        return match ($this->arity) {
            OperatorArity::Binary => $this->types[1] ?? null,
            OperatorArity::Prefix => $this->types[0],
            OperatorArity::Postfix, OperatorArity::Incomplete => null,
        };
    }

    /**
     * Derives the operand types; a single type without NONE is reported as the server's parser does.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->types as $type) {
            $type->deriveClause($derivation, $environment);
        }
        if ($this->arity === OperatorArity::Incomplete) {
            $derivation->report(new RoutineProblem(RoutineProblemKind::MissingOperatorArgument));
        }
    }

    /**
     * Writes the operator and its operand types.
     */
    public function render(Output $out): void
    {
        $out->node($this->operator)->symbol('(');
        match ($this->arity) {
            OperatorArity::Binary => $out->list($this->types),
            OperatorArity::Prefix => $out->keyword('NONE')->symbol(',')->node($this->types[0]),
            OperatorArity::Postfix => $out->node($this->types[0])->symbol(',')->keyword('NONE'),
            OperatorArity::Incomplete => $out->node($this->types[0]),
        };
        $out->symbol(')');
    }
}
