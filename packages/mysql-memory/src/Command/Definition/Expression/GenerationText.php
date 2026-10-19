<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\Piece;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Writes the canonical expression excerpt reported by legacy generated-column validation.
 *
 * Query boundaries are retained separately from literal text, so a string containing
 * SELECT cannot become the reported boundary. This renders semantic operands only.
 * Verified through SQL on MySQL 5.7.44.
 *
 * @visibility MySqlMemory
 */
final class GenerationText
{
    /**
     * Writes the suffix beginning at the first nested query, with its enclosing expression.
     */
    public function tail(Node $expression): string
    {
        $tail = '';
        $found = false;
        foreach ($this->parts($expression) as [$text, $query]) {
            $found = $found || $query;
            if ($found) {
                $tail .= $text;
            }
        }
        return mb_strcut($tail . ')', 0, 80, 'UTF-8');
    }

    /**
     * Reports a missing system-variable operand in the legacy stored expression.
     * The suffix comes from semantic operands, never from the input request.
     */
    public function systemTail(Node $expression): ?string
    {
        while ($expression instanceof M\Expression\Grouped || $expression instanceof M\Expression\GroupedRow || $expression instanceof M\Routine\Program\Expression\DeferredGrouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof M\Expression\Operator\Arithmetic || $expression instanceof M\Routine\Program\Expression\DeferredArithmetic) {
            $right = $expression->right;
            while ($right instanceof M\Expression\Grouped) {
                $right = $right->operand;
            }
            if ($right instanceof M\Variable\SystemVariable) {
                return '))';
            }
        }
        if ($expression instanceof M\Expression\Row) {
            foreach ($expression->elements as $index => $element) {
                while ($element instanceof M\Expression\Grouped) {
                    $element = $element->operand;
                }
                if ($element instanceof M\Variable\SystemVariable) {
                    $following = array_slice($expression->elements, $index + 1);
                    $text = array_map(static fn ($value): string => (new ItemText([], 'utf8mb4', \SqlSemantics\Contract\GrammarRelease::MySql5744))->text($value), $following);
                    return mb_strcut(implode(',', $text) . '))', 0, 80, 'UTF-8');
                }
            }
        }
        return null;
    }

    /**
     * Writes expression pieces while marking actual query boundaries.
     *
     * @return list<array{string, bool}>
     */
    public function parts(Node $node): array
    {
        if ($node instanceof M\Expression\Grouped || $node instanceof M\Expression\GroupedRow || $node instanceof M\Routine\Program\Expression\DeferredGrouped) {
            return $this->parts($node->operand);
        }
        if ($node instanceof M\Expression\Subquery\ScalarSubquery || $node instanceof M\Expression\Subquery\RowSubquery || $node instanceof M\Routine\Program\Expression\DeferredSubquery) {
            return [['(', false], [$this->query($node->query), true], [')', false]];
        }
        if ($node instanceof M\Expression\Operator\Arithmetic || $node instanceof M\Routine\Program\Expression\DeferredArithmetic || $node instanceof M\Expression\Comparison) {
            return $this->joined([$this->parts($node->left), $this->parts($node->right)], '(', ' ' . $node->operator->value . ' ', ')');
        }
        if ($node instanceof M\Call\FunctionCall || $node instanceof M\Call\KeywordCall) {
            $name = $node instanceof M\Call\FunctionCall ? $node->name->value : $node->function->value;
            $arguments = $node instanceof M\Call\FunctionCall ? array_map(static fn (M\Call\CallArgument $argument): Scalar => $argument->expression, $node->arguments) : $node->arguments;
            return $this->joined(array_map($this->parts(...), $arguments), strtolower($name) . '(', ',', ')');
        }
        $wrapped = $this->wrapped($node);
        if ($wrapped !== null) {
            return $wrapped;
        }
        \SqlSemantics\Diagnostic\Check::invariant($node instanceof Scalar || $node instanceof M\Expression\RowOperand, 'A legacy generation leaf retains its expression role.');
        return [[(new ItemText([], 'utf8mb4', \SqlSemantics\Contract\GrammarRelease::MySql5744))->text($node), false]];
    }

    /**
     * Formats unary and predicate wrappers around their retained operands.
     *
     * @return list<array{string, bool}>|null Null for an expression outside these wrappers
     */
    public function wrapped(Node $node): ?array
    {
        if ($node instanceof M\Expression\Operator\Unary) {
            return $this->joined([$this->parts($node->operand)], $node->operator->value . '(', '', ')');
        }
        if ($node instanceof M\Expression\Not) {
            return $this->joined([$this->parts($node->operand)], '(not(', '', '))');
        }
        if ($node instanceof M\Expression\NullTest) {
            return $this->joined([$this->parts($node->operand)], $node->negated ? '(not(isnull(' : 'isnull(', '', $node->negated ? ')))' : ')');
        }
        if ($node instanceof M\Expression\Predicate\InList && count($node->elements) === 1) {
            return $this->joined([$this->parts($node->operand), $this->parts($node->elements[0])], '(', $node->negated ? ' <> ' : ' = ', ')');
        }
        if ($node instanceof M\Variable\UserVariable) {
            return [['(@`' . str_replace('`', '``', $node->name->value) . '`)', false]];
        }
        return null;
    }

    /**
     * Joins expression parts without losing nested-query positions.
     *
     * @param list<list<array{string, bool}>> $parts Child expressions
     * @return list<array{string, bool}>
     */
    public function joined(array $parts, string $before, string $separator, string $after): array
    {
        $result = [[$before, false]];
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $result[] = [$separator, false];
            }
            array_push($result, ...$part);
        }
        $result[] = [$after, false];
        return $result;
    }

    /**
     * Writes a retained query with legacy identifier and punctuation spelling.
     */
    public function query(\SqlSemantics\Statement\Query $query): string
    {
        $out = new Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql5744));
        $out->layout(null, $query);
        $pieces = $out->pieces();
        $result = [];
        foreach ($pieces as $index => $piece) {
            $text = $piece->text;
            if ($piece->kind === PieceKind::Keyword) {
                $text = strtolower($text);
            } elseif ($piece->kind === PieceKind::Name && !str_starts_with($text, '`')) {
                $text = ($pieces[$index + 1]->text ?? '') === '(' && (new \SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions())->exists(\SqlSemantics\Contract\GrammarRelease::MySql5744, $text) ? strtolower($text) : '`' . str_replace('`', '``', $text) . '`';
            } elseif ($text === '*' && ($pieces[$index - 1]->text ?? '') === 'SELECT') {
                $text = '`*`';
            }
            if ($piece->text === '@') {
                $text = '(@';
            } elseif (($pieces[$index - 1]->text ?? '') === '@') {
                $text .= ')';
            }
            $result[] = new Piece($piece->kind, $text, $piece->glued || ($pieces[$index - 1]->text ?? '') === ',');
        }
        return (new Lexical())->join($result);
    }
}
