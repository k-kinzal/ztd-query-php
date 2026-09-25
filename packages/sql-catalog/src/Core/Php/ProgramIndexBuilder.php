<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Php;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
use SqlCatalog\Core\Type\TypeShape;

/**
 * Collects every declaration of a parsed source tree into one index.
 *
 * @visibility root
 */
final class ProgramIndexBuilder
{
    private NodeFinder $finder;

    private TypeReader $types;

    /**
     * Builds a collector that reads declarations with the given type reader.
     */
    public function __construct(?TypeReader $types = null)
    {
        $this->finder = new NodeFinder();
        $this->types = $types ?? new TypeReader();
    }

    /**
     * The index of every declaration in the given files.
     *
     * @param list<ParsedFile> $files
     */
    public function build(array $files): ProgramIndex
    {
        $classes = [];
        $functions = [];
        $constants = [];

        foreach ($files as $file) {
            foreach ($this->finder->findInstanceOf($file->statements, Node::class) as $node) {
                if ($node instanceof ClassLike) {
                    $shape = $this->readClass($node, $file->path);
                    if ($shape !== null) {
                        $classes[strtolower($shape->name)] = $shape;
                    }
                    continue;
                }
                if ($node instanceof Function_) {
                    $name = $node->namespacedName?->toString() ?? $node->name->toString();
                    $functions[strtolower($name)] = new FunctionShape(
                        $name,
                        $this->readParameters($node->params),
                        $this->types->read($node->returnType),
                        $node,
                        $file->path,
                    );
                    continue;
                }
                if ($node instanceof Node\Stmt\Const_) {
                    foreach ($node->consts as $const) {
                        $constants[$const->namespacedName?->toString() ?? $const->name->toString()] = $const->value;
                    }
                }
            }
        }

        return new ProgramIndex($classes, $functions, $constants);
    }

    /**
     * The shape of one class-like declaration, or null when it has no resolvable name.
     */
    public function readClass(ClassLike $node, string $file = ''): ?ClassShape
    {
        $name = $node->namespacedName?->toString() ?? $node->name?->toString();
        if ($name === null) {
            return null;
        }

        $methods = [];
        $properties = [];
        foreach ($node->getMethods() as $method) {
            $methods[strtolower($method->name->toString())] = $this->readMethod($name, $method, $file);
            $properties += $this->readPromotedProperties($method);
        }
        $members = $this->readMembers($node);

        return new ClassShape(
            $name,
            $node instanceof Class_ ? $node->extends?->toString() : null,
            $this->readParentNames($node),
            $members['traits'],
            $node instanceof Enum_,
            $members['constants'],
            $members['cases'],
            array_merge($properties, $members['properties']),
            $methods,
            $members['defaults'],
            $members['assigned'],
            $node->attrGroups !== [],
        );
    }

    /**
     * Everything a class body declares, read in one pass over it.
     *
     * The body is walked once and its declarations are sorted afterwards, which
     * matters on a source tree with thousands of classes in it.
     *
     * @return array{
     *     traits: list<string>,
     *     constants: array<string, Node\Expr>,
     *     cases: array<string, Node\Expr|null>,
     *     properties: array<string, TypeShape>,
     *     defaults: array<string, Node\Expr>,
     *     assigned: array<string, true>
     * }
     */
    public function readMembers(ClassLike $node): array
    {
        $members = ['traits' => [], 'constants' => [], 'cases' => [], 'properties' => [], 'defaults' => [], 'assigned' => []];
        foreach ($this->finder->findInstanceOf([$node], Node::class) as $member) {
            if ($member instanceof TraitUse) {
                foreach ($member->traits as $trait) {
                    $members['traits'][] = $trait->toString();
                }
            }
            if ($member instanceof ClassConst) {
                foreach ($member->consts as $const) {
                    $members['constants'][$const->name->toString()] = $const->value;
                }
            }
            if ($member instanceof EnumCase) {
                $members['cases'][$member->name->toString()] = $member->expr;
            }
            if ($member instanceof Property) {
                foreach ($member->props as $declared) {
                    $members['properties'][$declared->name->toString()] = $this->types->read($member->type);
                    if ($declared->default !== null) {
                        $members['defaults'][$declared->name->toString()] = $declared->default;
                    }
                }
            }
            $target = $member instanceof Node\Expr ? $this->assignmentTarget($member) : null;
            if ($target !== null) {
                $members['assigned'][$target] = true;
            }
        }

        return $members;
    }

