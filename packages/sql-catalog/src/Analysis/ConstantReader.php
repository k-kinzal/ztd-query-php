<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Reads constants: global ones, class ones, enum cases and `::class`.
 *
 * A constant is a value the source writes down once and names everywhere
 * else, which is exactly the kind of value that decides a table or a column
 * name, so it is resolved to what the declaration says.
 *
 * @visibility root
 */
final class ConstantReader
{
    private ProgramIndex $index;

    private NodeText $text;

    /**
     * Wires the reader to the declarations it resolves names against.
     */
    public function __construct(ProgramIndex $index, NodeText $text)
    {
        $this->index = $index;
        $this->text = $text;
    }

    /**
     * The value of a global constant.
     */
    public function readConstant(Expr\ConstFetch $node, FunctionScope $scope, ExpressionEvaluator $expressions): Domain
    {
        $name = $this->constantName($node);
        $keyword = strtolower($node->name->toString());
        if ($keyword === 'true' || $keyword === 'false') {
            return Domain::literal($keyword === 'true');
        }
        if ($keyword === 'null') {
            return Domain::literal(null);
        }

        $declared = $this->index->findConstant($name);
        if ($declared !== null) {
            return $expressions->evaluate($declared, new Environment(), $scope);
        }

        return $this->readRuntimeConstant($name);
    }

    /**
     * The name a constant reference resolves to.
     *
     * An unqualified constant is looked for in its own namespace first and in
     * the global one second, which is the order PHP itself resolves it in.
     */
    public function constantName(Expr\ConstFetch $node): string
    {
        $namespaced = $node->name->getAttribute('namespacedName');
        if ($namespaced instanceof Node\Name && $this->index->findConstant($namespaced->toString()) !== null) {
            return $namespaced->toString();
        }

        return $node->name->toString();
    }

    /**
     * The value of a constant the running process already defines.
     */
    public function readRuntimeConstant(string $name): Domain
    {
        $bare = ltrim($name, '\\');
        if (!str_starts_with($bare, 'PHP_') || !defined($bare)) {
            return Domain::opaque(TypeShape::unknown(), Origin::Unresolved, $bare);
        }
        $value = constant($bare);

        return is_scalar($value) || $value === null ? Domain::literal($value) : Domain::unknown($bare);
    }

    /**
     * The value of a class constant, an enum case, or a `::class` reference.
     */
    public function readClassConstant(
        Expr\ClassConstFetch $node,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        $className = $this->resolveClassName($node->class, $scope);
        $constant = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        if ($className === null || $constant === null) {
            return Domain::opaque(TypeShape::unknown(), Origin::Unresolved, $this->text->render($node));
        }
        if (strtolower($constant) === 'class') {
            return Domain::literal($className);
        }

        $shape = $this->index->findClass($className);
        if ($shape !== null && $shape->enum && array_key_exists($constant, $shape->enumCases)) {
            return Domain::of(new ObjectTerm($shape->name, $constant));
        }

        $declared = $this->index->findClassConstant($className, $constant);

        return $declared === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Unresolved, $className . '::' . $constant)
            : $expressions->evaluate($declared, new Environment(), $scope);
    }

    /**
     * The class a written class reference names, resolving `self` and `static`.
     */
    public function resolveClassName(Node\Name|Expr|Node\Stmt\Class_ $reference, FunctionScope $scope): ?string
    {
        if (!$reference instanceof Node\Name) {
            return null;
        }
        $written = $reference->toString();

        return match (strtolower($written)) {
            'self', 'static', 'parent' => $scope->className,
            default => $written,
        };
    }
}
