<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Api;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Query\Query;
use Deriver\Api\Query\ReturnQuery;
use Deriver\Api\Query\StateQuery;
use Deriver\Api\Query\TupleQuery;
use Deriver\Api\Query\ValueQuery;
use Deriver\Api\Reference\ExpressionRef;
use Deriver\Api\Reference\PointRef;
use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\Api\QueryValidation;
use Deriver\Internal\IR\BasicBlock;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\IR\Program;
use Deriver\Internal\IR\Terminator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Api\QueryValidation
 */
#[CoversClass(QueryValidation::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
#[UsesClass(PointRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
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
        $body = new CallableIR('target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
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
        $body = new CallableIR('target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
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
        $body = new CallableIR('target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
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
        $program->method('callable')->willReturnMap([['target',new CallableIR('target', [], [$block], $source)],['other',new CallableIR('other', [], [$block], $source)]]);
        $query = new TupleQuery(new PointRef($source, 'target', 'instruction', 'after'), ['foreign' => new ExpressionRef($source, 'other', 'register')]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple expressions must belong to the observation callable.');
        (new QueryValidation($program, 'snapshot'))->owner($query);
    }

    public function testOwnerAllowsEquivalentNamedOwnersWithinACorrelatedTuple(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableIR('N\\target', [], [new BasicBlock(0, [new Instruction('instruction', 'constant', $source, 'register')], new Terminator('return'))], $source);
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
        $program->method('callable')->willReturnMap([['script:A.php',new CallableIR('script:A.php', [], [$block], $source)],['script:a.php',new CallableIR('script:a.php', [], [$block], $source)]]);
        $query = new TupleQuery(new PointRef($source, 'script:A.php', 'instruction', 'after'), ['foreign' => new ExpressionRef($source, 'script:a.php', 'register')]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple expressions must belong to the observation callable.');
        (new QueryValidation($program, 'snapshot'))->owner($query);
    }
}
