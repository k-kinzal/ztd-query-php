<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Reads the value behind a name: a variable, a property, a constant or an element.
 *
 * @visibility root
 */
final class ReferenceEvaluator
{
    private ProgramIndex $index;

    private ExternalInput $external;

    private NodeText $text;

    /**
     * Wires the reader to the declarations it resolves names against.
     */
    public function __construct(ProgramIndex $index, ExternalInput $external, NodeText $text)
    {
        $this->index = $index;
        $this->external = $external;
        $this->text = $text;
    }

    /**
     * The value behind the reference, or null when the expression is not one.
     */
    public function evaluate(
        Expr $node,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): ?Domain {
        if ($node instanceof Expr\Variable) {
            return $this->readVariable($node, $environment, $scope);
        }
        if ($node instanceof Expr\Array_) {
            return $this->readArray($node, $environment, $scope, $expressions);
        }
        if ($node instanceof Expr\ArrayDimFetch) {
            return $this->readElement($node, $environment, $scope, $expressions);
        }
        if ($node instanceof Expr\ConstFetch) {
            return $this->readConstant($node, $scope, $expressions);
        }
        if ($node instanceof Expr\ClassConstFetch) {
            return $this->readClassConstant($node, $scope, $expressions);
        }
        if ($node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch) {
            return $this->readProperty($node, $environment, $scope, $expressions);
        }
        if ($node instanceof Expr\New_) {
            return $this->readInstance($node, $scope);
        }
        if ($node instanceof Expr\StaticPropertyFetch) {
            return Domain::opaque(TypeShape::unknown(), Origin::Property, $this->text->render($node));
        }
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            return Domain::of(new ObjectTerm('Closure'));
        }

