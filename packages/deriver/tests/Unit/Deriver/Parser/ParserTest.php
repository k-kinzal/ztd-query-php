<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Parser;

use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Sources\Source;
use Deriver\Exception\ParserException;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(Source::class)]
#[UsesClass(File::class)]
#[UsesClass(ParserException::class)]
#[UsesClass(TargetSyntax::class)]
#[Small]
final class ParserTest extends TestCase
{
    public function testParsePreservesTheSourceWithoutReadingOrExecutingIt(): void
    {
        $source = new Source('missing.php', '<?php throw new RuntimeException("must not execute");');
        $node = (new Parser(new ParserConfig('8.3')))->parse($source);
        self::assertSame($source, $node->source);
        self::assertCount(1, $node->stmts);
    }

    public function testParseThrowsWithTheSourcePathAndLineOnMalformedSyntax(): void
    {
        $source = new Source('broken.php', "<?php\nfunction {");
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('broken.php:2:');
        (new Parser(new ParserConfig('8.3')))->parse($source);
    }

    public function testParseRejectsSyntaxNewerThanTheConfiguredVersion(): void
    {
        $source = new Source('new.php', '<?php function target(){return new X()->f();}');
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('PHP 8.4');
        (new Parser(new ParserConfig('8.3')))->parse($source);
    }

    public function testParseUsesTheSuppliedPhpVersion(): void
    {
        $source = new Source('new.php', '<?php function target(){return new X()->f();}');
        $node = (new Parser(new ParserConfig('8.4')))->parse($source);
        self::assertInstanceOf(Stmt\Function_::class, $node->stmts[0]);
    }

    public function testParseAcceptsSyntaxDeeperThanTheFormerLimit(): void
    {
        $source = new Source('deep.php', '<?php return ' . str_repeat('1+', 150) . '1;');
        $node = (new Parser(new ParserConfig('8.3')))->parse($source);
        self::assertInstanceOf(Stmt\Return_::class, $node->stmts[0]);
        self::assertCount(150, (new \PhpParser\NodeFinder())->findInstanceOf([$node], \PhpParser\Node\Expr\BinaryOp\Plus::class));
    }

    public function testParseAcceptsSourceLargerThanTheFormerFileLimit(): void
    {
        $source = new Source('large.php', '<?php /*' . str_repeat('x', 4194304) . '*/ return 1;');
        $node = (new Parser(new ParserConfig('8.3')))->parse($source);
        self::assertInstanceOf(Stmt\Return_::class, $node->stmts[0]);
        self::assertSame($source, $node->source);
    }

    public function testParseIsDeterministicForTheSameSourceAndConfiguration(): void
    {
        $parser = new Parser(new ParserConfig('8.3'));
        $source = new Source('a.php', '<?php namespace A; function f($x) { return $x + 1; }');
        $first = $parser->parse($source);
        $second = $parser->parse($source);
        self::assertSame(serialize($first), serialize($second));
        self::assertSame($source, $first->source);
    }

    public function testParseKeepsConsecutiveSourcesIndependent(): void
    {
        $parser = new Parser(new ParserConfig('8.3'));
        $first = $parser->parse(new Source('first.php', '<?php namespace First; use Vendor\\One as Alias; function f(): Alias { return new Alias(); }'));
        $before = serialize($first);
        $source = new Source('second.php', '<?php namespace Second; use Vendor\\Two as Alias; function f(): Alias { return new Alias(); }');
        $second = $parser->parse($source);
        $fresh = (new Parser(new ParserConfig('8.3')))->parse($source);

        self::assertSame($before, serialize($first));
        self::assertSame(serialize($fresh), serialize($second));
        self::assertSame($source, $second->source);
    }

    public function testParsePreservesUnresolvedNamesAndMagicConstants(): void
    {
        $source = new Source('a.php', '<?php namespace App; use Vendor\Thing as Alias; function answer() { return [new Alias(), __FUNCTION__]; }');
        $tree = (new Parser(new ParserConfig('8.3')))->parse($source);
        $finder = new \PhpParser\NodeFinder();
        $creation = $finder->findFirstInstanceOf([$tree], \PhpParser\Node\Expr\New_::class);
        $magic = $finder->findFirstInstanceOf([$tree], \PhpParser\Node\Scalar\MagicConst\Function_::class);
        self::assertInstanceOf(\PhpParser\Node\Expr\New_::class, $creation);
        self::assertInstanceOf(\PhpParser\Node\Name::class, $creation->class);
        self::assertFalse($creation->class->isFullyQualified());
        self::assertSame('Alias', $creation->class->toString());
        self::assertInstanceOf(\PhpParser\Node\Scalar\MagicConst\Function_::class, $magic);
        self::assertFalse($magic->hasAttribute('deriver-lexical'));
    }

    #[DataProvider('providerUnresolvedSources')]
    public function testParseLeavesSemanticValidationToResolution(string $contents): void
    {
        $tree = (new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', $contents));
        self::assertNotEmpty($tree->stmts);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnresolvedSources(): iterable
    {
        yield 'duplicate alias' => ['<?php use Vendor\One as Alias; use Vendor\Two as Alias;'];
        yield 'class scope' => ['<?php function f() { return self::class; }'];
        yield 'literal unpack' => ['<?php return [...7];'];
        yield 'reference pattern' => ['<?php [&$x] = [1];'];
    }

    #[DataProvider('providerInvalidSources')]
    public function testParseCanBeReusedAfterAnError(string $invalid): void
    {
        $parser = new Parser(new ParserConfig('8.3'));
        try {
            $parser->parse(new Source('broken.php', $invalid));
            self::fail('Invalid source must throw.');
        } catch (ParserException $error) {
            self::assertSame('broken.php', $error->path);
        }
        $source = new Source('valid.php', '<?php namespace Valid; function f() { return 42; }');
        $actual = $parser->parse($source);
        $fresh = (new Parser(new ParserConfig('8.3')))->parse($source);
        self::assertSame(serialize($fresh), serialize($actual));
        self::assertSame($source, $actual->source);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidSources(): iterable
    {
        yield 'syntax' => ['<?php namespace Broken; function {'];
        yield 'language version' => ['<?php namespace Broken; function f() { return new X()->f(); }'];
    }
}
