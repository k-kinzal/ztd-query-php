<?php

declare(strict_types=1);

namespace Rules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Records class references, independently of constructors and vendor interfaces.
 * Imports, PHPDoc-only names and unresolved dynamic names are not references.
 * This is class-level analysis; it does not establish that a method executes.
 *
 * @implements Collector<Node, array{from: ?string, to: list<string>}>
 * @visibility Rules
 */
final class ClassUsageCollector implements Collector
{
    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return array{from: ?string, to: list<string>}|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        $from = $scope->getTraitReflection()?->getName() ?? $scope->getClassReflection()?->getName();
        if ($node instanceof ClassLike) {
            $from = $node->namespacedName?->toString() ?? $from;
            $names = [];
            if ($node instanceof Class_ || $node instanceof Enum_) {
                $names = $node->implements;
            }
            if ($node instanceof Class_ && $node->extends !== null) {
                $names[] = $node->extends;
            }
            if ($node instanceof Interface_) {
                $names = $node->extends;
            }
            $targets = array_map(static fn (Name $name): string => $scope->resolveName($name), $names);
        } else {
            $targets = $this->references($node, $scope);
        }
        return $targets === [] ? null : ['from' => $from, 'to' => array_values(array_unique($targets))];
    }

    /**
     * @return list<string>
     */
    public function references(Node $node, Scope $scope): array
    {
        if ($node instanceof New_) {
            return $scope->getType($node)->getObjectClassNames();
        }
        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof PropertyFetch || $node instanceof NullsafePropertyFetch) {
            return $scope->getType($node->var)->getObjectClassNames();
        }
        if ($node instanceof StaticCall || $node instanceof StaticPropertyFetch || $node instanceof ClassConstFetch || $node instanceof Instanceof_) {
            if ($node->class instanceof Name) {
                return [$scope->resolveName($node->class)];
            }
            $type = $scope->getType($node->class);
            $names = array_merge($type->getObjectClassNames(), $type->getClassStringObjectType()->getObjectClassNames());
            foreach ($type->getConstantStrings() as $string) {
                $names[] = $string->getValue();
            }
            return $names;
        }
        if ($node instanceof Attribute) {
            return [$scope->resolveName($node->name)];
        }
        if ($node instanceof Catch_) {
            return array_values(array_map(static fn (Name $name): string => $scope->resolveName($name), $node->types));
        }
        if ($node instanceof TraitUse) {
            return array_values(array_map(static fn (Name $name): string => $scope->resolveName($name), $node->traits));
        }
        if ($node instanceof FunctionLike) {
            return $this->typeNames($node->getReturnType(), $scope);
        }
        if ($node instanceof Param || $node instanceof Property) {
            return $this->typeNames($node->type, $scope);
        }
        return [];
    }

    /**
     * @return list<string>
     */
    public function typeNames(?Node $type, Scope $scope): array
    {
        if ($type instanceof Name) {
            return [$scope->resolveName($type)];
        }
        if ($type instanceof NullableType) {
            return $this->typeNames($type->type, $scope);
        }
        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $names = [];
            foreach ($type->types as $member) {
                $names = array_merge($names, $this->typeNames($member, $scope));
            }
            return $names;
        }
        return [];
    }
}
