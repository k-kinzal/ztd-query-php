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
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * @param list<Expression> $operands
     * @throws SemanticException
     */
    public function call(string $name, array $operands, Node $source): Expression
    {
        if (!in_array($name, ['COALESCE', 'NULLIF'], true)) {
            return (new Scalar\FunctionRules())->bind($name, $operands, $source, new Scope(new \SqlSemantics\Ast\Identifiers($this->dialect)));
        }
        if ($operands === [] || ($name === 'NULLIF' && count($operands) !== 2)) {
            throw new SemanticException('invalid-arity', 'Invalid argument count for ' . $name, $source);
        }
        $type = (new TypeResolution($this->dialect))->common($operands, $source);
        if ($name === 'COALESCE') {
            if ($this->dialect === Dialect::PostgreSql) {
                $operands = array_map(fn (Expression $operand): Expression => $this->coerce($operand, $type), $operands);
            }
            $nullability = NullFacts::coalesce($operands);
            return new Expression(ExpressionKind::Coalesce, $type, $nullability, $source, $operands, symbol: $name, nullExtendedBy: NullFacts::extensions($operands, $nullability));
        }
        $type = $operands[0]->type;
        $nullability = $operands[0]->nullability === Nullability::AlwaysNull ? Nullability::AlwaysNull : Nullability::MaybeNull;
        return new Expression(ExpressionKind::NullIf, $type, $nullability, $source, $operands, symbol: $name, nullExtendedBy: NullFacts::extensions($operands, $nullability));
    }

    /**
     * Records a type-directed implicit conversion without evaluating its value.
     */
    public function coerce(Expression $operand, TypeDescriptor $type): Expression
    {
        if ($operand->type->name === $type->name) {
            return $operand;
        }

        return new Expression(ExpressionKind::Cast, $type, $operand->nullability, $operand->source, [$operand], symbol: 'implicit', nullExtendedBy: $operand->nullExtendedBy);
    }

    /**
     * @param non-empty-list<Expression> $operands
     */
    public function operator(string $operator, array $operands, Node $source): Expression
    {
        $operator = strtoupper($operator);
        $nullability = NullFacts::strict($operands);
        $types = new TypeResolution($this->dialect);
        if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
            $type = $types->boolean();
            $nullability = Nullability::NotNull;
        } elseif (in_array($operator, ['AND', 'OR', 'NOT'], true)) {
            foreach ($operands as $operand) {
                $this->predicate($operand);
            }
            $type = $types->boolean();
            $nullability = NullFacts::coalesce($operands) === Nullability::NotNull && NullFacts::strict($operands) === Nullability::NotNull ? Nullability::NotNull : Nullability::MaybeNull;
        } elseif (in_array($operator, ['=', '<>', '!=', '<', '>', '<=', '>=', 'IS', 'IS NOT', '<=>', 'LIKE', 'NOT LIKE', 'ILIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'REGEXP', 'GLOB', 'MATCH', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM'], true)) {
            $types->common($operands, $source);
            $type = $types->boolean();
            if (in_array($operator, ['IS', 'IS NOT', '<=>'], true)) {
                $nullability = Nullability::NotNull;
            }
        } elseif (in_array($operator, ['+', '-', '*', '/', '%', 'DIV', 'MOD', '^', '&', '|', '<<', '>>'], true)) {
            $type = $this->arithmetic($operator, $operands, $source);
        } else {
            $type = new TypeDescriptor($this->dialect, $operator === '||' ? 'text' : 'unknown');
            $nullability = Nullability::Unknown;
        }

        return new Expression(ExpressionKind::Operator, $type, $nullability, $source, $operands, symbol: $operator, nullExtendedBy: NullFacts::extensions($operands, $nullability));
    }

    /**
     * Resolves supported numeric operations, including signed literal boundaries.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(string $operator, array $operands, Node $source): TypeDescriptor
    {
        $type = (new TypeResolution($this->dialect))->common($operands, $source);
        if ($this->dialect === Dialect::PostgreSql && $operator === '-' && count($operands) === 1 && $operands[0]->kind === ExpressionKind::Literal) {
            $magnitude = str_replace('_', '', $operands[0]->symbol ?? '');
            $type = match ($magnitude) {
                '2147483648' => new TypeDescriptor($this->dialect, 'integer'),
                '9223372036854775808' => new TypeDescriptor($this->dialect, 'bigint'),
                default => $type,
            };
        }
        if ($this->dialect === Dialect::MySql) {
            $type = new TypeDescriptor($this->dialect, in_array($type->name, ['real', 'double precision'], true) ? 'double precision' : ($operator === '/' || $type->name === 'numeric' ? 'numeric' : 'bigint'));
        } elseif ($this->dialect === Dialect::Sqlite) {
            $type = new TypeDescriptor($this->dialect, 'dynamic');
        }
        return $type;
    }

    /**
     * @throws SemanticException
     */
    public function predicate(Expression $expression): void
    {
        if ($this->dialect === Dialect::PostgreSql && !in_array($expression->type->name, ['boolean', 'unknown'], true)) {
            throw new SemanticException('non-boolean-predicate', 'A PostgreSQL predicate must have boolean type.', $expression->source);
        }
    }
}