    /**
     * The properties the class body assigns to, other than by promotion.
     *
     * @return array<string, true>
     */
    public function readAssignedProperties(ClassLike $node): array
    {
        $assigned = [];
        foreach ($this->finder->findInstanceOf([$node], Node\Expr::class) as $expression) {
            $target = $this->assignmentTarget($expression);
            if ($target !== null) {
                $assigned[$target] = true;
            }
        }

        return $assigned;
    }

    /**
     * The property name an expression assigns to, or null when it assigns to none.
     */
    public function assignmentTarget(Node\Expr $expression): ?string
    {
        $target = match (true) {
            $expression instanceof Node\Expr\Assign => $expression->var,
            $expression instanceof Node\Expr\AssignOp => $expression->var,
            $expression instanceof Node\Expr\AssignRef => $expression->var,
            default => null,
        };
        if (!$target instanceof Node\Expr\PropertyFetch && !$target instanceof Node\Expr\StaticPropertyFetch) {
            return null;
        }

        return $target->name instanceof Node\Identifier ? $target->name->toString() : null;
    }

    /**
     * The interfaces the declaration implements or extends.
     *
     * @return list<string>
     */
    public function readParentNames(ClassLike $node): array
    {
        $names = [];
        if ($node instanceof Class_) {
            $names = $node->implements;
        }
        if ($node instanceof Interface_) {
            $names = $node->extends;
        }
        if ($node instanceof Enum_) {
            $names = $node->implements;
        }

        $resolved = [];
        foreach ($names as $name) {
            $resolved[] = $name->toString();
        }

        return $resolved;
    }

    /**
     * The traits the declaration uses.
     *
     * @return list<string>
     */
    public function readTraitNames(ClassLike $node): array
    {
        $names = [];
        foreach ($this->finder->findInstanceOf([$node], TraitUse::class) as $use) {
            foreach ($use->traits as $trait) {
                $names[] = $trait->toString();
            }
        }

        return $names;
    }

    /**
     * The class constant expressions declared on the class-like.
     *
     * @return array<string, Node\Expr>
     */
    public function readConstants(ClassLike $node): array
    {
        $constants = [];
        foreach ($this->finder->findInstanceOf([$node], ClassConst::class) as $declaration) {
            foreach ($declaration->consts as $const) {
                $constants[$const->name->toString()] = $const->value;
            }
        }

        return $constants;
    }

    /**
     * The enum case backing expressions declared on the class-like.
     *
     * @return array<string, Node\Expr|null>
     */
    public function readEnumCases(ClassLike $node): array
    {
        $cases = [];
        foreach ($this->finder->findInstanceOf([$node], EnumCase::class) as $case) {
            $cases[$case->name->toString()] = $case->expr;
        }

        return $cases;
    }

    /**
     * The properties a constructor promotes.
     *
     * @return array<string, TypeShape>
     */
    public function readPromotedProperties(ClassMethod $method): array
    {
        if (strtolower($method->name->toString()) !== '__construct') {
            return [];
        }

        $properties = [];
        foreach ($method->params as $param) {
            if ($param->flags !== 0 && $param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $properties[$param->var->name] = $this->types->read($param->type);
            }
        }

        return $properties;
    }

    /**
     * The shape of one method declaration.
     */
    public function readMethod(string $className, ClassMethod $method, string $file = ''): MethodShape
    {
        return new MethodShape(
            $className,
            $method->name->toString(),
            $this->readParameters($method->params),
            $this->types->read($method->returnType),
            $method->isStatic(),
            $method,
            $file,
        );
    }

    /**
     * The shapes of a declaration's parameters, in order.
     *
     * @param array<array-key, Node\Param> $params
     * @return list<ParameterShape>
     */
    public function readParameters(array $params): array
    {
        $shapes = [];
        foreach ($params as $param) {
            if (!$param->var instanceof Node\Expr\Variable || !is_string($param->var->name)) {
                continue;
            }
            $shapes[] = new ParameterShape(
                $param->var->name,
                $this->types->read($param->type),
                $param->default,
                $param->variadic,
            );
        }

        return $shapes;
    }
}