        return null;
    }

    /**
     * The value bound to a variable, or what is known about it when it is external.
     */
    public function readVariable(Expr\Variable $node, Environment $environment, FunctionScope $scope): Domain
    {
        if (!is_string($node->name)) {
            return Domain::opaque(TypeShape::unknown(), Origin::Unresolved, 'variable variable');
        }
        if ($node->name === 'this') {
            return $scope->className === null
                ? Domain::opaque(TypeShape::unknown(), Origin::Unresolved, '$this')
                : Domain::of(new ObjectTerm($scope->className));
        }
        if ($this->external->isVariable($node->name)) {
            return Domain::opaque(TypeShape::unknown(), Origin::External, '$' . $node->name);
        }

        return $environment->read($node->name);
    }

    /**
     * The array an array literal builds.
     */
    public function readArray(
        Expr\Array_ $node,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        $entries = [];
        $complete = true;
        foreach ($node->items as $item) {
            if ($item->unpack) {
                $complete = false;
                continue;
            }
            $entries[] = new ArrayEntry(
                $item->key === null ? null : $expressions->evaluate($item->key, $environment, $scope),
                $expressions->evaluate($item->value, $environment, $scope),
            );
        }

        return Domain::of(new ArrayTerm($entries, $complete));
    }

    /**
     * The value of an array element, when both the array and the key resolved.
     */
    public function readElement(
        Expr\ArrayDimFetch $node,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        $base = $expressions->evaluate($node->var, $environment, $scope);
        $array = $base->soleArray();
        $key = $node->dim === null ? null : $expressions->evaluate($node->dim, $environment, $scope)->soleLiteral();
        if ($array !== null && $key !== null) {
            $found = $this->lookup($array, $key->value);
            if ($found !== null) {
                return $found;
            }
        }

        return Domain::opaque(TypeShape::unknown(), $this->originOf($base), $this->text->render($node));
    }

    /**
     * The element stored under a key, or null when the array does not hold one.
     */
    public function lookup(ArrayTerm $array, string|int|float|bool|null $key): ?Domain
    {
        $wanted = is_bool($key) || $key === null ? null : (string) $key;
        if ($wanted === null) {
            return null;
        }
        $position = 0;
        foreach ($array->entries as $entry) {
            $entryKey = $entry->key === null ? $position++ : $entry->scalarKey();
            if ($entryKey !== null && (string) $entryKey === $wanted) {
                return $entry->value;
            }
        }

        return null;
    }

    /**
     * Where the unresolved parts of a domain come from.
     */
    public function originOf(Domain $domain): Origin
    {
        foreach ($domain->terms as $term) {
            if ($term instanceof OpaqueTerm && $term->origin === Origin::External) {
                return Origin::External;
            }
        }

        return Origin::Unresolved;
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
     * The value of a property read, resolving a settled default when there is one.
     */
    public function readProperty(
        Expr\PropertyFetch|Expr\NullsafePropertyFetch $node,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        $receiver = $expressions->evaluate($node->var, $environment, $scope);
        $owner = $receiver->type()->soleClassName();
        if ($name === null || $owner === null) {
            return Domain::opaque(TypeShape::unknown(), Origin::Property, $this->text->render($node));
        }

        $enum = $this->readEnumProperty($receiver, $owner, $name, $scope, $expressions);
        if ($enum !== null) {
            return $enum;
        }

        foreach ($this->index->lineage($owner) as $shape) {
            $default = $shape->settledDefault($name);
            if ($default !== null) {
                return $expressions->evaluate($default, new Environment(), $scope);
            }
        }

        $declared = $this->index->findPropertyType($owner, $name);

        return Domain::opaque($declared ?? TypeShape::unknown(), Origin::Property, $this->text->render($node));
    }

    /**
     * The value behind `->value` or `->name` on an enum.
     *
     * A value typed as a backed enum can only ever be one of that enum's cases,
     * so reading its backing value resolves to the union of them. That is what
     * turns a parameter typed `Status` into the handful of strings the column
     * can hold rather than an opaque `string`.
     */
    public function readEnumProperty(
        Domain $receiver,
        string $owner,
        string $property,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): ?Domain {
        $shape = $this->index->findClass($owner);
        if ($shape === null || !$shape->enum || ($property !== 'value' && $property !== 'name')) {
            return null;
        }

        $selected = $receiver->soleObject()?->enumCase;
        $result = null;
        foreach ($shape->enumCases as $case => $backing) {
            if ($selected !== null && $case !== $selected) {
                continue;
            }
            $value = $property === 'name' || $backing === null
                ? Domain::literal($case)
                : $expressions->evaluate($backing, new Environment(), $scope);
            $result = $result === null ? $value : $result->union($value);
        }

        return $result;
    }

    /**
     * The object an instantiation produces.
     */
    public function readInstance(Expr\New_ $node, FunctionScope $scope): Domain
    {
        $className = $node->class instanceof Node\Name ? $this->resolveClassName($node->class, $scope) : null;

        return $className === null
            ? Domain::opaque(TypeShape::of(['object']), Origin::Unresolved, 'new')
            : Domain::of(new ObjectTerm($className));
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

    /**
     * Writes a value to whatever the target expression names.
     */
    public function assign(
        Expr $target,
        Domain $value,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): void {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $environment->write($target->name, $value);

            return;
        }
        if ($target instanceof Expr\ArrayDimFetch) {
            $this->assignElement($target, $value, $environment, $scope, $expressions);
        }
    }

    /**
     * Writes a value into an array held by a variable, keeping the key when one is written.
     */
    public function assignElement(
        Expr\ArrayDimFetch $target,
        Domain $value,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): void {
        if (!$target->var instanceof Expr\Variable || !is_string($target->var->name)) {
            return;
        }
        $name = $target->var->name;
        $key = $target->dim === null ? null : $expressions->evaluate($target->dim, $environment, $scope);
        $array = $environment->read($name)->soleArray();
        $entries = $array === null ? [] : $array->entries;
        $entries[] = new ArrayEntry($key, $value);

        $environment->write($name, Domain::of(new ArrayTerm($entries, $array !== null && $array->complete)));
    }
}
