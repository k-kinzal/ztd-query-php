<?php

declare(strict_types=1);

namespace Deriver\Source\Validation;

use Override;
use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Checks relative class references only where PHP fixes the lexical class at compile time.
 * @visibility root
 */
final class ClassScope extends NodeVisitorAbstract
{
    /**
     * @var array{class: bool,parent: bool,trait: bool,function: string} Active lexical context
     */
    public array $scope = ['class' => false,'parent' => false,'trait' => false,'function' => 'script'];
    /**
     * @var list<array{class: bool,parent: bool,trait: bool,function: string}> Enclosing contexts
     */
    public array $stack = [];

    /**
     * @param Collecting $errors Captured source diagnostics
     */
    public function __construct(public readonly Collecting $errors)
    {
    }

    /**
     * Validates compile-time class fetches without rejecting rebindable closures and traits.
     * @param Node $node Current syntax
     * @return null Continue traversal
     */
    #[Override]
    public function enterNode(Node $node)
    {
        if ($node instanceof Stmt\ClassLike || $node instanceof Node\FunctionLike) {
            $this->stack[] = $this->scope;
            if ($node instanceof Stmt\ClassLike) {
                $this->scope = ['class' => true,'parent' => $node instanceof Stmt\Class_ && $node->extends !== null,'trait' => $node instanceof Stmt\Trait_,'function' => 'script'];
            } elseif ($node instanceof Stmt\Function_) {
                $this->scope = ['class' => false,'parent' => false,'trait' => false,'function' => 'function'];
            } else {
                $this->scope['function'] = $node instanceof Stmt\ClassMethod ? 'method' : 'closure';
            }
        }
        $this->validate($node);
        return null;
    }

    /**
     * Rejects relative class fetches whose fixed lexical scope cannot supply them.
     * @param Node $node Possible class fetch
     */
    public function validate(Node $node): void
    {
        $class = match(true) {
            $node instanceof Expr\ClassConstFetch,$node instanceof Expr\StaticCall,$node instanceof Expr\StaticPropertyFetch,$node instanceof Expr\New_,$node instanceof Expr\Instanceof_ => $node->class,
            default => null,
        };
        if (!$class instanceof Name || $class instanceof Name\FullyQualified || $this->scope['function'] === 'closure' || $this->scope['trait'] || !$this->scope['class'] && $this->scope['function'] === 'script') {
            return;
        }
        $name = strtolower($class->toString());
        if (in_array($name, ['self','parent','static'], true)) {
            if (!$this->scope['class']) {
                $this->errors->handleError(new Error('Cannot use "'.$name.'" when no class scope is active.', $class->getAttributes()));
            } elseif ($name === 'parent' && !$this->scope['parent']) {
                $this->errors->handleError(new Error('Cannot use "parent" when current class scope has no parent.', $class->getAttributes()));
            }
        }
    }

    /**
     * Restores the context after nested declarations or closures.
     * @param Node $node Completed syntax
     * @return null Preserve the parsed tree
     */
    #[Override]
    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\ClassLike || $node instanceof Node\FunctionLike) {
            $this->scope = array_pop($this->stack) ?? ['class' => false,'parent' => false,'trait' => false,'function' => 'script'];
        }
        return null;
    }
}
