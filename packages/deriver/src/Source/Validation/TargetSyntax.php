<?php

declare(strict_types=1);

namespace Deriver\Source\Validation;

use Deriver\Project\TargetProfile;
use Override;
use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Rejects newer grammar forms that the upstream parser accepts under older emulation profiles.
 * @visibility root
 */
final class TargetSyntax extends NodeVisitorAbstract
{
    /**
     * Number of active constant-expression contexts.
     */
    public int $initializers = 0;
    /**
     * @var list<int> Constant contexts suspended while traversing executable method bodies.
     */
    public array $functions = [];

    /**
     * @param TargetProfile $profile Declared target language
     * @param Collecting $errors Project syntax diagnostics
     * @param list<\PhpParser\Token> $tokens Original token stream for parenthesis-sensitive syntax
     */
    public function __construct(public readonly TargetProfile $profile, public readonly Collecting $errors, public readonly array $tokens = [])
    {
    }

    /**
     * Records a target-version error without lowering or evaluating the unsupported syntax.
     * @param Node $node Parsed syntax
     * @return null Traversal continues to collect independent errors
     */
    #[Override]
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\FunctionLike) {
            $this->functions[] = $this->initializers;
            $this->initializers = 0;
        }
        if ($this->initializer($node)) {
            $this->initializers++;
        }
        if ($node instanceof Node\ArrayItem && $node->unpack && ($node->value instanceof Node\Scalar\Int_ || $node->value instanceof Node\Scalar\Float_ || $node->value instanceof Node\Scalar\String_ || $node->value instanceof Expr\ConstFetch && in_array(strtolower($node->value->name->toString()), ['true', 'false', 'null'], true))) {
            $this->errors->handleError(new Error('Only arrays and Traversables can be unpacked.', $node->getAttributes()));
        }
        if (($node instanceof Expr\New_ || $node instanceof Expr\NullsafeMethodCall) && $node->isFirstClassCallable()) {
            $this->errors->handleError(new Error('First-class callable syntax is invalid for this expression.', $node->getAttributes()));
            return null;
        }
        $version = $this->minimum($node);
        if ($version !== '' && version_compare($this->profile->version, $version, '<')) {
            $this->errors->handleError(new Error($node->getType() . ' requires PHP ' . $version . ' or later.', $node->getAttributes()));
        }
        return null;
    }

    /**
     * Identifies newer AST forms whose availability is not enforced by parser emulation.
     * @param Node $node Syntax candidate
     * @return string Minimum version, or empty for syntax covered by the target grammar
     */
    public function minimum(Node $node): string
    {
        if ($this->initializers > 0 && $node instanceof Expr\CallLike && $node->isFirstClassCallable()) {
            return '8.5';
        }
        if ($this->unparenthesizedNew($node)) {
            return '8.4';
        }
        if ($node instanceof Expr\CallLike && $node->isPartialFunctionApplication() && !$node->isFirstClassCallable()) {
            return '8.6';
        }
        if (in_array($node->getType(), ['Expr_BinaryOp_Pipe', 'Expr_Cast_Void'], true)) {
            return '8.5';
        }
        if ($node instanceof Node\PropertyHook || $node->getType() === 'Scalar_MagicConst_Property') {
            return '8.4';
        }
        if (($node instanceof Stmt\Property || $node instanceof Node\Param) && ($node->flags & Modifiers::VISIBILITY_SET_MASK) !== 0) {
            return '8.4';
        }
        return $node instanceof Stmt\Property && ($node->flags & (Modifiers::FINAL | Modifiers::ABSTRACT)) !== 0 ? '8.4' : '';
    }

    /**
     * Leaves one constant-expression context after its descendants have been checked.
     * @param Node $node Completed syntax
     * @return null Preserve the parsed tree
     */
    #[Override]
    public function leaveNode(Node $node)
    {
        if ($this->initializer($node)) {
            $this->initializers--;
        }
        if ($node instanceof Node\FunctionLike) {
            $this->initializers = array_pop($this->functions) ?? 0;
        }
        return null;
    }

    /**
     * Recognizes contexts whose callable constants were introduced in PHP 8.5.
     * @param Node $node Parsed syntax
     * @return bool Whether descendants are part of a constant initializer
     */
    public function initializer(Node $node): bool
    {
        return $node instanceof Node\Const_ || $node instanceof Node\Param || $node instanceof Node\PropertyItem || $node instanceof Node\Attribute;
    }

    /**
     * Distinguishes (new C())->m() from the unparenthesized PHP 8.4 form.
     * @param Node $node Possible dereference expression
     * @return bool Whether a new expression is directly dereferenced without grouping
     */
    public function unparenthesizedNew(Node $node): bool
    {
        $receiver = match (true) {
            $node instanceof Expr\MethodCall, $node instanceof Expr\NullsafeMethodCall, $node instanceof Expr\PropertyFetch, $node instanceof Expr\NullsafePropertyFetch, $node instanceof Expr\ArrayDimFetch => $node->var,
            $node instanceof Expr\StaticCall, $node instanceof Expr\StaticPropertyFetch, $node instanceof Expr\ClassConstFetch => $node->class,
            default => null,
        };
        if (!$receiver instanceof Expr\New_ || $receiver->getEndTokenPos() < 0) {
            return false;
        }
        for ($index = $receiver->getEndTokenPos() + 1; $index < count($this->tokens); $index++) {
            $token = $this->tokens[$index];
            if (!in_array($token->id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $token->text !== ')';
            }
        }
        return false;
    }
}
