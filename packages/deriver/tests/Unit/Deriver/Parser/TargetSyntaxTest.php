<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Parser;

use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Parser\TargetSyntax
 */
#[UsesClass(ParserConfig::class)]
#[UsesClass(\Deriver\Exception\ParserException::class)]
#[CoversClass(TargetSyntax::class)]
#[UsesClass(Source::class)]
#[UsesClass(Parser::class)]
#[UsesClass(\Deriver\Deriver\Parser\Node\File::class)]
#[Small]
final class TargetSyntaxTest extends TestCase
{
    public function testEnterNodeRejectsPartialApplicationBeforeCallLowering(): void
    {
        $errors = new \PhpParser\ErrorHandler\Collecting();
        $validator = new TargetSyntax(new ParserConfig('8.3'), $errors);
        $validator->enterNode(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('f'), [new \PhpParser\Node\ArgPlaceholder()]));
        self::assertCount(1, $errors->getErrors());
        self::assertStringContainsString('8.6', $errors->getErrors()[0]->getRawMessage());
    }
    public function testEnterNodeAcceptsOrdinaryFirstClassCallablesButRejectsObjectCreationPlaceholders(): void
    {
        $errors = new \PhpParser\ErrorHandler\Collecting();
        $validator = new TargetSyntax(new ParserConfig('8.3'), $errors);
        $validator->enterNode(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('f'), [new \PhpParser\Node\VariadicPlaceholder()]));
        self::assertSame([], $errors->getErrors());
        $validator->enterNode(new \PhpParser\Node\Expr\New_(new \PhpParser\Node\Name('Box'), [new \PhpParser\Node\VariadicPlaceholder()]));
        self::assertCount(1, $errors->getErrors());
    }
    public function testMinimumIdentifiesNewerOperatorsAndAsymmetricProperties(): void
    {
        $validator = new TargetSyntax(new ParserConfig('8.3'), new \PhpParser\ErrorHandler\Collecting());
        self::assertSame('8.5', $validator->minimum(new \PhpParser\Node\Expr\Cast\Void_(new \PhpParser\Node\Scalar\Int_(1))));
        self::assertSame('8.4', $validator->minimum(new \PhpParser\Node\Stmt\Property(\PhpParser\Modifiers::PUBLIC | \PhpParser\Modifiers::PRIVATE_SET, [new \PhpParser\Node\PropertyItem('x')])));
        self::assertSame('', $validator->minimum(new \PhpParser\Node\Scalar\Int_(1)));
    }
    public function testLeaveNodeRestoresTheConstantContextForOrdinaryCalls(): void
    {
        $errors = new \PhpParser\ErrorHandler\Collecting();
        $visitor = new TargetSyntax(new ParserConfig('8.3'), $errors);
        $parameter = new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('x'));
        $visitor->enterNode($parameter);
        self::assertSame(1, $visitor->initializers);
        $visitor->leaveNode($parameter);
        self::assertSame(0, $visitor->initializers);
    }
    public function testInitializerRecognizesConstantDeclarationsButNotExecutableStaticVariables(): void
    {
        $visitor = new TargetSyntax(new ParserConfig('8.3'), new \PhpParser\ErrorHandler\Collecting());
        self::assertTrue($visitor->initializer(new \PhpParser\Node\Const_('F', new \PhpParser\Node\Scalar\Int_(1))));
        self::assertFalse($visitor->initializer(new \PhpParser\Node\StaticVar(new \PhpParser\Node\Expr\Variable('f'))));
    }
    #[DataProvider('providerUnsupportedSyntax')]
    public function testUnparenthesizedNewAndOtherUnsupportedSyntaxRaiseParserExceptions(string $source): void
    {
        $this->expectException(\Deriver\Exception\ParserException::class);
        (new Parser(new ParserConfig('8.3')))->parse(new Source('x.php', $source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnsupportedSyntax(): iterable
    {
        yield 'direct new' => ['<?php function target(){return new X()->f();}'];
        yield 'new in argument' => ['<?php function target(){return f(new X()->f());}'];
        yield 'callable constant' => ['<?php const F=strlen(...);'];
        yield 'callable default' => ['<?php function target($f=strlen(...)){}'];
    }

    #[DataProvider('providerSupportedSyntax')]
    public function testEnterNodeAllowsSupportedSyntax(string $source): void
    {
        $node = (new Parser(new ParserConfig('8.3')))->parse(new Source('x.php', $source));
        self::assertCount(1, $node->stmts);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSupportedSyntax(): iterable
    {
        yield 'parenthesized new' => ['<?php function target(){return (new X())->f();}'];
        yield 'runtime callable' => ['<?php function target(){return strlen(...);}'];
        yield 'runtime unpack' => ['<?php function target(){$x=7;try{return [...$x];}catch(Error $e){return 1;}}'];
    }
}
