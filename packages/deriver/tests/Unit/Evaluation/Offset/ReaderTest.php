<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Offset\Reader;
use Deriver\Evaluation\Offset\Strings;
use Deriver\Evaluation\State;
use Deriver\Memory\Memory;
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
use Deriver\Result\Frontier;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
use Deriver\Value\IntegerConversion;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Offset\Reader
 */
#[CoversClass(Reader::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
#[UsesClass(Strings::class)]
#[UsesClass(State::class)]
#[UsesClass(Memory::class)]
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
#[UsesClass(Frontier::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
#[UsesClass(IntegerConversion::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ReaderTest extends TestCase
{
    public function testReadScalarOffsetsReturnNullWithAWarning(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $reader = new Reader($context);
        self::assertNull($reader->read(Term::constant(4), Term::array([]), $instruction, new State())->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testArrayRejectsIllegalKeysEvenInExistenceChecks(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $reader = new Reader($context);
        self::assertSame('TypeError', $reader->array(Term::array([]), Term::array([]), $instruction, new State(), true)->literal);
        self::assertSame('uninitialized', $reader->array(Term::array([]), Term::constant('absent'), $instruction, new State(), true)->kind);
        self::assertSame([], $context->frontiers);
    }
    public function testKeyTruncatesFloatKeysAndRecordsLossOfPrecision(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $key = (new Reader($context))->key(Term::constant(1.5), $instruction);
        self::assertSame(1, $key->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testPlainObjectRecognizesArrayAccessImplementations(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class A implements ArrayAccess{} function target(){}');
        $reader = new Reader($context);
        self::assertTrue($reader->plainObject(new Term('object', 'a', attributes: ['class' => 'stdClass'])));
        self::assertFalse($reader->plainObject(new Term('object', 'b', attributes: ['class' => 'A'])));
        self::assertTrue($reader->plainObject(new Term('closure', 'c')));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalarPatterns')]
    public function testReadTreatsScalarPatternEntriesAsNullWithoutOffsetDiagnostics(Term $container): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $instruction = new Instruction('i', 'array-read', $source, 'result', attributes:['destructure' => true]);
        $result = (new Reader($context))->read($container, Term::constant(1), $instruction, new State());
        self::assertSame('constant', $result->kind);
        self::assertNull($result->literal);
        self::assertSame($container->isSecret(), $result->isSecret());
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{Term}>
     */
    public static function providerScalarPatterns(): iterable
    {
        yield 'null' => [Term::constant(null)];
        yield 'integer' => [Term::constant(42)];
        yield 'float' => [Term::constant(1.5)];
        yield 'true' => [Term::constant(true)];
        yield 'false' => [Term::constant(false)];
        yield 'string' => [Term::constant('abc')];
        yield 'secret string' => [Term::constant('secret', true)];
        yield 'uninitialized' => [new Term('uninitialized')];
    }
}
