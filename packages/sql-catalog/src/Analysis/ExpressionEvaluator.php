<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node\Expr;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar;
use SqlCatalog\Analysis\Effect\WriteEffects;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Works out what values an expression can take at the point it is written.
 *
 * @visibility root
 */
final class ExpressionEvaluator
{
    private ReferenceEvaluator $references;

    private CallEvaluator $calls;

    private EvaluationBudget $budget;

    private NodeText $text;

    private int $clones = 0;

    /**
     * Wires the evaluator to the parts that read references and follow calls.
     */
    public function __construct(
        ReferenceEvaluator $references,
        CallEvaluator $calls,
        EvaluationBudget $budget,
        NodeText $text,
    ) {
        $this->references = $references;
        $this->calls = $calls;
        $this->budget = $budget;
        $this->text = $text;
    }

    /**
     * The reader that resolves names and writes assignments for this evaluator.
     */
    public function references(): ReferenceEvaluator
    {
        return $this->references;
    }

    /**
     * Every value the expression can take.
     */
    public function evaluate(Expr $node, Environment $environment, FunctionScope $scope): Domain
    {
        $this->budget->spend();
        if ($this->budget->isSpent()) {
            return Domain::opaque(TypeShape::unknown(), Origin::Budget, 'budget exhausted');
        }

        $scalar = $this->evaluateScalar($node);
        if ($scalar !== null) {
            return $scalar;
        }
        $operator = $this->evaluateOperator($node, $environment, $scope);
        if ($operator !== null) {
            return $operator;
        }
        if ($node instanceof Expr\CallLike) {
            return $this->calls->evaluate($node, $environment, $scope, $this);
        }
        $reference = $this->references->evaluate($node, $environment, $scope, $this);
        if ($reference !== null) {
            return $reference;
        }
        $this->evaluateOperands($node, $environment, $scope);
        $effects = new WriteEffects();
        $effects->apply($effects->own($node), $environment);

        return Domain::opaque(TypeShape::unknown(), Origin::Unresolved, $this->text->render($node));
    }

    /**
     * Evaluates whatever an expression is written over, for what those evaluations find.
     *
     * A statement can sit anywhere an expression can, including in an operand
     * the analyzer has no use for the value of. `$enabled && $pdo->query(…)`
     * issues a query whatever `$enabled` turns out to be, so finding the call
     * must not depend on the analyzer caring about the expression around it.
     * Every call written inside an operand is evaluated even when the value of
     * the expression holding it is discarded.
     */
    public function evaluateOperands(Expr $node, Environment $environment, FunctionScope $scope): void
    {
        foreach (get_object_vars($node) as $subNode) {
            foreach (is_array($subNode) ? $subNode : [$subNode] as $operand) {
                if ($operand instanceof Expr) {
                    $this->evaluate($operand, $environment, $scope);
                }
            }
        }
    }

    /**
     * The value of a literal expression, or null when the expression is not one.
     */
    public function evaluateScalar(Expr $node): ?Domain
    {
        if ($node instanceof Scalar\String_) {
            return Domain::literal($node->value);
        }
        if ($node instanceof Scalar\Int_) {
            return Domain::literal($node->value);
        }
        if ($node instanceof Scalar\Float_) {
            return Domain::literal($node->value);
        }

        return null;
    }

