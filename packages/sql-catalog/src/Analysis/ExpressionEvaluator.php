<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node\Expr;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
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

    private BodyWalker $bodies;

    private EvaluationBudget $budget;

    private NodeText $text;

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
        $this->bodies = new BodyWalker($this, $budget);
    }

    /**
     * The walker that runs statement bodies with this evaluator.
     */
    public function bodies(): BodyWalker
    {
        return $this->bodies;
    }

    /**
     * Every value the expression can take.
     */
    public function evaluate(Expr $node, Environment $environment, FunctionScope $scope): Domain
    {
        if (!$this->budget->spend()) {
            return Domain::opaque(TypeShape::unknown(), Origin::Unresolved, 'budget exhausted');
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
            return $this->calls->evaluate($node, $environment, $scope, $this, $this->bodies);
        }
        $reference = $this->references->evaluate($node, $environment, $scope, $this);
        if ($reference !== null) {
            return $reference;
        }

        return Domain::opaque(TypeShape::unknown(), Origin::Unresolved, $this->text->render($node));
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
        if ($node instanceof Expr\BinaryOp\Coalesce) {
            return $this->evaluate($node->left, $environment, $scope)
                ->union($this->evaluate($node->right, $environment, $scope));
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

        return $this->evaluatePredicate($node);
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
     * The value of an expression whose result is a boolean or a number.
     */
    public function evaluatePredicate(Expr $node): ?Domain
    {
        if ($node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\BooleanOr) {
            return Domain::opaque(TypeShape::of(['bool']), Origin::Unresolved);
        }
        if ($node instanceof Expr\BooleanNot || $node instanceof Expr\Isset_ || $node instanceof Expr\Empty_) {
            return Domain::opaque(TypeShape::of(['bool']), Origin::Unresolved);
        }
        if ($node instanceof Expr\Instanceof_) {
            return Domain::opaque(TypeShape::of(['bool']), Origin::Unresolved);
        }
        if ($node instanceof Expr\PostInc || $node instanceof Expr\PreInc) {
            return Domain::opaque(TypeShape::of(['int']), Origin::Unresolved);
        }
        if ($node instanceof Expr\PostDec || $node instanceof Expr\PreDec) {
            return Domain::opaque(TypeShape::of(['int']), Origin::Unresolved);
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
        $whenTrue = $node->if === null
            ? $this->evaluate($node->cond, $environment, $scope)
            : $this->evaluate($node->if, $environment, $scope);

        return $whenTrue->union($this->evaluate($node->else, $environment, $scope));
    }

    /**
     * The value of a match, which is whichever arm runs.
     */
    public function evaluateMatch(Expr\Match_ $node, Environment $environment, FunctionScope $scope): Domain
    {
        $result = null;
        foreach ($node->arms as $arm) {
            $value = $this->evaluate($arm->body, $environment, $scope);
            $result = $result === null ? $value : $result->union($value);
        }

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
