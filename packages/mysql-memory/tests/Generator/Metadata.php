<?php

declare(strict_types=1);

namespace Tests\Generator;

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
use RuntimeException;

/**
 * Reads retained field and constructor types; no SQL or execution rule enters generation.
 */
final class Metadata
{
    /**
     * @var array<class-string, array{\PhpParser\Node\Stmt\Class_, array<string, TypeNode>, NameContext}>
     */
    private array $classes = [];

    /**
     * @param class-string $class The constructor to inspect
     * @throws RuntimeException When the source cannot be read
     * @return array{\PhpParser\Node\Stmt\Class_, array<string, TypeNode>, NameContext}
     */
    public function of(string $class): array
    {
        if (isset($this->classes[$class])) {
            return $this->classes[$class];
        }
        $mysql = 'SqlSemantics\\Platform\\MySql\\';
        $prefix = str_starts_with($class, $mysql) ? $mysql : 'SqlSemantics\\';
        $package = $prefix === $mysql ? 'sql-semantics-mysql' : 'sql-semantics';
        $file = dirname(__DIR__, 2) . '/vendor/k-kinzal/' . $package . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $source = file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('No PHP source for ' . $class);
        }
        $resolver = new NameResolver();
        $nodes = (new NodeTraverser($resolver))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? []);
        $declaration = (new \PhpParser\NodeFinder())->findFirstInstanceOf($nodes, \PhpParser\Node\Stmt\Class_::class);
        if ($declaration === null) {
            throw new RuntimeException('No class declaration for ' . $class);
        }
        $config = new ParserConfig([]);
        $lexer = new Lexer($config);
        $constant = new ConstExprParser($config);
        $parser = new PhpDocParser($config, new TypeParser($config, $constant), $constant);
        $parameters = [];
        $comment = $declaration->getMethod('__construct')?->getDocComment()?->getText();
        foreach ($parser->parse(new TokenIterator($lexer->tokenize($comment === null ? '/** */' : $comment)))->getParamTagValues() as $tag) {
            $parameters[ltrim($tag->parameterName, '$')] = $tag->type;
        }
        foreach ($declaration->getProperties() as $property) {
            $comment = $property->getDocComment()?->getText();
            if (!$property->isPublic() || !$property->isReadonly() || $comment === null) {
                continue;
            }
            foreach ($parser->parse(new TokenIterator($lexer->tokenize($comment)))->getVarTagValues() as $tag) {
                $parameters[$property->props[0]->name->toString()] = $tag->type;
            }
        }
        return $this->classes[$class] = [$declaration, $parameters, clone $resolver->getNameContext()];
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
    /**
     * Reads the declared parameter name without evaluating the source.
     *
     * @throws RuntimeException When source contains a recovered parser error
     */
    public function parameter(\PhpParser\Node\Param $parameter): string
    {
        if (!$parameter->var instanceof \PhpParser\Node\Expr\Variable || !is_string($parameter->var->name)) {
            throw new RuntimeException('A constructor parameter must have a declared name.');
        }
        return $parameter->var->name;
    }

    /**
     * @return list<\PhpParser\Node\Param> Parameters in declaration order
     */
    public function parameters(\PhpParser\Node\Stmt\Class_ $declaration): array
    {
        return array_values($declaration->getMethod('__construct')->params ?? []);
    }

}
