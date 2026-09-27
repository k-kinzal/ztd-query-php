<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Transfer\ExternalTransfer
 */
#[CoversClass(\Deriver\Internal\Solver\Transfer\ExternalTransfer::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ExternalTransferTest extends TestCase
{
    public function testApplyDistinguishesFreshEventsFromStableEnvironmentInputs(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $state = new \Deriver\Internal\Solver\State();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $random = new \Deriver\Internal\IR\Instruction('random', 'intrinsic', $source, 'result', name:'random_int');
        $a = $transfer->apply($random, $state, [\Deriver\Value\Term::constant(1),\Deriver\Value\Term::constant(9)])[0]->value('result');
        $b = $transfer->apply($random, $state, [\Deriver\Value\Term::constant(1),\Deriver\Value\Term::constant(9)])[0]->value('result');
        self::assertNotSame($a->literal, $b->literal);
        $env = new \Deriver\Internal\IR\Instruction('env', 'intrinsic', $source, 'result', name:'getenv');
        $c = $transfer->apply($env, $state, [\Deriver\Value\Term::constant('NAME')])[0]->value('result');
        $d = $transfer->apply($env, $state, [\Deriver\Value\Term::constant('NAME')])[0]->value('result');
        self::assertSame($c->literal, $d->literal);
    }
    public function testRangeErrorRejectsReversedBoundsAndOneMissingBound(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('ValueError', $transfer->rangeError([\Deriver\Value\Term::constant(9),\Deriver\Value\Term::constant(1)]));
        self::assertSame('ArgumentCountError', $transfer->rangeError([\Deriver\Value\Term::constant(1)]));
        self::assertNull($transfer->rangeError([]));
    }
    public function testClockTypeKeepsBothOverloadsForAnUnknownFlag(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('float', $transfer->clockType([\Deriver\Value\Term::constant(true)]));
        self::assertSame('string', $transfer->clockType([]));
        self::assertSame('string|float', $transfer->clockType([\Deriver\Value\Term::parameter('flag', 'bool')]));
    }
    public function testRandomRetainsEntropyFailureAlongsideAValidSample(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $instruction = new \Deriver\Internal\IR\Instruction('random', 'intrinsic', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', name:'random_int');
        $paths = $transfer->random($instruction, new \Deriver\Internal\Solver\State(), [\Deriver\Value\Term::constant(1),\Deriver\Value\Term::constant(2)]);
        self::assertCount(2, $paths);
        self::assertSame('external', $paths[0]->value('result')->kind);
        self::assertSame('Random\\RandomException', $paths[1]->completion->value?->literal);
    }
    public function testRandomReturnsAnEqualEndpointWithoutAnEntropyRequest(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $instruction = new \Deriver\Internal\IR\Instruction('random', 'intrinsic', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', name:'random_int');
        $paths = $transfer->random($instruction, new \Deriver\Internal\Solver\State(), [\Deriver\Value\Term::constant(2),\Deriver\Value\Term::constant(2, true)]);
        self::assertCount(1, $paths);
        self::assertSame(2, $paths[0]->value('result')->native());
        self::assertTrue($paths[0]->value('result')->isSecret());
    }
    public function testRandomPreservesThePossibleMtRandRangeErrorForSymbolicBounds(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $instruction = new \Deriver\Internal\IR\Instruction('random', 'intrinsic', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', name:'mt_rand');
        $paths = $transfer->random($instruction, new \Deriver\Internal\Solver\State(), [\Deriver\Value\Term::parameter('min', 'int'),\Deriver\Value\Term::constant(2)]);
        self::assertCount(2, $paths);
        self::assertSame('ValueError', $paths[1]->completion->value?->literal);
    }
    public function testRangeErrorAllowsReversedRandBounds(): void
    {
        self::assertNull((new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context()))->rangeError([\Deriver\Value\Term::constant(9),\Deriver\Value\Term::constant(1)], 'rand'));
    }
    public function testEnvironmentKeySeparatesLocalAndSapiLookups(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('env:NAME', $transfer->environmentKey('getenv', [\Deriver\Value\Term::constant('NAME')]));
        self::assertSame('env-local:NAME', $transfer->environmentKey('getenv', [\Deriver\Value\Term::constant('NAME'),\Deriver\Value\Term::constant(true)]));
        self::assertSame('getenv:lookup', $transfer->environmentKey('getenv', [\Deriver\Value\Term::parameter('name', 'string')]));
        self::assertSame('getenv', $transfer->environmentKey('getenv', []));
    }
    public function testEnvironmentTypeIncludesTheCompleteEnvironmentOverload(): void
    {
        $transfer = new \Deriver\Internal\Solver\Transfer\ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('array', $transfer->environmentType([]));
        self::assertSame('string|false', $transfer->environmentType([\Deriver\Value\Term::constant('NAME')]));
        self::assertSame('array|string|false', $transfer->environmentType([\Deriver\Value\Term::parameter('name', 'string|null')]));
    }
}
