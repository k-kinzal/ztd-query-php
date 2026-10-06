<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\QueryValidation;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Program;
use Deriver\ControlFlow\Terminator;
use Deriver\Exception\InvalidInputException;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\PointRef;
use Deriver\Reference\SourceRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Analysis\QueryValidation
 */
#[CoversClass(QueryValidation::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
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
#[UsesClass(ReturnQuery::class)]
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
#[UsesClass(PointRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
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
#[UsesClass(\Deriver\Value\Projection::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class QueryValidationTest extends TestCase
{
    public function testOwnerRejectsAnEmptyReturnSymbol(): void
    {
        $this->expectException(InvalidInputException::class);
        (new QueryValidation(\Tests\Fake\SolverFixture::context()->program, 'test'))->owner(new ReturnQuery(''));
    }
    public function testReferenceRejectsForeignSnapshots(): void
    {
        $reference = new ExpressionRef(new SourceRef('foreign', 'fixture.php', 0, 1), 'target', 'r0');
        $this->expectException(InvalidInputException::class);
        (new QueryValidation(\Tests\Fake\SolverFixture::context()->program, 'test'))->reference($reference);
    }
    public function testReferenceAcceptsAnActualCapturedInstruction(): void
    {
        $program = \Tests\Fake\SolverFixture::context()->program;
        $body = $program->callable('target');
        self::assertNotNull($body);
        $instruction = $body->blocks[0]->instructions[0];
        $reference = new ExpressionRef($instruction->source, 'target', $instruction->result);
        $validation = new QueryValidation($program, 'test');
        $validation->reference($reference);
        self::assertSame('target', $validation->owner(new ValueQuery($reference)));
    }

    #[DataProvider('providerInvalidReferences')]
    public function testReferenceRejectsEveryMismatchedIdentityComponent(ExpressionRef|PointRef $reference): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        $this->expectException(InvalidInputException::class);
        (new QueryValidation($program, 'snapshot'))->reference($reference);
    }

    /**
     * @return array<string,array{ExpressionRef|PointRef}>
     */
    public static function providerInvalidReferences(): array
    {
        return [
            'expression path' => [new ExpressionRef(new SourceRef('snapshot', 'other.php', 10, 20), 'target', 'register')],
            'expression start' => [new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 9, 20), 'target', 'register')],
            'expression end' => [new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 10, 21), 'target', 'register')],
            'expression register' => [new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 10, 20), 'target', 'other')],
            'point phase' => [new PointRef(new SourceRef('snapshot', 'fixture.php', 10, 20), 'target', 'instruction', 'during')],
            'point path' => [new PointRef(new SourceRef('snapshot', 'other.php', 10, 20), 'target', 'instruction', 'after')],
            'point start' => [new PointRef(new SourceRef('snapshot', 'fixture.php', 9, 20), 'target', 'instruction', 'before')],
            'point end' => [new PointRef(new SourceRef('snapshot', 'fixture.php', 10, 21), 'target', 'instruction', 'invocation')],
            'point instruction' => [new PointRef(new SourceRef('snapshot', 'fixture.php', 10, 20), 'target', 'register', 'after')],
            'point snapshot' => [new PointRef(new SourceRef('foreign', 'fixture.php', 10, 20), 'target', 'instruction', 'after')],
        ];
    }

    #[DataProvider('providerValidQueries')]
    public function testOwnerResolvesEachSupportedObservationForm(Query $query): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        self::assertSame('target', (new QueryValidation($program, 'snapshot'))->owner($query));
    }

    /**
     * @return array<string,array{Query}>
     */
    public static function providerValidQueries(): array
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $expression = new ExpressionRef($source, 'target', 'register');
        return [
            'return' => [new ReturnQuery('target')], 'value' => [new ValueQuery($expression)],
            'state before' => [new StateQuery(new PointRef($source, 'target', 'instruction', 'before'), 'value')],
            'state after' => [new StateQuery(new PointRef($source, 'target', 'instruction', 'after'), 'value')],
            'state invocation' => [new StateQuery(new PointRef($source, 'target', 'instruction', 'invocation'), 'value')],
            'tuple' => [new TupleQuery(new PointRef($source, 'target', 'instruction', 'after'), ['value' => $expression])],
        ];
    }

    public function testOwnerRejectsUnknownQueryImplementations(): void
    {
        $program = self::createStub(Program::class);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unsupported query implementation.');
        (new QueryValidation($program, 'snapshot'))->owner(self::createStub(Query::class));
    }

    public function testReferenceRejectsMissingCallableBodies(): void
    {
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn(null);
        $this->expectException(InvalidInputException::class);
        (new QueryValidation($program, 'snapshot'))->reference(new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 10, 20), 'missing', 'register'));
    }

    public function testOwnerRejectsAnEmptyTupleAtAnOtherwiseValidPoint(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple queries require at least one expression.');
        (new QueryValidation($program, 'snapshot'))->owner(new TupleQuery(new PointRef($source, 'target', 'instruction', 'after'), []));
    }

    public function testOwnerCannotCombineExpressionsFromDifferentInvocations(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $block = new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'));
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturnMap([['target',new CallableGraph('target', [], [$block], $source)],['other',new CallableGraph('other', [], [$block], $source)]]);
        $query = new TupleQuery(new PointRef($source, 'target', 'instruction', 'after'), ['foreign' => new ExpressionRef($source, 'other', 'register')]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple expressions must belong to the observation callable.');
        (new QueryValidation($program, 'snapshot'))->owner($query);
    }

    public function testOwnerAllowsEquivalentNamedOwnersWithinACorrelatedTuple(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('N\\target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        $query = new TupleQuery(new PointRef($source, '\\N\\TARGET', 'instruction', 'after'), ['value' => new ExpressionRef($source, 'n\\target', 'register')]);
        self::assertSame('\\N\\TARGET', (new QueryValidation($program, 'snapshot'))->owner($query));
    }

    public function testOwnerCannotFoldDifferentScriptPathsIntoTheSameTuple(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $block = new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'));
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturnMap([['script:A.php',new CallableGraph('script:A.php', [], [$block], $source)],['script:a.php',new CallableGraph('script:a.php', [], [$block], $source)]]);
        $query = new TupleQuery(new PointRef($source, 'script:A.php', 'instruction', 'after'), ['foreign' => new ExpressionRef($source, 'script:a.php', 'register')]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple expressions must belong to the observation callable.');
        (new QueryValidation($program, 'snapshot'))->owner($query);
    }
}
