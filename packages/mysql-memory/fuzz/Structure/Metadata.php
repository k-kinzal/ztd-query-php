<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use PhpParser\NameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionClass;
use RuntimeException;

/**
 * Reads retained field and constructor types; no SQL or execution rule enters generation.
 */
final class Metadata
{
    /**
     * @var array<class-string, array{ReflectionClass<object>, array<string, TypeNode>, NameContext}>
     */
    private array $classes = [];

    /**
     * @param class-string $class The constructor to inspect
     * @throws RuntimeException When the source cannot be read
     * @return array{ReflectionClass<object>, array<string, TypeNode>, NameContext}
     */
    public function of(string $class): array
    {
        if (isset($this->classes[$class])) {
            return $this->classes[$class];
        }
        $reflection = new ReflectionClass($class);
        $config = new ParserConfig([]);
        $lexer = new Lexer($config);
        $constant = new ConstExprParser($config);
        $parser = new PhpDocParser($config, new TypeParser($config, $constant), $constant);
        $parameters = [];
        $comment = $reflection->getConstructor()?->getDocComment();
        foreach ($parser->parse(new TokenIterator($lexer->tokenize($comment === null || $comment === false ? '/** */' : $comment)))->getParamTagValues() as $tag) {
            $parameters[ltrim($tag->parameterName, '$')] = $tag->type;
        }
        foreach ($reflection->getProperties() as $property) {
            $comment = $property->getDocComment();
            if (!$property->isReadOnly() || $comment === false) {
                continue;
            }
            foreach ($parser->parse(new TokenIterator($lexer->tokenize($comment)))->getVarTagValues() as $tag) {
                $parameters[$property->getName()] = $tag->type;
            }
        }
        $file = $reflection->getFileName();
        $source = $file === false ? false : file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('No PHP source for ' . $class);
        }
        $resolver = new NameResolver();
        (new NodeTraverser($resolver))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? []);

        return $this->classes[$class] = [$reflection, $parameters, clone $resolver->getNameContext()];
    }

    /**
     * @param class-string $scope The declaring class
     * @throws RuntimeException When a declared type cannot be resolved
     * @return class-string
     */
    public function resolve(string $name, string $scope, bool $native): string
    {
        if ($name === 'self' || $name === 'static') {
            return $scope;
        }
        $resolved = $native ? $name : $this->of($scope)[2]->getResolvedClassName(str_starts_with($name, '\\') ? new FullyQualified(ltrim($name, '\\')) : new Name($name))->toString();
        if (!class_exists($resolved) && !interface_exists($resolved)) {
            throw new RuntimeException('Unresolved constructor type ' . $name . ' in ' . $scope);
        }

        return $resolved;
    }
}
