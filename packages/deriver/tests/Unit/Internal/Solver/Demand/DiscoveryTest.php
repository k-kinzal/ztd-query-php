<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Demand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Demand\Discovery
 */
#[CoversClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Query\StateQuery::class)]
#[UsesClass(\Deriver\Api\Query\TupleQuery::class)]
#[UsesClass(\Deriver\Api\Query\ValueQuery::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DiscoveryTest extends TestCase
{
    public function testInstructionsRetainsAnUnusedCallButOmitsAnUnusedLiteral(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){999; sideEffect(); return 1;}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instructions = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        self::assertArrayNotHasKey($body->blocks[0]->instructions[0]->id, $instructions);
        self::assertArrayHasKey($body->blocks[0]->instructions[2]->id, $instructions);
        self::assertArrayHasKey($body->blocks[0]->instructions[3]->id, $instructions);
    }
    public function testRootRetainsThrowingOperatorsWithoutAResultUse(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('division', 'binary', $body->source, 'unused', name: '/');
        self::assertTrue((new \Deriver\Internal\Solver\Demand\Discovery($context))->root($body, $instruction));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerObservationRoots')]
    public function testRootSelectsOnlyTheRequestedPureObservation(\Deriver\Api\Query\Query $query, string $symbol, bool $expected): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $body = new \Deriver\Internal\IR\CallableIR($symbol, [], [], $source);
        $instruction = new \Deriver\Internal\IR\Instruction('selected', 'constant', $source, 'value');
        self::assertSame($expected, (new \Deriver\Internal\Solver\Demand\Discovery($context))->root($body, $instruction));
    }

    /**
     * @return iterable<string,array{\Deriver\Api\Query\Query,string,bool}>
     */
    public static function providerObservationRoots(): iterable
    {
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $expression = new \Deriver\Api\Reference\ExpressionRef($source, '\\N\\TARGET', 'value');
        $point = new \Deriver\Api\Reference\PointRef($source, '\\N\\TARGET', 'selected', 'after');
        $elsewhere = new \Deriver\Api\Reference\PointRef($source, '\\N\\TARGET', 'elsewhere', 'after');
        yield 'return does not force unused literals' => [new \Deriver\Api\Query\ReturnQuery('N\\target'), 'N\\target', false];
        yield 'value owner and register' => [new \Deriver\Api\Query\ValueQuery($expression), 'N\\target', true];
        yield 'value other owner' => [new \Deriver\Api\Query\ValueQuery($expression), 'N\\other', false];
        yield 'value other register' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'N\\target', 'other')), 'N\\target', false];
        yield 'state observation instruction' => [new \Deriver\Api\Query\StateQuery($point, 'x'), 'N\\target', true];
        yield 'state other owner' => [new \Deriver\Api\Query\StateQuery($point, 'x'), 'N\\other', false];
        yield 'state other instruction' => [new \Deriver\Api\Query\StateQuery($elsewhere, 'x'), 'N\\target', false];
        yield 'tuple instruction' => [new \Deriver\Api\Query\TupleQuery($point, []), 'N\\target', true];
        yield 'tuple demanded register' => [new \Deriver\Api\Query\TupleQuery($elsewhere, ['one' => $expression]), 'N\\target', true];
        yield 'tuple unrelated register' => [new \Deriver\Api\Query\TupleQuery($elsewhere, ['one' => new \Deriver\Api\Reference\ExpressionRef($source, 'N\\target', 'other')]), 'N\\target', false];
        yield 'tuple unrelated owner' => [new \Deriver\Api\Query\TupleQuery($elsewhere, ['one' => $expression]), 'N\\other', false];
        yield 'case-sensitive script' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'script:A.php', 'value')), 'script:a.php', false];
    }

    public function testInstructionsFollowsArgumentsAndReferenceLocationsThroughRepeatedDependencies(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $instructions = [
            new \Deriver\Internal\IR\Instruction('argument-definition', 'constant', $source, 'argument'),
            new \Deriver\Internal\IR\Instruction('location-definition', 'constant', $source, 'address'),
            new \Deriver\Internal\IR\Instruction('receiver-definition', 'constant', $source, 'receiver'),
            new \Deriver\Internal\IR\Instruction('discarded', 'constant', $source, 'unused'),
            new \Deriver\Internal\IR\Instruction('effect', 'invoke', $source, 'result', ['receiver', 'receiver'], arguments:[new \Deriver\Internal\IR\Argument('argument', location:'address'), new \Deriver\Internal\IR\Argument('argument')]),
        ];
        $body = new \Deriver\Internal\IR\CallableIR('target', [], [new \Deriver\Internal\IR\BasicBlock(0, $instructions, new \Deriver\Internal\IR\Terminator('return', 'result'))], $source);
        $needed = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        ksort($needed);
        self::assertSame(['argument-definition' => true, 'effect' => true, 'location-definition' => true, 'receiver-definition' => true], $needed);
        self::assertFalse($context->sealed);
        self::assertSame([], $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDiscoveryBudgets')]
    public function testInstructionsStopsOnlyAfterExceedingTheNodeBudget(int $budget, int $count, bool $sealed): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new \Deriver\Api\Query\Budget(nodes:$budget));
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $instructions = [
            new \Deriver\Internal\IR\Instruction('first', 'invoke', $source, 'one'),
            new \Deriver\Internal\IR\Instruction('second', 'invoke', $source, 'two'),
            new \Deriver\Internal\IR\Instruction('third', 'invoke', $source, 'three'),
        ];
        $body = new \Deriver\Internal\IR\CallableIR('target', [], [new \Deriver\Internal\IR\BasicBlock(0, $instructions, new \Deriver\Internal\IR\Terminator('return', 'three'))], $source);
        $needed = (new \Deriver\Internal\Solver\Demand\Discovery($context))->instructions($body);
        self::assertCount($count, $needed);
        self::assertSame($sealed, $context->sealed);
        self::assertSame($sealed, $context->frontiers !== []);
    }

    /**
     * @return iterable<string,array{int,int,bool}>
     */
    public static function providerDiscoveryBudgets(): iterable
    {
        yield 'exact fit' => [3,3,false];
        yield 'last node exceeds limit' => [2,3,true];
        yield 'stop before all roots' => [1,2,true];
    }
}