    /**
     * The value of an operator expression, or null when the expression is not one.
     */
    public function evaluateOperator(Expr $node, Environment $environment, FunctionScope $scope): ?Domain
    {
        if ($node instanceof Expr\Clone_) {
            return $this->evaluateClone($node, $environment, $scope);
        }
        if ($node instanceof Scalar\InterpolatedString) {
            return $this->evaluateInterpolation($node, $environment, $scope);
        }
        if ($node instanceof Expr\BinaryOp\Concat) {
            return $this->evaluate($node->left, $environment, $scope)
                ->concat($this->evaluate($node->right, $environment, $scope));
        }
        if ($node instanceof Expr\Ternary) {
            return $this->evaluateTernary($node, $environment, $scope);
        }
        if ($node instanceof Expr\Isset_) {
            return $this->evaluateIsset($node, $environment, $scope);
        }
        if ($node instanceof Expr\BinaryOp\Coalesce || $node instanceof Expr\BinaryOp\BooleanAnd
            || $node instanceof Expr\BinaryOp\BooleanOr || $node instanceof Expr\BinaryOp\LogicalAnd
            || $node instanceof Expr\BinaryOp\LogicalOr) {
            return $this->evaluateOptionalRight($node, $environment, $scope);
        }
        if ($node instanceof Expr\Match_) {
            return $this->evaluateMatch($node, $environment, $scope);
        }
        if ($node instanceof Expr\Cast) {
            return $this->evaluateCast($node, $environment, $scope);
        }
        if ($node instanceof Expr\Assign) {
            return $this->evaluateAssign($node, $environment, $scope);
        }
        if ($node instanceof Expr\AssignOp\Concat) {
            return $this->evaluateAppend($node, $environment, $scope);
        }

        return $this->evaluateResult($node, $environment, $scope);
    }

    /**
     * Clones a tracked object without sharing its allocation with the original.
     */
    public function evaluateClone(Expr\Clone_ $node, Environment $environment, FunctionScope $scope): Domain
    {
        $value = $this->evaluate($node->expr, $environment, $scope);
        $terms = [];
        foreach ($value->terms as $term) {
            if ($term instanceof ObjectTerm && $term->identity !== null) {
                $this->clones++;
                $term = new ObjectTerm($term->className, $term->enumCase, $term->statementId, 'clone:' . $this->clones, $term->state);
                $environment->objects()->remember($term);
            }
            $terms[] = $term;
        }

        return Domain::fromTerms($terms, $value->widened, $value->combined);
    }

    /**
     * Keeps the effects of executing and skipping a short-circuited operand.
     */
    public function evaluateOptionalRight(Expr\BinaryOp $node, Environment $environment, FunctionScope $scope): Domain
    {
        $left = $this->evaluate($node->left, $environment, $scope);
        $taken = $environment->copy();
        $right = $this->evaluate($node->right, $taken, $scope);
        $environment->replace($environment->join($taken));

        return $node instanceof Expr\BinaryOp\Coalesce
            ? $left->union($right)
            : Domain::opaque(TypeShape::of(['bool']), Origin::Unresolved);
    }

    /**
     * The value of a cast, which narrows to a scalar type and, for the numeric
     * casts, also ends the trail back to whatever the value came from.
     *
     * Casting to `int` neutralises a value as a source of injected SQL, so the
     * result deliberately no longer carries the operand's origin.
     */
    public function evaluateCast(Expr\Cast $node, Environment $environment, FunctionScope $scope): Domain
    {
        $operand = $this->evaluate($node->expr, $environment, $scope);
        if ($node instanceof Expr\Cast\String_) {
            return $operand->concat(Domain::literal(''));
        }
        $type = match (true) {
            $node instanceof Expr\Cast\Int_ => 'int',
            $node instanceof Expr\Cast\Double => 'float',
            $node instanceof Expr\Cast\Bool_ => 'bool',
            $node instanceof Expr\Cast\Array_ => 'array',
            default => null,
        };
        if ($type === null) {
            return Domain::opaque(TypeShape::of(['object']), Origin::Call, 'cast');
        }
        $literal = $operand->soleLiteral();
        if ($literal !== null && $type !== 'array') {
            return $this->castLiteral($literal->value, $type);
        }

        return Domain::opaque(TypeShape::of([$type]), Origin::Call, '(' . $type . ') cast');
    }

