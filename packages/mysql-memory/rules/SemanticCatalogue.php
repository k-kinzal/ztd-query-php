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
     * @var array<string, array{file: string, line: int}>
     */
    private array $locations = [];

    /**
     * @param array<string, array{string, string}>|null $sources Source directories with their model and generator namespace prefixes; null selects the installed semantic packages
     */
    public function __construct(private readonly ?array $sources = null)
    {
    }

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
        $sources = $this->sources ?? [
            dirname(__DIR__) . '/vendor/k-kinzal/sql-semantics/src' => ['SqlSemantics\\', 'Tests\\Generator\\Semantics\\'],
            dirname(__DIR__) . '/vendor/k-kinzal/sql-semantics-mysql/src' => ['SqlSemantics\\Platform\\MySql\\', 'Tests\\Generator\\MySql\\'],
        ];
        foreach ($sources as $directory => [$source, $target]) {
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
                if (!str_starts_with($class, $source)) {
                    throw new RuntimeException('Semantic source declares a type outside its namespace: ' . $class);
                }
                $generator = $target . substr($class, strlen($source)) . 'Generator';
                $generators[$class] = $generator;
                $this->locations[$class] = ['file' => $file->getPathname(), 'line' => $value->getStartLine()];
            }
        }
        ksort($generators);
        return $this->values = $generators;
    }

    /**
     * The value domain includes public statement records independently of traits,
     * public interfaces/enums and readonly contract records. Services outside the
     * model namespaces do not become values merely by having public constructors.
     */
    public function isValue(Node\Stmt\ClassLike $node): bool
    {
        if ($node instanceof Node\Stmt\Enum_ || $node instanceof Node\Stmt\Interface_) {
            return true;
        }
        if (!$node instanceof Node\Stmt\Class_) {
            return false;
        }
        $name = $node->namespacedName?->toString() ?? '';
        if (str_starts_with($name, 'SqlSemantics\\Statement\\') || str_starts_with($name, 'SqlSemantics\\Platform\\MySql\\Statement\\')) {
            return true;
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

    /**
     * Identifies the declaration which requires a generator, even if no generator exists.
     *
     * @return array{file: string, line: int}
     * @throws RuntimeException When the name is outside the semantic inventory
     */
    public function location(string $model): array
    {
        $this->generators();
        return $this->locations[$model] ?? throw new RuntimeException('Unknown semantic model: ' . $model);
    }
}
