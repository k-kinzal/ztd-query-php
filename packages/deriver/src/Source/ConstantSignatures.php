<?php

declare(strict_types=1);

namespace Deriver\Source;

use Deriver\ControlFlow\ClassConstant;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\ProjectIndex;
use PhpParser\Node\Stmt;

/**
 * Captures access and initializer contracts without evaluating constant expressions.
 * @visibility root
 */
final class ConstantSignatures
{
    /**
     * Reads case-sensitive constant declarations from one resolved class-like node.
     * @param ProjectIndex $index Captured declaration world
     * @param Stmt\ClassLike $node Class, trait, interface, or enum
     * @param string $class Declaring class name
     * @return array<string, ClassConstant> Constant contracts
     */
    public function read(ProjectIndex $index, Stmt\ClassLike $node, string $class): array
    {
        $result = [];
        foreach ($node->stmts as $statement) {
            if ($statement instanceof Stmt\ClassConst) {
                $visibility = $statement->isPrivate() ? 'private' : ($statement->isProtected() ? 'protected' : 'public');
                foreach ($statement->consts as $constant) {
                    $name = $constant->name->toString();
                    $result[$name] = new ClassConstant($class, $name, $visibility, (new CallableCompiler($index))->type($statement->type));
                }
            } elseif ($statement instanceof Stmt\EnumCase && $node instanceof Stmt\Enum_) {
                $name = $statement->name->toString();
                $result[$name] = new ClassConstant($class, $name, type: $node->scalarType?->toString() ?? 'mixed', enum: true);
            }
        }
        return $result;
    }

    /**
     * Captures class constant and enum backing expressions in their declaring scope.
     * @param ProjectIndex $index Declaration destination
     * @param Stmt\ClassLike $node Resolved class syntax
     * @param string $path Captured file path
     * @param string $class Declaring class name
     * @param bool $strict File scalar mode
     */
    public function initializers(ProjectIndex $index, Stmt\ClassLike $node, string $path, string $class, bool $strict): void
    {
        foreach ($node->stmts as $statement) {
            if ($statement instanceof Stmt\ClassConst) {
                foreach ($statement->consts as $constant) {
                    $symbol = strtolower($class) . '::' . $constant->name->toString();
                    $index->constantSources[$symbol] = new CallableSource($symbol, $constant->value, $path, $class, $strict);
                }
            } elseif ($statement instanceof Stmt\EnumCase && $statement->expr !== null) {
                $symbol = strtolower($class) . '::' . $statement->name->toString();
                $index->constantSources[$symbol] = new CallableSource($symbol, $statement->expr, $path, $class, $strict);
            }
        }
    }
}
