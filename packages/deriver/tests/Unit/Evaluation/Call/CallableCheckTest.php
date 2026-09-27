<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\CallableCheck
 */
#[CoversClass(CallableCheck::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
#[UsesClass(Library::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
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
