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

    /** @var array<string, array{implements: list<string>, extends: string|null, parameters: list<string>}> */
    private array $structure = [];

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
                $nodes = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse($text) ?? []);
                $value = (new NodeFinder())->findFirstInstanceOf($nodes, Node\Stmt\ClassLike::class);
                if ($value === null || $value->namespacedName === null) {
                    continue;
                }
                $imports = [];
                foreach ((new NodeFinder())->findInstanceOf($nodes, Node\Stmt\UseUse::class) as $use) {
                    $imports[$use->getAlias()->toString()] = $use->name->toString();
                }
                $class = $value->namespacedName->toString();
                $this->structure[$class] = $this->structure($value, $imports, substr($class, 0, strrpos($class, '\\') ?: 0));
                if (!str_contains($value->getDocComment()?->getText() ?? '', '@visibility public') || !$this->isValue($value)) {
                    continue;
                }
                if (!str_starts_with($class, $source)) {
                    throw new RuntimeException('Semantic source declares a type outside its namespace: ' . $class);
                }
                $generator = $target . substr($class, strlen($source)) . 'Generator';
                $generators[$class] = $generator;
                $this->locations[$class] = ['file' => $file->getPathname(), 'line' => $value->getStartLine()];
            }
        }
        $generators = array_intersect_key($generators, array_flip($this->reachable(array_keys($generators))));
        ksort($generators);
        return $this->values = $generators;
    }

    /**
     * The types a generated statement can hold: the statements and, transitively, the types of
     * their constructor parameters other than the analysis information the publication derives,
     * with the implementors of every reachable interface and the subclasses of every reachable parent.
     *
     * @param list<string> $classes
     * @return list<string>
     */
    public function reachable(array $classes): array
    {
        $implementors = [];
        foreach ($this->structure as $class => $structure) {
            foreach ([...$structure['implements'], ...($structure['extends'] === null ? [] : [$structure['extends']])] as $parent) {
                $implementors[$parent][] = $class;
            }
        }
        $reached = [];
        $pending = array_values(array_filter($classes, fn (string $class): bool => $this->isRoot($class)));
        while ($pending !== []) {
            $class = array_pop($pending);
            if (isset($reached[$class])) {
                continue;
            }
            $reached[$class] = true;
            array_push($pending, ...($this->structure[$class]['parameters'] ?? []), ...($implementors[$class] ?? []));
        }

        return array_values(array_filter(array_keys($reached), static fn (string $class): bool => in_array($class, $classes, true)));
    }

    /**
     * Tells whether a type is an analysis result in its own right: a statement.
     */
    private function isRoot(string $class): bool
    {
        $pending = [$class];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (!isset($this->structure[$current])) {
                return $current === 'SqlSemantics\\Statement\\AnalyzedStatement';
            }
            if (in_array('SqlSemantics\\Statement\\AnalyzedStatement', $this->structure[$current]['implements'], true)) {
                return true;
            }
            array_push($pending, ...$this->structure[$current]['implements'], ...($this->structure[$current]['extends'] === null ? [] : [$this->structure[$current]['extends']]));
        }

        return false;
    }

    /**
     * The parents and the constructor parameter types of a declaration.
     *
     * @param array<string, string> $imports The imported names of the file, by alias
     * @param string $namespace The namespace of the declaration
     * @return array{implements: list<string>, extends: string|null, parameters: list<string>}
     */
    private function structure(Node\Stmt\ClassLike $node, array $imports, string $namespace): array
    {
        $implements = [];
        $extends = null;
        if ($node instanceof Node\Stmt\Class_) {
            $implements = array_map(static fn (Node\Name $name): string => $name->toString(), $node->implements);
            $extends = $node->extends?->toString();
        } elseif ($node instanceof Node\Stmt\Interface_) {
            $implements = array_map(static fn (Node\Name $name): string => $name->toString(), $node->extends);
        } elseif ($node instanceof Node\Stmt\Enum_) {
            $implements = array_map(static fn (Node\Name $name): string => $name->toString(), $node->implements);
        }
        $parameters = [];
        $constructor = $node->getMethod('__construct');
        foreach ($constructor?->params ?? [] as $parameter) {
            $name = $parameter->var instanceof Node\Expr\Variable && is_string($parameter->var->name) ? $parameter->var->name : '';
            if ($name === 'inputSource' || str_ends_with($name, 'Information')) {
                continue;
            }
            array_push($parameters, ...$this->typeNames($parameter->type));
            if (preg_match('/@param\s+(\S+)\s+\$' . preg_quote($name, '/') . '\b/', $constructor?->getDocComment()?->getText() ?? '', $match) === 1) {
                foreach (preg_split('/[^A-Za-z0-9_\\\\]+/', $match[1]) ?: [] as $token) {
                    if ($token === '' || preg_match('/^[a-z]/', $token) === 1) {
                        continue;
                    }
                    [$head, $rest] = explode('\\', ltrim($token, '\\'), 2) + [1 => null];
                    $parameters[] = match (true) {
                        str_starts_with($token, '\\') => ltrim($token, '\\'),
                        isset($imports[$head]) => $imports[$head] . ($rest === null ? '' : '\\' . $rest),
                        default => $namespace . '\\' . $token,
                    };
                }
            }
        }

        return ['implements' => $implements, 'extends' => $extends, 'parameters' => array_values(array_unique($parameters))];
    }

    /**
     * The class names of a declared type.
     *
     * @return list<string>
     */
    private function typeNames(Node\Identifier|Node\Name|Node\ComplexType|null $type): array
    {
        return match (true) {
            $type instanceof Node\Name => [$type->toString()],
            $type instanceof Node\NullableType => $this->typeNames($type->type),
            $type instanceof Node\UnionType, $type instanceof Node\IntersectionType => array_merge(...array_map(fn (Node\Identifier|Node\Name|Node\ComplexType $part): array => $this->typeNames($part), $type->types)),
            default => [],
        };
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
