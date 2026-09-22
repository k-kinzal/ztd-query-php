<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Assigns types and NULL facts to supported scalar operations.
 *
 * @visibility SqlSemantics
 */
final class ExpressionRules
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect, public readonly Analysis\Diagnostics $diagnostics = new Analysis\Diagnostics())
    {
    }

    /**
     * @param list<Expression> $operands
     * @throws SemanticException
     */
    public function call(string $name, array $operands, Node $source): Expression
    {
        if (!in_array($name, ['COALESCE', 'NULLIF', 'GREATEST', 'LEAST'], true)) {
            return (new Scalar\FunctionRules())->bind($name, $operands, $source, new Scope(new \SqlSemantics\Ast\Identifiers($this->dialect)));
        }
        if ($operands === [] || ($name === 'NULLIF' && count($operands) !== 2)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::FunctionArity, $source);
        }
        $type = (new TypeResolution($this->dialect, $this->diagnostics))->common($operands, $source);
        if ($name !== 'NULLIF') {
            if ($this->dialect === Dialect::PostgreSql && $type->name !== 'unknown') {
                $operands = array_map(fn (Expression $operand): Expression => $this->coerce($operand, $type), $operands);
            }
            $nullability = NullFacts::coalesce($operands);
            $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $nullability, NullFacts::extensions($operands, $nullability));
            return $name === 'COALESCE'
                ? new \SqlSemantics\Model\Scalar\Conditional\Coalesce($facts, $source, $operands)
                : new \SqlSemantics\Model\Scalar\Conditional\Extremum($facts, $source, \SqlSemantics\Model\Scalar\Conditional\ExtremumKind::from($name), $operands);
        }
        $type = $operands[0]->type;
        $nullability = $operands[0]->nullability === Nullability::AlwaysNull ? Nullability::AlwaysNull : Nullability::MaybeNull;
        return new \SqlSemantics\Model\Scalar\Conditional\NullIf(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $nullability, NullFacts::extensions($operands, $nullability)), $source, ($operands)[0], ($operands)[1]);
    }

    /**
     * Records a type-directed implicit conversion without evaluating its value.
     */
    public function coerce(Expression $operand, TypeDescriptor $type): Expression
    {
        if ($operand->type->name === $type->name) {
            return $operand;
        }

        return new \SqlSemantics\Model\Scalar\Operator\CastExpression(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $operand->nullability, $operand->nullExtendedBy), $operand->source, ([$operand])[0], \SqlSemantics\Model\Scalar\Operator\CastMode::Implicit);
    }

    /**
     * @param non-empty-list<Expression> $operands
     */
    public function operator(string $operator, array $operands, Node $source): Expression
    {
        $operator = match (strtoupper($operator)) {
            'ISNULL' => 'IS NULL',
            'NOTNULL', 'NOT NULL' => 'IS NOT NULL',
            default => strtoupper($operator),
        };
        if (in_array($operator, ['IN', 'NOT IN'], true)) {
            return (new Scalar\Conditional\MembershipBinder())->bind($operator === 'NOT IN', $operands[0], array_slice($operands, 1), $source, $this);
        }
        if (in_array($operator, ['MEMBER', 'MEMBER OF'], true) && count($operands) === 2) {
            return new \SqlSemantics\Model\Scalar\Conditional\JsonMembership(new \SqlSemantics\Model\Scalar\ExpressionFacts((new TypeResolution($this->dialect, $this->diagnostics))->boolean(), NullFacts::strict($operands)), $source, $operands[0], $operands[1]);
        }
        $pattern = preg_replace('/^(?:NOT )?(LIKE|ILIKE|GLOB|REGEXP|RLIKE|MATCH|SIMILAR TO)(?: ESCAPE)?$/', '$1', $operator);
        if ($pattern !== null && \SqlSemantics\Model\Scalar\Conditional\PatternOperator::tryFrom($pattern === 'RLIKE' ? 'REGEXP' : $pattern) !== null) {
            return \SqlSemantics\Model\Scalar\Operator\Operations::make(new \SqlSemantics\Model\Scalar\ExpressionFacts((new TypeResolution($this->dialect, $this->diagnostics))->boolean(), NullFacts::strict($operands)), $source, $operator, $operands);
        }
        $nullability = NullFacts::strict($operands);
        $types = new TypeResolution($this->dialect, $this->diagnostics);
        $unary = \SqlSemantics\Model\Scalar\Operator\UnaryOperator::tryFrom($operator);
        if ($unary?->postfix() === true) {
            if ($unary->truthTest()) {
                $this->predicate($operands[0]);
            }
            $type = $types->boolean();
            $nullability = Nullability::NotNull;
        } elseif (in_array($operator, ['AND', 'OR', 'NOT'], true)) {
            foreach ($operands as $operand) {
                $this->predicate($operand);
            }
            $type = $types->boolean();
            $nullability = NullFacts::coalesce($operands) === Nullability::NotNull && NullFacts::strict($operands) === Nullability::NotNull ? Nullability::NotNull : Nullability::MaybeNull;
        } elseif (in_array($operator, ['=', '<>', '!=', '<', '>', '<=', '>=', 'IS', 'IS NOT', '<=>', 'LIKE', 'NOT LIKE', 'ILIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'REGEXP', 'NOT REGEXP', 'GLOB', 'NOT GLOB', 'MATCH', 'NOT MATCH', 'SIMILAR TO', 'NOT SIMILAR TO', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM'], true)) {
            $types->common($operands, $source);
            $type = $types->boolean();
            if (in_array($operator, ['IS', 'IS NOT', '<=>', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM'], true)) {
                $nullability = Nullability::NotNull;
            }
        } elseif (in_array($operator, ['+', '-', '*', '/', '%', 'DIV', 'MOD', '^', '&', '|', '<<', '>>'], true)) {
            $type = $this->arithmetic($operator, $operands, $source);
        } else {
            $type = TypeDescriptor::builtin($this->dialect, $operator === '||' ? 'text' : 'unknown');
            $nullability = Nullability::Unknown;
        }

        return \SqlSemantics\Model\Scalar\Operator\Operations::make(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $nullability, NullFacts::extensions($operands, $nullability)), $source, $operator, $operands);
    }

    /**
     * Resolves supported numeric operations, including signed literal boundaries.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(string $operator, array $operands, Node $source): TypeDescriptor
    {
        $type = (new TypeResolution($this->dialect, $this->diagnostics))->common($operands, $source);
        if ($this->dialect === Dialect::PostgreSql && $operator === '-' && count($operands) === 1 && $operands[0]->kind === ExpressionKind::Literal) {
            $magnitude = str_replace('_', '', $operands[0]->spelling() ?? '');
            $type = match ($magnitude) {
                '2147483648' => TypeDescriptor::builtin($this->dialect, 'integer'),
                '9223372036854775808' => TypeDescriptor::builtin($this->dialect, 'bigint'),
                default => $type,
            };
        }
        if ($this->dialect === Dialect::MySql) {
            $type = TypeDescriptor::builtin($this->dialect, in_array($type->name, ['real', 'double precision'], true) ? 'double precision' : ($operator === '/' || $type->name === 'numeric' ? 'numeric' : 'bigint'));
        } elseif ($this->dialect === Dialect::Sqlite) {
            $type = TypeDescriptor::builtin($this->dialect, 'dynamic');
        }
        return $type;
    }

    /**
     * @throws SemanticException
     */
    public function predicate(Expression $expression): void
    {
        if ($this->dialect === Dialect::PostgreSql && !in_array($expression->type->name, ['boolean', 'unknown'], true)) {
            $this->diagnostics->report('non-boolean-predicate', 'A PostgreSQL predicate must have boolean type.', $expression->source);
        }
    }
}
