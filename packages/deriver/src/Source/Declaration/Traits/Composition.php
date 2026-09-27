<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration\Traits;

use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Result\Frontier;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\ProjectIndex;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;

/**
 * Imports trait declarations into their lexical consuming classes before lazy body lowering.
 * @visibility root
 */
final class Composition
{
    /**
     * @var array<string, bool> Completed composition visits
     */
    public array $done = [];

    /**
     * @param ProjectIndex $index Complete captured declaration world
     * @param string $version Source manifest digest used by composed graph caches
     */
    public function __construct(public readonly ProjectIndex $index, public readonly string $version)
    {
    }

    /**
     * Composes nested trait uses independently of input file order.
     * @param string $name Consuming class or trait
     * @param list<string> $seen Active composition ancestors
     * @return void
     */
    public function compose(string $name, array $seen = []): void
    {
        $key = strtolower($name);
        $class = $this->index->classIndex[$key] ?? null;
        $source = $this->index->classSources[$key] ?? null;
        if ($class === null || $source === null || isset($this->done[$key])) {
            return;
        }
        if (in_array($key, $seen, true)) {
            $this->index->issues[] = new Frontier('INVALID_PROGRAM', $this->index->builder($source->path)->source($source->node), 'cyclic-trait-composition:' . $name);
            return;
        }
        foreach ($class->traits as $trait) {
            $this->compose($trait, [...$seen, $key]);
            if (!isset($this->index->classIndex[strtolower($trait)])) {
                $this->index->issues[] = new Frontier('INCOMPLETE_SOURCE', $this->index->builder($source->path)->source($source->node), 'missing-trait:' . $trait);
            }
        }
        if ($class->traits !== []) {
            $this->import($class, $source);
        }
        $this->done[$key] = true;
    }

    /**
     * Publishes trait methods, properties and constants with consuming class ownership.
     * @param ClassDeclaration $class Original consuming declaration
     * @param CallableSource $source Captured class syntax
     * @return void
     */
    public function import(ClassDeclaration $class, CallableSource $source): void
    {
        $adaptations = [];
        if ($source->node instanceof Stmt\ClassLike) {
            foreach ($source->node->stmts as $statement) {
                if ($statement instanceof Stmt\TraitUse) {
                    array_push($adaptations, ...$statement->adaptations);
                }
            }
        }
        $selection = new Members($this->index);
        $all = $selection->candidates($class);
        $selected = $selection->aliases($all, $selection->select($class, $selection->precedence($all, $adaptations)), $adaptations);
        $methods = $class->methods;
        foreach ($selected as $name => $member) {
            if (isset($methods[$name])) {
                continue;
            }
            $symbol = $class->name . '::' . $name;
            $node = (new NodeTraverser(new CloningVisitor()))->traverse([$member->node])[0];
            $node = (new NodeTraverser(new LexicalConstants($member->className)))->traverse([$node])[0];
            $this->index->register(new CallableSource($symbol, $node, $member->path, $class->name, $member->strict, $this->version));
            $methods[$name] = $symbol;
        }
        $properties = $class->properties;
        $constants = $class->constants;
        $constantDeclarations = $class->constantDeclarations;
        foreach ($class->traits as $trait) {
            $declaration = $this->index->classIndex[strtolower($trait)] ?? null;
            if ($declaration !== null) {
                $properties += $this->properties($declaration, $class->name);
                $constants += $declaration->constants;
                foreach ($declaration->constantDeclarations as $name => $constant) {
                    $constantDeclarations[$name] ??= new ClassConstant($class->name, $name, $constant->visibility, $constant->type, $constant->enum);
                }
                $this->constants($declaration, $class->name);
            }
        }
        $this->index->classIndex[strtolower($class->name)] = new ClassDeclaration($class->name, $class->parent, $class->interfaces, $class->traits, $methods, $properties, $constants, $class->final, $class->abstract, $class->interface, $class->readonly, $class->enum, true, $constantDeclarations);
    }

    /**
     * Rebinds property defaults and private slots to the class receiving the trait.
     * @param ClassDeclaration $trait Composed trait declaration
     * @param string $class Consuming lexical class
     * @return array<string, PropertyDeclaration> Imported properties
     */
    public function properties(ClassDeclaration $trait, string $class): array
    {
        $properties = [];
        foreach ($trait->properties as $name => $property) {
            $default = $property->default === null ? null : (new PropertyScope())->graph($property->default, $class);
            $properties[$name] = new PropertyDeclaration($name, $class, $property->type, $property->visibility, $property->static, $default, $property->readonly);
        }
        return $properties;
    }

    /**
     * Copies unevaluated trait constants so self and parent refer to the consumer.
     * @param ClassDeclaration $trait Composed trait declaration
     * @param string $class Consuming lexical class
     * @return void
     */
    public function constants(ClassDeclaration $trait, string $class): void
    {
        foreach ($trait->constants as $name => $_) {
            $source = $this->index->constantSources[strtolower($trait->name) . '::' . $name] ?? null;
            if ($source !== null) {
                $symbol = strtolower($class) . '::' . $name;
                $this->index->constantSources[$symbol] ??= new CallableSource($symbol, $source->node, $source->path, $class, $source->strict, $this->version);
            }
        }
    }
}
