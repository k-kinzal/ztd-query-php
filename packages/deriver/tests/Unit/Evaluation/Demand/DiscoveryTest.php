<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Demand;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Query\Budget;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\PointRef;
use Deriver\Reference\SourceRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Demand\Discovery
 */
#[CoversClass(Discovery::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(StateQuery::class)]
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DiscoveryTest extends TestCase
{
    public function testInstructionsRetainsAnUnusedCallButOmitsAnUnusedLiteral(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target(){999; sideEffect(); return 1;}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instructions = (new Discovery($context))->instructions($body);
        self::assertArrayNotHasKey($body->blocks[0]->instructions[0]->id, $instructions);
        self::assertArrayHasKey($body->blocks[0]->instructions[2]->id, $instructions);
        self::assertArrayHasKey($body->blocks[0]->instructions[3]->id, $instructions);
    }
    public function testRootRetainsThrowingOperatorsWithoutAResultUse(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('division', 'binary', $body->source, 'unused', name: '/');
        self::assertTrue((new Discovery($context))->root($body, $instruction));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerObservationRoots')]
    public function testRootSelectsOnlyTheRequestedPureObservation(Query $query, string $symbol, bool $expected): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph($symbol, [], [], $source);
        $instruction = new Instruction('selected', 'constant', $source, 'value');
        self::assertSame($expected, (new Discovery($context))->root($body, $instruction));
    }

    /**
     * @return iterable<string,array{Query,string,bool}>
     */
    public static function providerObservationRoots(): iterable
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $expression = new ExpressionRef($source, '\\N\\TARGET', 'value');
        $point = new PointRef($source, '\\N\\TARGET', 'selected', 'after');
        $elsewhere = new PointRef($source, '\\N\\TARGET', 'elsewhere', 'after');
        yield 'return does not force unused literals' => [new ReturnQuery('N\\target'), 'N\\target', false];
        yield 'value owner and register' => [new ValueQuery($expression), 'N\\target', true];
        yield 'value other owner' => [new ValueQuery($expression), 'N\\other', false];
        yield 'value other register' => [new ValueQuery(new ExpressionRef($source, 'N\\target', 'other')), 'N\\target', false];
        yield 'state observation instruction' => [new StateQuery($point, 'x'), 'N\\target', true];
        yield 'state other owner' => [new StateQuery($point, 'x'), 'N\\other', false];
        yield 'state other instruction' => [new StateQuery($elsewhere, 'x'), 'N\\target', false];
        yield 'tuple instruction' => [new TupleQuery($point, []), 'N\\target', true];
        yield 'tuple demanded register' => [new TupleQuery($elsewhere, ['one' => $expression]), 'N\\target', true];
        yield 'tuple unrelated register' => [new TupleQuery($elsewhere, ['one' => new ExpressionRef($source, 'N\\target', 'other')]), 'N\\target', false];
        yield 'tuple unrelated owner' => [new TupleQuery($elsewhere, ['one' => $expression]), 'N\\other', false];
        yield 'case-sensitive script' => [new ValueQuery(new ExpressionRef($source, 'script:A.php', 'value')), 'script:a.php', false];
    }

    public function testInstructionsFollowsArgumentsAndReferenceLocationsThroughRepeatedDependencies(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $instructions = [
            new Instruction('argument-definition', 'constant', $source, 'argument'),
            new Instruction('location-definition', 'constant', $source, 'address'),
            new Instruction('receiver-definition', 'constant', $source, 'receiver'),
            new Instruction('discarded', 'constant', $source, 'unused'),
            new Instruction('effect', 'invoke', $source, 'result', ['receiver', 'receiver'], arguments:[new Argument('argument', location:'address'), new Argument('argument')]),
        ];
        $body = new CallableGraph('target', [], [new BasicBlock(0, $instructions, new Terminator('return', 'result'))], $source);
        $needed = (new Discovery($context))->instructions($body);
        ksort($needed);
        self::assertSame(['argument-definition' => true, 'effect' => true, 'location-definition' => true, 'receiver-definition' => true], $needed);
        self::assertFalse($context->sealed);
        self::assertSame([], $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDiscoveryBudgets')]
    public function testInstructionsStopsOnlyAfterExceedingTheNodeBudget(int $budget, int $count, bool $sealed): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget:new Budget(nodes:$budget));
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $instructions = [
            new Instruction('first', 'invoke', $source, 'one'),
            new Instruction('second', 'invoke', $source, 'two'),
            new Instruction('third', 'invoke', $source, 'three'),
        ];
        $body = new CallableGraph('target', [], [new BasicBlock(0, $instructions, new Terminator('return', 'three'))], $source);
        $needed = (new Discovery($context))->instructions($body);
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
    public function testObservationDemandsOnlyTheRequestedPureRegister(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = $body->blocks[0]->instructions[0];
        $query = new ValueQuery(new ExpressionRef($instruction->source, $body->symbol, $instruction->result));
        self::assertTrue((new Discovery($context))->observation($query, $body, $instruction));
        self::assertFalse((new Discovery($context))->observation(new ReturnQuery('target'), $body, $instruction));
    }

}
