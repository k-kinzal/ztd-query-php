<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Validation;

use Deriver\Project\SourceFile;
use Deriver\Project\TargetProfile;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Validation\TargetSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Validation\TargetSyntax
 */
#[CoversClass(TargetSyntax::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[Small]
final class TargetSyntaxTest extends TestCase
{
    public function testEnterNodeRejectsPartialApplicationBeforeCallLowering(): void
    {
        $errors = new \PhpParser\ErrorHandler\Collecting();
        $validator = new TargetSyntax(new TargetProfile(), $errors);
        $validator->enterNode(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('f'), [new \PhpParser\Node\ArgPlaceholder()]));
        self::assertCount(1, $errors->getErrors());
        self::assertStringContainsString('8.6', $errors->getErrors()[0]->getRawMessage());
    }
    public function testEnterNodeAcceptsOrdinaryFirstClassCallablesButRejectsObjectCreationPlaceholders(): void
    {
        $errors = new \PhpParser\ErrorHandler\Collecting();
        $validator = new TargetSyntax(new TargetProfile(), $errors);
        $validator->enterNode(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('f'), [new \PhpParser\Node\VariadicPlaceholder()]));
        self::assertSame([], $errors->getErrors());
        $validator->enterNode(new \PhpParser\Node\Expr\New_(new \PhpParser\Node\Name('Box'), [new \PhpParser\Node\VariadicPlaceholder()]));
        self::assertCount(1, $errors->getErrors());
    }
    public function testMinimumIdentifiesNewerOperatorsAndAsymmetricProperties(): void
    {
        $validator = new TargetSyntax(new TargetProfile(), new \PhpParser\ErrorHandler\Collecting());
        self::assertSame('8.5', $validator->minimum(new \PhpParser\Node\Expr\Cast\Void_(new \PhpParser\Node\Scalar\Int_(1))));
        self::assertSame('8.4', $validator->minimum(new \PhpParser\Node\Stmt\Property(\PhpParser\Modifiers::PUBLIC | \PhpParser\Modifiers::PRIVATE_SET, [new \PhpParser\Node\PropertyItem('x')])));
        self::assertSame('', $validator->minimum(new \PhpParser\Node\Scalar\Int_(1)));
    }
    public function testLeaveNodeRestoresTheConstantContextForOrdinaryCalls(): void
    {
        $errors = new \PhpParser\ErrorHandler\Collecting();
        $visitor = new TargetSyntax(new TargetProfile(), $errors);
        $parameter = new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('x'));
        $visitor->enterNode($parameter);
        self::assertSame(1, $visitor->initializers);
        $visitor->leaveNode($parameter);
        self::assertSame(0, $visitor->initializers);
    }
    public function testInitializerRecognizesConstantDeclarationsButNotExecutableStaticVariables(): void
    {
        $visitor = new TargetSyntax(new TargetProfile(), new \PhpParser\ErrorHandler\Collecting());
        self::assertTrue($visitor->initializer(new \PhpParser\Node\Const_('F', new \PhpParser\Node\Scalar\Int_(1))));
        self::assertFalse($visitor->initializer(new \PhpParser\Node\StaticVar(new \PhpParser\Node\Expr\Variable('f'))));
    }
    public function testUnparenthesizedNewChecksTheClosingGroupAfterConstructorArguments(): void
    {
        $cache = new SyntaxCache();
        $profile = new TargetProfile();
        self::assertNotEmpty($cache->read(new SourceFile('x.php', '<?php function target(){return new X()->f();}'), $profile)->errors);
        self::assertNotEmpty($cache->read(new SourceFile('x.php', '<?php function target(){return f(new X()->f());}'), $profile)->errors);
        self::assertSame([], $cache->read(new SourceFile('x.php', '<?php function target(){return (new X())->f();}'), $profile)->errors);
    }
    public function testEnterNodeRejectsFirstClassCallableConstantsAndDefaultsInTheTargetProfile(): void
    {
        $cache = new SyntaxCache();
        $profile = new TargetProfile();
        self::assertNotEmpty($cache->read(new SourceFile('x.php', '<?php const F=strlen(...);'), $profile)->errors);
        self::assertNotEmpty($cache->read(new SourceFile('x.php', '<?php function target($f=strlen(...)){}'), $profile)->errors);
        self::assertSame([], $cache->read(new SourceFile('x.php', '<?php function target(){return strlen(...);}'), $profile)->errors);
    }

    public function testEnterNodeRejectsScalarLiteralUnpackBeforeRuntimeAnalysis(): void
    {
        $cache = new SyntaxCache();
        $profile = new TargetProfile();
        self::assertNotEmpty($cache->read(new SourceFile('x.php', '<?php function target(){try{return [...7];}catch(Error $e){return 1;}}'), $profile)->errors);
        self::assertSame([], $cache->read(new SourceFile('x.php', '<?php function target(){$x=7;try{return [...$x];}catch(Error $e){return 1;}}'), $profile)->errors);
    }
}
