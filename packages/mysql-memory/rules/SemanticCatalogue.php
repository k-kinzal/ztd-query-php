<?php

declare(strict_types=1);

namespace Rules;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Discovers public semantic value types from source, independently of the generator inventory.
 *
 * @visibility Rules
 */
final class SemanticCatalogue
{
    /**
     * @var array<string, string>|null
     */
    private ?array $values = null;

    /**
     * @return array<string, string> Semantic value classes and their required generator classes
     * @throws RuntimeException When a dependency's source cannot be read or loaded
     */
    public function generators(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }
        $generators = [];
        foreach (['sql-semantics' => ['SqlSemantics\\', 'Tests\\Generator\\Semantics\\'], 'sql-semantics-mysql' => ['SqlSemantics\\Platform\\MySql\\', 'Tests\\Generator\\MySql\\']] as $package => [$source, $target]) {
            $directory = dirname(__DIR__) . '/vendor/k-kinzal/' . $package . '/src/';
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $text = file_get_contents($file->getPathname());
                if ($text === false) {
                    throw new RuntimeException('Cannot read ' . $file->getPathname());
                }
                if (!str_contains($text, '@visibility public')) {
                    continue;
                }
                $nodes = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse($text) ?? []);
                $value = (new NodeFinder())->findFirstInstanceOf($nodes, Node\Stmt\ClassLike::class);
                if ($value === null || $value->namespacedName === null || !str_contains($value->getDocComment()?->getText() ?? '', '@visibility public') || !$this->isValue($value)) {
                    continue;
                }
                $class = $value->namespacedName->toString();
                if (!class_exists($class) && !interface_exists($class)) {
                    throw new RuntimeException('Cannot load semantic value ' . $class);
                }
                $generator = $target . substr($class, strlen($source)) . 'Generator';
                $generators[$class] = $generator;
            }
        }
        ksort($generators);
        return $this->values = $generators;
    }

    /**
     * The value domain includes public interfaces, enums, Snapshot records and readonly context records.
     */
    public function isValue(Node\Stmt\ClassLike $node): bool
    {
        if ($node instanceof Node\Stmt\Enum_ || $node instanceof Node\Stmt\Interface_) {
            return true;
        }
        if (!$node instanceof Node\Stmt\Class_) {
            return false;
        }
        foreach ($node->stmts as $statement) {
            if ($statement instanceof Node\Stmt\TraitUse) {
                foreach ($statement->traits as $trait) {
                    if ($trait->toString() === 'SqlSemantics\\Statement\\Snapshot') {
                        return true;
                    }
                }
            }
        }
        $contract = str_starts_with($node->namespacedName?->toString() ?? '', 'SqlSemantics\\Contract\\')
            || in_array('SqlSemantics\\Contract\\Mode', array_map(static fn (Node\Name $name): string => $name->toString(), $node->implements), true);
        if ($contract && $node->extends === null) {
            foreach ($node->getProperties() as $property) {
                if ($property->isPublic() && $property->isReadonly() && !$property->isStatic()) {
                    return true;
                }
            }
            foreach ($node->getMethod('__construct')->params ?? [] as $parameter) {
                if (($parameter->flags & Node\Stmt\Class_::MODIFIER_PUBLIC) !== 0 && ($parameter->flags & Node\Stmt\Class_::MODIFIER_READONLY) !== 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
