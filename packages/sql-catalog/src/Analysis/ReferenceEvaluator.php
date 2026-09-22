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

    private ConstantReader $constants;

    /**
     * Wires the reader to the declarations it resolves names against.
     */
    public function __construct(ProgramIndex $index, ExternalInput $external, NodeText $text)
    {
        $this->index = $index;
        $this->external = $external;
        $this->text = $text;
        $this->constants = new ConstantReader($index, $text);
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
            return $this->constants->readConstant($node, $scope, $expressions);
        }
        if ($node instanceof Expr\ClassConstFetch) {
            return $this->constants->readClassConstant($node, $scope, $expressions);
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
        $origin = Origin::Unresolved;
        foreach ($domain->terms as $term) {
            if ($term instanceof OpaqueTerm && $term->origin === Origin::External) {
                return Origin::External;
            }
            if ($term instanceof OpaqueTerm && $origin === Origin::Unresolved) {
                $origin = $term->origin;
            }
        }

        return $origin;
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
        $tracked = $this->trackedName($node);
        if ($tracked !== null && $environment->has($tracked)) {
            return $environment->read($tracked);
        }
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
        $className = $node->class instanceof Node\Name ? $this->constants->resolveClassName($node->class, $scope) : null;

        return $className === null
            ? Domain::opaque(TypeShape::of(['object']), Origin::Unresolved, 'new')
            : Domain::of(new ObjectTerm($className));
    }

    /**
     * The name a target is tracked under in the environment, or null when it is not tracked.
     *
     * A variable is tracked under its name, and a property of `$this` under
     * `this->property`, so that assigning it earlier in a body is seen when it
     * is read later in the same body.
     */
    public function trackedName(Expr $target): ?string
    {
        if ($target instanceof Expr\Variable) {
            return is_string($target->name) ? $target->name : null;
        }
        if (($target instanceof Expr\PropertyFetch || $target instanceof Expr\NullsafePropertyFetch)
            && $target->var instanceof Expr\Variable && $target->var->name === 'this'
            && $target->name instanceof Node\Identifier) {
            return 'this->' . $target->name->toString();
        }

        return null;
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
        $name = $this->trackedName($target);
        if ($name !== null) {
            $environment->write($name, $value);

            return;
        }
        if ($target instanceof Expr\ArrayDimFetch) {
            $this->assignElement($target, $value, $environment, $scope, $expressions);

            return;
        }
        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            $this->assignList($target, $value, $environment, $scope, $expressions);
        }
    }

    /**
     * Writes the elements of an array into the targets a destructuring assignment lists.
     */
    public function assignList(
        Expr\List_|Expr\Array_ $target,
        Domain $value,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): void {
        $array = $value->soleArray();
        $position = 0;
        foreach ($target->items as $item) {
            if ($item === null) {
                $position++;
                continue;
            }
            $key = $item->key === null ? $position++ : $expressions->evaluate($item->key, $environment, $scope)->soleLiteral()?->value;
            $element = $array === null || $key === null ? null : $this->lookup($array, $key);
            $this->assign(
                $item->value,
                $element ?? Domain::opaque(TypeShape::unknown(), $this->originOf($value), $this->text->render($item->value)),
                $environment,
                $scope,
                $expressions,
            );
        }
    }

    /**
     * Writes a value into an array held by a tracked name, keeping the key when one is written.
     *
     * A write one level down, such as `$parts['where'][] = …`, is not followed
     * element by element: the array it goes into is marked as no longer known
     * in full, so reading it back never claims to have seen all of it.
     */
    public function assignElement(
        Expr\ArrayDimFetch $target,
        Domain $value,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): void {
        $name = $this->trackedName($target->var);
        if ($name === null) {
            $this->loseTrack($target->var, $environment);

            return;
        }
        $key = $target->dim === null ? null : $expressions->evaluate($target->dim, $environment, $scope);
        $array = $environment->has($name) ? $environment->read($name)->soleArray() : null;
        $entries = $array === null ? [] : $array->entries;
        $entries[] = new ArrayEntry($key, $value);

        $environment->write($name, Domain::of(new ArrayTerm($entries, $array === null || $array->complete)));
    }

    /**
     * Marks the array a nested write goes into as no longer known in full.
     */
    public function loseTrack(Expr $target, Environment $environment): void
    {
        while ($target instanceof Expr\ArrayDimFetch) {
            $target = $target->var;
        }
        $name = $this->trackedName($target);
        $array = $name === null || !$environment->has($name) ? null : $environment->read($name)->soleArray();
        if ($name !== null && $array !== null) {
            $environment->write($name, Domain::of(new ArrayTerm($array->entries, false)));
        }
    }
}