    /**
     * A resolved scalar written as the type it is cast to.
     */
    public function castLiteral(string|int|float|bool|null $value, string $type): Domain
    {
        return Domain::literal(match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            default => (bool) $value,
        });
    }

    /**
     * The value of an expression whose operands matter more than its result.
     *
     * The operands are evaluated for what those evaluations find, and the
     * result is reported by the type the operator produces.
     */
    public function evaluateResult(Expr $node, Environment $environment, FunctionScope $scope): ?Domain
    {
        $type = $this->resultType($node);
        if ($type === null) {
            return null;
        }
        $this->evaluateOperands($node, $environment, $scope);

        return Domain::opaque(TypeShape::of([$type]), Origin::Unresolved);
    }

    /**
     * The type an operator produces, or null when the node is not one.
     */
    public function resultType(Expr $node): ?string
    {
        if ($node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\BooleanOr) {
            return 'bool';
        }
        if ($node instanceof Expr\BinaryOp\LogicalAnd || $node instanceof Expr\BinaryOp\LogicalOr) {
            return 'bool';
        }
        if ($node instanceof Expr\BooleanNot || $node instanceof Expr\Isset_ || $node instanceof Expr\Empty_) {
            return 'bool';
        }
        if ($node instanceof Expr\Instanceof_) {
            return 'bool';
        }
        if ($node instanceof Expr\PostInc || $node instanceof Expr\PreInc) {
            return 'int';
        }
        if ($node instanceof Expr\PostDec || $node instanceof Expr\PreDec) {
            return 'int';
        }

        return null;
    }

    /**
     * The value of a double-quoted string or heredoc with expressions inside it.
     */
    public function evaluateInterpolation(
        Scalar\InterpolatedString $node,
        Environment $environment,
        FunctionScope $scope,
    ): Domain {
        $result = Domain::literal('');
        foreach ($node->parts as $part) {
            $result = $result->concat(
                $part instanceof InterpolatedStringPart
                    ? Domain::literal($part->value)
                    : $this->evaluate($part, $environment, $scope),
            );
        }

        return $result;
    }

    /**
     * The value of a conditional, which is whichever branch runs.
     */
    public function evaluateTernary(Expr\Ternary $node, Environment $environment, FunctionScope $scope): Domain
    {
        $condition = $this->evaluate($node->cond, $environment, $scope);
        $yes = $environment->copy();
        $no = $environment->copy();
        $whenTrue = $node->if === null ? $condition : $this->evaluate($node->if, $yes, $scope);
        $whenFalse = $this->evaluate($node->else, $no, $scope);
        $environment->replace($yes->join($no));

        return $whenTrue->union($whenFalse);
    }

    /**
     * An existence test's boolean result stays open; its operands may have effects.
     */
    public function evaluateIsset(Expr\Isset_ $node, Environment $environment, FunctionScope $scope): Domain
    {
        $taken = $environment->copy();
        foreach ($node->vars as $variable) {
            $this->evaluate($variable, $taken, $scope);
            $environment->replace($environment->join($taken));
        }

        return Domain::opaque(TypeShape::of(['bool']), Origin::Unresolved);
    }

    /**
     * The value of a match, which is whichever arm runs.
     */
    public function evaluateMatch(Expr\Match_ $node, Environment $environment, FunctionScope $scope): Domain
    {
        $this->evaluate($node->cond, $environment, $scope);
        $result = null;
        $joined = null;
        $conditions = $environment->copy();
        foreach ($node->arms as $arm) {
            foreach ($arm->conds ?? [] as $condition) {
                $tested = $conditions->copy();
                $this->evaluate($condition, $tested, $scope);
                $conditions = $conditions->join($tested);
            }
            $branch = $conditions->copy();
            $value = $this->evaluate($arm->body, $branch, $scope);
            $result = $result === null ? $value : $result->union($value);
            $joined = $joined === null ? $branch : $joined->join($branch);
        }

        $environment->replace($joined ?? $environment);

        return $result ?? Domain::unknown();
    }

    /**
     * The value of an assignment, which is also written into the environment.
     */
    public function evaluateAssign(Expr\Assign $node, Environment $environment, FunctionScope $scope): Domain
    {
        $value = $this->evaluate($node->expr, $environment, $scope);
        $this->references->assign($node->var, $value, $environment, $scope, $this);

        return $value;
    }

    /**
     * The value of an appending assignment, which is also written into the environment.
     */
    public function evaluateAppend(Expr\AssignOp\Concat $node, Environment $environment, FunctionScope $scope): Domain
    {
        $value = $this->evaluate($node->var, $environment, $scope)
            ->concat($this->evaluate($node->expr, $environment, $scope));
        $this->references->assign($node->var, $value, $environment, $scope, $this);

        return $value;
    }
}
