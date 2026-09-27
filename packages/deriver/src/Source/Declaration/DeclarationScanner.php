<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration;

use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\ConstantSignatures;
use Deriver\Value\Term;
use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

/**
 * Indexes declarations without traversing or evaluating callable bodies.
 * @visibility root
 */
final class DeclarationScanner
{
    /**
     * @param ProjectIndex $index Destination index
     */
    public function __construct(public readonly ProjectIndex $index)
    {
    }

    /**
     * Reads a file's declared scalar coercion mode.
     * @param array<Stmt> $nodes Top-level statements
     * @return bool Whether strict_types is enabled
     */
    public function strict(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Declare_) {
                foreach ($node->declares as $declare) {
                    if ($declare->key->toString() === 'strict_types' && $declare->value instanceof Scalar\Int_) {
                        return $declare->value->value === 1;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Indexes unconditional declarations in a namespace.
     * @param array<Stmt> $nodes Source statements
     * @param string $path Source path
     * @param bool $strict Scalar coercion mode
     */
    public function scan(array $nodes, string $path, bool $strict): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Namespace_) {
                $this->scan($node->stmts, $path, $strict);
            } elseif ($node instanceof Stmt\Function_) {
                $name = $node->namespacedName?->toString() ?? $node->name->toString();
                $this->index->register(new CallableSource($name, $node, $path, strict: $strict));
            } elseif ($node instanceof Stmt\Const_) {
                foreach ($node->consts as $constant) {
                    $name = $constant->namespacedName?->toString() ?? $constant->name->toString();
                    $this->index->constantSources[$name] = new CallableSource($name, $constant->value, $path, strict: $strict);
                }
            } elseif ($node instanceof Stmt\ClassLike && $node->name !== null) {
                $this->classDeclaration($node, $path, $strict);
            }
        }
    }

    /**
     * Captures methods, inheritance, trait uses, and property defaults.
     * @param Stmt\ClassLike $node Class, trait, interface, or enum
     * @param string $path Source path
     * @param bool $strict Scalar coercion mode
     */
    public function classDeclaration(Stmt\ClassLike $node, string $path, bool $strict): void
    {
        $name = $node->namespacedName?->toString() ?? $node->name?->toString() ?? '';
        $this->index->classSources[strtolower($name)] = new CallableSource($name, $node, $path, $name, $strict);
        $readonly = $node instanceof Stmt\Class_ && $node->isReadonly();
        $methods = [];
        $properties = [];
        $traits = [];
        foreach ($node->stmts as $statement) {
            if ($statement instanceof Stmt\ClassMethod) {
                $symbol = $name . '::' . $statement->name->toString();
                $methods[strtolower($statement->name->toString())] = $symbol;
                $this->index->register(new CallableSource($symbol, $statement, $path, $name, $strict));
                $properties += $this->promotions($statement, $name, $readonly);
            } elseif ($statement instanceof Stmt\Property) {
                $properties += $this->properties($statement, $path, $name, $readonly);
            } elseif ($statement instanceof Stmt\TraitUse) {
                foreach ($statement->traits as $trait) {
                    $traits[] = $trait->toString();
                }
            }
        }
        (new ConstantSignatures())->initializers($this->index, $node, $path, $name, $strict);
        $parent = $node instanceof Stmt\Class_ ? ($node->extends?->toString() ?? '') : '';
        $interfaces = $node instanceof Stmt\Class_ || $node instanceof Stmt\Enum_ ? $node->implements : ($node instanceof Stmt\Interface_ ? $node->extends : []);
        $this->index->classIndex[strtolower($name)] = new ClassDeclaration($name, $parent, array_values(array_map(static fn (Node\Name $name): string => $name->toString(), $interfaces)), $traits, $methods, $properties, $this->constants($node), $node instanceof Stmt\Enum_ || ($node instanceof Stmt\Class_ && $node->isFinal()), $node instanceof Stmt\Trait_ || $node instanceof Stmt\Class_ && $node->isAbstract(), $node instanceof Stmt\Interface_, $readonly, $node instanceof Stmt\Enum_, constantDeclarations: (new ConstantSignatures())->read($this->index, $node, $name));
    }

    /**
     * Captures a property's default as an unevaluated expression graph.
     * @param Stmt\Property $node Property declaration
     * @param string $path Source path
     * @param string $class Class scope
     * @param bool $readonlyClass Whether the enclosing class is readonly
     * @return array<string, PropertyDeclaration> Declared properties
     */
    public function properties(Stmt\Property $node, string $path, string $class, bool $readonlyClass = false): array
    {
        $result = [];
        $compiler = new CallableCompiler($this->index);
        foreach ($node->props as $property) {
            $name = $property->name->toString();
            $default = $property->default === null ? null : $compiler->expression($property->default, $path, $class . '::$' . $name, $class);
            $result[$name] = new PropertyDeclaration($name, $class, $compiler->type($node->type), $node->isPrivate() ? 'private' : ($node->isProtected() ? 'protected' : 'public'), $node->isStatic(), $default, $readonlyClass || $node->isReadonly());
        }
        return $result;
    }

    /**
     * Captures scalar constants and enum case identity.
     * @param Stmt\ClassLike $node Class declaration
     * @return array<string, Term> Constants
     */
    public function constants(Stmt\ClassLike $node): array
    {
        $constants = [];
        foreach ($node->stmts as $statement) {
            if ($statement instanceof Stmt\ClassConst) {
                foreach ($statement->consts as $constant) {
                    $value = $constant->value;
                    $constants[$constant->name->toString()] = $value instanceof Scalar\String_ || $value instanceof Scalar\Int_ || $value instanceof Scalar\Float_ ? Term::constant($value->value) : Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE');
                }
            } elseif ($statement instanceof Stmt\EnumCase) {
                $class = $node->namespacedName?->toString() ?? '';
                $value = $statement->expr;
                $fields = ['name' => Term::constant($statement->name->toString())];
                if ($value instanceof Scalar\String_ || $value instanceof Scalar\Int_) {
                    $fields['value'] = Term::constant($value->value);
                }
                $constants[$statement->name->toString()] = new Term('enum', $class . '::' . $statement->name->toString(), $fields, ['class' => $class]);
            }
        }
        return $constants;
    }

    /**
     * Captures promoted properties independently from constructor parameter defaults.
     * @param Stmt\ClassMethod $method Method declaration
     * @param string $class Declaring class
     * @param bool $readonlyClass Whether the enclosing class is readonly
     * @return array<string, PropertyDeclaration> Promoted property definitions
     */
    public function promotions(Stmt\ClassMethod $method, string $class, bool $readonlyClass = false): array
    {
        $properties = [];
        foreach ($method->params as $parameter) {
            if (!$parameter->isPromoted() || !$parameter->var instanceof Node\Expr\Variable || !is_string($parameter->var->name)) {
                continue;
            }
            $name = $parameter->var->name;
            $properties[$name] = new PropertyDeclaration($name, $class, (new CallableCompiler($this->index))->type($parameter->type), $parameter->isPrivate() ? 'private' : ($parameter->isProtected() ? 'protected' : 'public'), readonly: $readonlyClass || $parameter->isReadonly());
        }
        return $properties;
    }
}
