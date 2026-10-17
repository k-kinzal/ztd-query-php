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
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
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
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Declaration\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
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
