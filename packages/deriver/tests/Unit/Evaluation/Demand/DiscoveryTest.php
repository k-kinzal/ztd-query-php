<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Demand;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\Query;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\PointRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Frontier;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
 * @covers \Deriver\Evaluation\Demand\Discovery
 */
#[CoversClass(Discovery::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
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
#[UsesClass(StateQuery::class)]
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
}
