<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\CallableCheck
 */
#[CoversClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class CallableCheckTest extends TestCase
{
    public function testEvaluateChecksCapturedFunctionsWithoutUsingHostDeclarations(): void
    {
        $check = new \Deriver\Internal\Solver\Call\CallableCheck(\Tests\Fake\SolverFixture::context('<?php function target(){}'));
        self::assertSame(true, $check->evaluate(\Deriver\Value\Term::constant('target'))->native());
        self::assertSame(false, $check->evaluate(\Deriver\Value\Term::constant(7))->native());
        self::assertSame('intrinsic', $check->evaluate(\Deriver\Value\Term::constant('unavailable'))->kind);
    }
    public function testPairRequiresExactlyTheTwoNumericCallableKeys(): void
    {
        $check = new \Deriver\Internal\Solver\Call\CallableCheck(\Tests\Fake\SolverFixture::context());
        self::assertTrue($check->pair(\Deriver\Value\Term::fromNative([1 => 'run',0 => 'Box'])));
        self::assertFalse($check->pair(\Deriver\Value\Term::fromNative(['Box','run',2])));
        self::assertFalse($check->pair(\Deriver\Value\Term::fromNative([1 => 'Box',2 => 'run'])));
        self::assertFalse($check->pair(\Deriver\Value\Term::array([\Deriver\Value\Term::constant('Box'),\Deriver\Value\Term::constant('run')], true)));
    }
    public function testMethodResolvesPublicInstancePairsWithoutGrantingPrivateAccess(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class B{public function run(){} private static function hidden(){}} function target(){return 1;}');
        $check = new \Deriver\Internal\Solver\Call\CallableCheck($context);
        self::assertTrue($check->evaluate(\Deriver\Value\Term::array([new \Deriver\Value\Term('object', 'one', attributes: ['class' => 'B']), \Deriver\Value\Term::constant('run')]))->native());
        self::assertFalse($check->evaluate(\Deriver\Value\Term::constant('B::run'))->native());
        self::assertSame('intrinsic', $check->evaluate(\Deriver\Value\Term::constant('B::hidden'))->kind);
    }
}
