<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Resolver;

use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use Deriver\Exception\ResolutionException;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resolver::class)]
#[UsesClass(Parser::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(File::class)]
#[UsesClass(Source::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(MagicContext::class)]
#[Small]
final class ResolverTest extends TestCase
{
    public function testResolveBindsNamesAndMagicContextWithoutMutatingParsedSyntax(): void
    {
        $source = new Source('a.php', '<?php namespace App; use Vendor\Thing as Alias; #[Alias] class Box { #[Alias] function answer(#[Alias] ?Alias $arg): Alias|null { return [new Alias(), __METHOD__]; } } const FLAG = 1;');
        $parsed = (new Parser(new ParserConfig('8.3')))->parse($source);
        $before = serialize($parsed);
        $resolved = (new Resolver())->resolve($parsed);
        $finder = new NodeFinder();
        $creation = $finder->findFirstInstanceOf([$resolved], Node\Expr\New_::class);
        $magic = $finder->findFirstInstanceOf([$resolved], Node\Scalar\MagicConst\Method::class);
        self::assertInstanceOf(Node\Expr\New_::class, $creation);
        self::assertInstanceOf(Node\Name\FullyQualified::class, $creation->class);
        self::assertSame('Vendor\Thing', $creation->class->toString());
        self::assertInstanceOf(Node\Scalar\MagicConst\Method::class, $magic);
        self::assertSame(['namespace' => 'App', 'class' => 'App\Box', 'function' => 'answer', 'method' => 'App\Box::answer', 'property' => ''], $magic->getAttribute('deriver-lexical'));
        self::assertSame($source, $resolved->source);
        self::assertSame($before, serialize($parsed));
    }

    #[DataProvider('providerInvalidSources')]
    public function testResolveReportsSemanticErrorsWithTheirSourceLocation(string $contents): void
    {
        $parsed = (new Parser(new ParserConfig('8.3')))->parse(new Source('invalid.php', $contents));
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('invalid.php:2:');
        (new Resolver())->resolve($parsed);
    }

    public function testResolveResetsScopeAndDiagnosticsAfterAFailure(): void
    {
        $parser = new Parser(new ParserConfig('8.3'));
        $resolver = new Resolver();
        $invalid = $parser->parse(new Source('bad.php', '<?php namespace Broken; use A as Alias; use B as Alias;'));
        try {
            $resolver->resolve($invalid);
            self::fail('Duplicate aliases must fail during resolution.');
        } catch (ResolutionException $error) {
            self::assertSame('bad.php', $error->path);
        }
        $valid = $parser->parse(new Source('good.php', '<?php namespace Valid; function f() { return __FUNCTION__; }'));
        self::assertSame(serialize((new Resolver())->resolve($valid)), serialize($resolver->resolve($valid)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidSources(): iterable
    {
        yield 'duplicate alias' => ["<?php\nuse A as Alias; use B as Alias;"];
        yield 'class scope' => ["<?php\nfunction f() { return self::class; }"];
        yield 'literal unpack' => ["<?php\nreturn [...7];"];
        yield 'assignment pattern' => ["<?php\n[&\$x] = [1];"];
    }
}
