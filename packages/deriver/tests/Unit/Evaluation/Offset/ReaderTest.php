<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Offset\Reader;
use Deriver\Evaluation\State;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Offset\Reader
 */
#[CoversClass(Reader::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Strings::class)]
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
#[UsesClass(\Deriver\Result\Frontier::class)]
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
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
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
#[UsesClass(\Deriver\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
