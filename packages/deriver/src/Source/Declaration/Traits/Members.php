<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration\Traits;

use Deriver\ControlFlow\ClassDeclaration;
use Deriver\Result\Frontier;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\ProjectIndex;
use PhpParser\Node\Stmt;

/**
 * Selects trait method implementations before rebinding them into a consuming class.
 * @visibility root
 */
final class Members
{
    /**
     * @param ProjectIndex $index Captured source index
     */
    public function __construct(public readonly ProjectIndex $index)
    {
    }

    /**
     * Collects declared methods by their immediate trait origin.
     * @param ClassDeclaration $class Consuming declaration
     * @return array<string, array<string, CallableSource>> Method name, trait name, source
     */
    public function candidates(ClassDeclaration $class): array
    {
        $methods = [];
        foreach ($class->traits as $trait) {
            foreach ($this->index->classIndex[strtolower($trait)]->methods ?? [] as $name => $symbol) {
                $source = $this->index->declarations[strtolower($symbol)] ?? null;
                if ($source !== null) {
                    $methods[$name][strtolower($trait)] = $source;
                }
            }
        }
        return $methods;
    }

    /**
     * Applies insteadof exclusions without discarding explicitly aliased alternatives.
     * @param array<string, array<string, CallableSource>> $methods Collected implementations
     * @param list<Stmt\TraitUseAdaptation> $adaptations Explicit source adaptations
     * @return array<string, array<string, CallableSource>> Remaining original implementations
     */
    public function precedence(array $methods, array $adaptations): array
    {
        foreach ($adaptations as $adaptation) {
            if (!$adaptation instanceof Stmt\TraitUseAdaptation\Precedence) {
                continue;
            }
            foreach ($adaptation->insteadof as $excluded) {
                unset($methods[strtolower($adaptation->method->toString())][strtolower($excluded->toString())]);
            }
        }
        return $methods;
    }

    /**
     * Applies aliases and visibility changes after resolving original method conflicts.
     * @param array<string, array<string, CallableSource>> $all Original methods including excluded variants
     * @param array<string, CallableSource> $selected Chosen original methods
     * @param list<Stmt\TraitUseAdaptation> $adaptations Explicit source adaptations
     * @return array<string, CallableSource> Method and alias imports
     */
    public function aliases(array $all, array $selected, array $adaptations): array
    {
        foreach ($adaptations as $adaptation) {
            if (!$adaptation instanceof Stmt\TraitUseAdaptation\Alias) {
                continue;
            }
            $name = strtolower($adaptation->method->toString());
            $source = $adaptation->trait === null ? ($selected[$name] ?? null) : ($all[$name][strtolower($adaptation->trait->toString())] ?? null);
            if ($source === null || !$source->node instanceof Stmt\ClassMethod) {
                continue;
            }
            $node = clone $source->node;
            if ($adaptation->newModifier !== null) {
                $visibility = $adaptation->newModifier & 7;
                $node->flags = ($visibility === 0 ? $node->flags : $node->flags & ~7) | $adaptation->newModifier;
            }
            $alias = $adaptation->newName?->toString() ?? $name;
            $selected[strtolower($alias)] = new CallableSource($source->symbol, $node, $source->path, $source->className, $source->strict, $source->cacheSalt);
        }
        return $selected;
    }

    /**
     * Rejects unresolved concrete conflicts instead of selecting the first trait.
     * @param ClassDeclaration $class Consuming declaration
     * @param array<string, array<string, CallableSource>> $candidates Eligible originals
     * @return array<string, CallableSource> Unambiguous implementations
     */
    public function select(ClassDeclaration $class, array $candidates): array
    {
        $selected = [];
        foreach ($candidates as $name => $sources) {
            if (isset($class->methods[$name])) {
                continue;
            }
            $concrete = [];
            foreach ($sources as $source) {
                if ($source->node instanceof Stmt\ClassMethod && $source->node->stmts !== null) {
                    $concrete[$source->path . ':' . $source->node->getStartFilePos()] = $source;
                }
            }
            if (count($concrete) > 1) {
                $source = array_values($concrete)[0];
                $this->index->issues[] = new Frontier('INVALID_PROGRAM', $this->index->builder($source->path)->source($source->node), 'trait-method-conflict:' . $class->name . '::' . $name);
                continue;
            }
            if ($concrete !== []) {
                $selected[$name] = array_values($concrete)[0];
            }
        }
        return $selected;
    }
}
