<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\ExternalTransfer;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Transfer\ExternalTransfer
 */
#[CoversClass(ExternalTransfer::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
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
final class ExternalTransferTest extends TestCase
{
    public function testApplyDistinguishesFreshEventsFromStableEnvironmentInputs(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $state = new State();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $random = new Instruction('random', 'intrinsic', $source, 'result', name:'random_int');
        $a = $transfer->apply($random, $state, [Term::constant(1),Term::constant(9)])[0]->value('result');
        $b = $transfer->apply($random, $state, [Term::constant(1),Term::constant(9)])[0]->value('result');
        self::assertNotSame($a->literal, $b->literal);
        $env = new Instruction('env', 'intrinsic', $source, 'result', name:'getenv');
        $c = $transfer->apply($env, $state, [Term::constant('NAME')])[0]->value('result');
        $d = $transfer->apply($env, $state, [Term::constant('NAME')])[0]->value('result');
        self::assertSame($c->literal, $d->literal);
    }
    public function testRangeErrorRejectsReversedBoundsAndOneMissingBound(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('ValueError', $transfer->rangeError([Term::constant(9),Term::constant(1)]));
        self::assertSame('ArgumentCountError', $transfer->rangeError([Term::constant(1)]));
        self::assertNull($transfer->rangeError([]));
    }
    public function testClockTypeKeepsBothOverloadsForAnUnknownFlag(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('float', $transfer->clockType([Term::constant(true)]));
        self::assertSame('string', $transfer->clockType([]));
        self::assertSame('string|float', $transfer->clockType([Term::parameter('flag', 'bool')]));
    }
    public function testRandomRetainsEntropyFailureAlongsideAValidSample(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $instruction = new Instruction('random', 'intrinsic', new SourceRef('test', 'a.php', 0, 1), 'result', name:'random_int');
        $paths = $transfer->random($instruction, new State(), [Term::constant(1),Term::constant(2)]);
        self::assertCount(2, $paths);
        self::assertSame('external', $paths[0]->value('result')->kind);
        self::assertSame('Random\\RandomException', $paths[1]->completion->value?->literal);
    }
    public function testRandomReturnsAnEqualEndpointWithoutAnEntropyRequest(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $instruction = new Instruction('random', 'intrinsic', new SourceRef('test', 'a.php', 0, 1), 'result', name:'random_int');
        $paths = $transfer->random($instruction, new State(), [Term::constant(2),Term::constant(2, true)]);
        self::assertCount(1, $paths);
        self::assertSame(2, $paths[0]->value('result')->native());
        self::assertTrue($paths[0]->value('result')->isSecret());
    }
    public function testRandomPreservesThePossibleMtRandRangeErrorForSymbolicBounds(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        $instruction = new Instruction('random', 'intrinsic', new SourceRef('test', 'a.php', 0, 1), 'result', name:'mt_rand');
        $paths = $transfer->random($instruction, new State(), [Term::parameter('min', 'int'),Term::constant(2)]);
        self::assertCount(2, $paths);
        self::assertSame('ValueError', $paths[1]->completion->value?->literal);
    }
    public function testRangeErrorAllowsReversedRandBounds(): void
    {
        self::assertNull((new ExternalTransfer(\Tests\Fake\SolverFixture::context()))->rangeError([Term::constant(9),Term::constant(1)], 'rand'));
    }
    public function testEnvironmentKeySeparatesLocalAndSapiLookups(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('env:NAME', $transfer->environmentKey('getenv', [Term::constant('NAME')]));
        self::assertSame('env-local:NAME', $transfer->environmentKey('getenv', [Term::constant('NAME'),Term::constant(true)]));
        self::assertSame('getenv:lookup', $transfer->environmentKey('getenv', [Term::parameter('name', 'string')]));
        self::assertSame('getenv', $transfer->environmentKey('getenv', []));
    }
    public function testEnvironmentTypeIncludesTheCompleteEnvironmentOverload(): void
    {
        $transfer = new ExternalTransfer(\Tests\Fake\SolverFixture::context());
        self::assertSame('array', $transfer->environmentType([]));
        self::assertSame('string|false', $transfer->environmentType([Term::constant('NAME')]));
        self::assertSame('array|string|false', $transfer->environmentType([Term::parameter('name', 'string|null')]));
    }
}
