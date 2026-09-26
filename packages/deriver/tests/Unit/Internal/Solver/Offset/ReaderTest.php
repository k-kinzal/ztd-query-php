<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\Reader
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\Reader::class)]
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
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Strings::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ReaderTest extends TestCase
{
    public function testReadScalarOffsetsReturnNullWithAWarning(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $reader = new \Deriver\Internal\Solver\Offset\Reader($context);
        self::assertNull($reader->read(\Deriver\Value\Term::constant(4), \Deriver\Value\Term::array([]), $instruction, new \Deriver\Internal\Solver\State())->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testArrayRejectsIllegalKeysEvenInExistenceChecks(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $reader = new \Deriver\Internal\Solver\Offset\Reader($context);
        self::assertSame('TypeError', $reader->array(\Deriver\Value\Term::array([]), \Deriver\Value\Term::array([]), $instruction, new \Deriver\Internal\Solver\State(), true)->literal);
        self::assertSame('uninitialized', $reader->array(\Deriver\Value\Term::array([]), \Deriver\Value\Term::constant('absent'), $instruction, new \Deriver\Internal\Solver\State(), true)->kind);
        self::assertSame([], $context->frontiers);
    }
    public function testKeyTruncatesFloatKeysAndRecordsLossOfPrecision(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $key = (new \Deriver\Internal\Solver\Offset\Reader($context))->key(\Deriver\Value\Term::constant(1.5), $instruction);
        self::assertSame(1, $key->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testPlainObjectRecognizesArrayAccessImplementations(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class A implements ArrayAccess{} function target(){}');
        $reader = new \Deriver\Internal\Solver\Offset\Reader($context);
        self::assertTrue($reader->plainObject(new \Deriver\Value\Term('object', 'a', attributes: ['class' => 'stdClass'])));
        self::assertFalse($reader->plainObject(new \Deriver\Value\Term('object', 'b', attributes: ['class' => 'A'])));
        self::assertTrue($reader->plainObject(new \Deriver\Value\Term('closure', 'c')));
    }
}
