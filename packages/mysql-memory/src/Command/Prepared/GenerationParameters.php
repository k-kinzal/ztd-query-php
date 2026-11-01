<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Captures MySQL 5.7 generation parameters as values fixed at table creation.
 * Marker positions come from semantic occurrences, independently of input SQL.
 * Observed through PREPARE, EXECUTE, SHOW CREATE TABLE and subsequent inserts.
 *
 * @visibility MySqlMemory
 */
final class GenerationParameters
{
    /**
     * Refuses markers in declared column defaults before opening the altered table.
     * ALTER COLUMN SET DEFAULT has a later check and can be prepared with markers.
     *
     * @throws \MySqlMemory\Error\SqlError When a default contains a parameter marker
     */
    public function defaults(Node $definition): void
    {
        foreach ((new \MySqlMemory\Evaluation\Compile\Walker())->find($definition, M\Table\Column\ColumnDefinition::class) as $node) {
            $name = $node->name->column->value;
            $attributes = $node->specification->columnAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute instanceof M\Table\Column\DefaultExpression && $this->occurrences($attribute->expression) !== []) {
                    throw \MySqlMemory\Error\Family\ConstraintError::DefaultSubquery->error($name);
                }
            }
        }
    }

    /**
     * Reads marker occurrences in their semantic input-slot order.
     *
     * @return list<M\Literal\Parameter>
     */
    public function occurrences(Node $definition): array
    {
        $markers = (new \MySqlMemory\Evaluation\Compile\Walker())->find($definition, M\Literal\Parameter::class);
        usort($markers, static fn (M\Literal\Parameter $left, M\Literal\Parameter $right): int => $left->position <=> $right->position);
        return $markers;
    }

    /**
     * Supplies a preparation slot for each marker in a complete table instruction.
     */
    public function capture(Node $definition): ParameterBindings
    {
        return new ParameterBindings(array_fill(0, max([0, ...array_map(static fn (M\Literal\Parameter $marker): int => $marker->position + 1, $this->occurrences($definition))]), [Domain::string(16383, M\Type\Resolved\Collation::binary()), false]));
    }

    /**
     * Freezes execution values by occurrence, retaining the reusable template.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, Domain}> $parameters Bound protocol values
     * @return array<int, Scalar> Literal values indexed by original occurrence identity
     */
    public function values(Node $definition, array $parameters): array
    {
        $values = [];
        foreach ($this->occurrences($definition) as $occurrence) {
            \SqlSemantics\Diagnostic\Check::invariant(isset($parameters[$occurrence->position]), 'A retained generation marker has a bound execution value.');
            $values[spl_object_id($occurrence)] = $this->literal(...$parameters[$occurrence->position]);
        }
        return $values;
    }

    /**
     * Preserves NULL, numeric values and decoded character bytes as semantic operands.
     */
    public function literal(int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null $value, Domain $domain): Scalar
    {
        if ($value === null) {
            return new M\Literal\NullLiteral();
        }
        if (!in_array($domain->kind, [M\Type\Resolved\Kind::Integer, M\Type\Resolved\Kind::Decimal, M\Type\Resolved\Kind::Double], true)) {
            return new M\Literal\StringLiteral([(string) $value], new \SqlSemantics\Statement\Identifier\Name($domain->collation->charset->name));
        }
        $text = (string) $value;
        $number = new M\Literal\NumberLiteral(ltrim($text, '-+'));
        return str_starts_with($text, '-') ? new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Minus, $number) : $number;
    }
}
