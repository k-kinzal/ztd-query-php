<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\CallableCheck
 */
#[CoversClass(CallableCheck::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(Term::class)]
#[Small]
final class CallableCheckTest extends TestCase
{
    public function testEvaluateChecksCapturedFunctionsWithoutUsingHostDeclarations(): void
    {
        $check = new CallableCheck(\Tests\Fake\SolverFixture::context('<?php function target(){}'));
        self::assertSame(true, $check->evaluate(Term::constant('target'))->native());
        self::assertSame(false, $check->evaluate(Term::constant(7))->native());
        self::assertSame('intrinsic', $check->evaluate(Term::constant('unavailable'))->kind);
    }
    public function testPairRequiresExactlyTheTwoNumericCallableKeys(): void
    {
        $check = new CallableCheck(\Tests\Fake\SolverFixture::context());
        self::assertTrue($check->pair(Term::fromNative([1 => 'run',0 => 'Box'])));
        self::assertFalse($check->pair(Term::fromNative(['Box','run',2])));
        self::assertFalse($check->pair(Term::fromNative([1 => 'Box',2 => 'run'])));
        self::assertFalse($check->pair(Term::array([Term::constant('Box'),Term::constant('run')], true)));
    }
    public function testMethodResolvesPublicInstancePairsWithoutGrantingPrivateAccess(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class B{public function run(){} private static function hidden(){}} function target(){return 1;}');
        $check = new CallableCheck($context);
        self::assertTrue($check->evaluate(Term::array([new Term('object', 'one', attributes: ['class' => 'B']), Term::constant('run')]))->native());
        self::assertFalse($check->evaluate(Term::constant('B::run'))->native());
        self::assertSame('intrinsic', $check->evaluate(Term::constant('B::hidden'))->kind);
    }
}
