<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Occurrences as Reconstruction;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Operands;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Interprets legacy generation expressions after system-variable operands vanish.
 *
 * MySQL 5.7 retains the resulting unary sign in an arithmetic expression. The
 * declaration still keeps every original operand; execution consumes this
 * structural rewrite without parsing generated SQL. Observed through SQL on
 * MySQL 5.7.44, including nested calls, arithmetic and references to row columns.
 *
 * @visibility MySqlMemory
 */
final class GenerationRewrite
{
    /**
     * @param array<int, Scalar> $parameters Bound values by marker occurrence identity
     */
    public function __construct(public readonly array $parameters = [])
    {
    }

    /**
     * Reconstructs an expression with the observed unary arithmetic interpretation.
     */
    public function expression(Scalar $expression): Scalar
    {
        $copy = new Reconstruction(function (Node $node): ?Node {
            if ($node instanceof M\Literal\Parameter) {
                return $this->parameters[spl_object_id($node)] ?? null;
            }
            if (!$node instanceof M\Expression\Operator\Arithmetic) {
                return null;
            }
            $left = $node->left;
            while ($left instanceof M\Expression\Grouped) {
                $left = $left->operand;
            }
            if (!$left instanceof M\Variable\SystemVariable || !in_array($node->operator, [M\Expression\Operator\ArithmeticOperator::Plus, M\Expression\Operator\ArithmeticOperator::Minus], true)) {
                return null;
            }
            return new M\Expression\Operator\Unary($node->operator === M\Expression\Operator\ArithmeticOperator::Plus ? M\Expression\Operator\UnaryOperator::Plus : M\Expression\Operator\UnaryOperator::Minus, new M\Expression\Grouped($this->expression($node->right)));
        });
        $result = $copy->node($expression);
        Check::invariant($result instanceof Scalar, 'Generation arithmetic retains a scalar result.');
        return $result;
    }

    /**
     * Replaces a deferred declaration only when its remaining operands are ordinary.
     * Other invocation conditions remain represented by the original declaration.
     */
    public function column(M\Table\Column\ColumnDefinition $column): M\Table\Column\ColumnDefinition
    {
        $specification = $column->specification;
        if (!$specification instanceof M\Table\Column\Generation\DeferredGeneratedColumn || !$specification->expression instanceof Scalar) {
            return $column;
        }
        $expression = $this->expression($specification->expression);
        if ((new Operands())->problem($expression, $column->name->column) !== null || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\GroupedOperands())->contains($expression)) {
            return $column;
        }
        return new M\Table\Column\ColumnDefinition($column->name, new M\Table\Column\GeneratedColumn($specification->type, $expression, $specification->collation, $specification->storage, $specification->attributes, $specification->references));
    }

    /**
     * Preserves all creation operands and any independent deferred definition role.
     */
    public function create(M\Table\CreateTable $source): M\Table\CreateTable
    {
        $elements = array_map(fn ($element) => $element instanceof M\Table\Column\ColumnDefinition ? $this->column($element) : $element, $source->elements);
        return new M\Table\CreateTable($source->name, $elements, $source->options, $source->partitioning, $source->query, $source->temporaryWords, $source->ifNotExists, $source->enclosed);
    }

    /**
     * Reconstructs complete ALTER actions without losing their order or column positions.
     *
     * @return list<M\Alter\AlterCommand>
     */
    public function commands(M\Alter\AlterTable $source): array
    {
        $copy = new Reconstruction(fn (Node $node): ?Node => $node instanceof M\Table\Column\ColumnDefinition ? $this->column($node) : null);
        return array_map(static function (M\Alter\AlterCommand $command) use ($copy): M\Alter\AlterCommand {
            $result = $copy->node($command);
            Check::invariant($result instanceof M\Alter\AlterCommand, 'Generation rewriting retains the complete ALTER action.');
            return $result;
        }, $source->commands);
    }
}
