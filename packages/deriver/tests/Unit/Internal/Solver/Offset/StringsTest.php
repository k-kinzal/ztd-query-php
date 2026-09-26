<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\Strings
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\Strings::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class StringsTest extends TestCase
{
    public function testIndexDistinguishesExistenceChecksFromReads(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        self::assertSame('Error', $strings->index(null, $instruction)->literal);
        self::assertSame('TypeError', $strings->index(\Deriver\Value\Term::array([]), $instruction)->literal);
        self::assertSame('uninitialized', $strings->index(\Deriver\Value\Term::array([]), $instruction, true)->kind);
        self::assertSame(1, $strings->index(\Deriver\Value\Term::constant(true), $instruction)->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testStringIndexAcceptsIntegerSpellingsButRejectsDecimalStrings(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        self::assertSame(1, $strings->stringIndex(\Deriver\Value\Term::constant(' +01 '), $instruction, false)->native());
        self::assertSame('TypeError', $strings->stringIndex(\Deriver\Value\Term::constant('1.0'), $instruction, false)->literal);
        self::assertSame(1, $strings->stringIndex(\Deriver\Value\Term::constant('1x'), $instruction, false)->native());
        self::assertSame('uninitialized', $strings->stringIndex(\Deriver\Value\Term::constant('1x'), $instruction, true)->kind);
    }
    public function testReadUsesBytesNegativePositionsAndSilentAbsence(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        self::assertSame('c', $strings->read(\Deriver\Value\Term::constant('abc'), \Deriver\Value\Term::constant(-1), $instruction)->native());
        self::assertSame('', $strings->read(\Deriver\Value\Term::constant('abc'), \Deriver\Value\Term::constant(3), $instruction)->native());
        self::assertSame('uninitialized', $strings->read(\Deriver\Value\Term::constant('abc'), \Deriver\Value\Term::constant(3), $instruction, true)->kind);
        self::assertTrue($strings->read(\Deriver\Value\Term::constant('abc', true), \Deriver\Value\Term::constant(0), $instruction)->isSecret());
    }
    public function testWriteReturnsFirstByteAndPreservesTheRemainder(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $write = (new \Deriver\Internal\Solver\Offset\Strings($context))->write(\Deriver\Value\Term::constant('abc'), \Deriver\Value\Term::constant(1), \Deriver\Value\Term::constant('xy'), $instruction);
        self::assertSame('axc', $write['value']->native());
        self::assertSame('x', $write['result']->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testWritePadsWithSpacesAndRejectsAnEmptyRightHandString(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        $write = $strings->write(\Deriver\Value\Term::constant('a'), \Deriver\Value\Term::constant(3), \Deriver\Value\Term::constant('Z'), $instruction);
        self::assertSame('a  Z', $write['value']->native());
        $failed = $strings->write(\Deriver\Value\Term::constant('abc'), \Deriver\Value\Term::constant(1), \Deriver\Value\Term::constant(''), $instruction);
        self::assertSame('abc', $failed['value']->native());
        self::assertSame('Error', $failed['result']->literal);
    }
    public function testWriteRejectsAnOversizedAllocationBeforePadding(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $write = (new \Deriver\Internal\Solver\Offset\Strings($context))->write(\Deriver\Value\Term::constant('a'), \Deriver\Value\Term::constant(PHP_INT_MAX), \Deriver\Value\Term::constant('Z'), $instruction);
        self::assertSame('OFFSET_OPERATION', $write['result']->literal);
        self::assertSame('MEMORY_LIMIT', $context->stopReason);
    }
    public function testConvertRecordsArrayConversionWithoutExecutingObjectCode(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        self::assertSame('Array', $strings->convert(\Deriver\Value\Term::array([]), $instruction)->native());
        self::assertSame('OFFSET_OPERATION', $strings->convert(new \Deriver\Value\Term('object', 'id'), $instruction)->literal);
    }
    public function testWarningDeduplicatesDiagnosticsAtOneOffsetOperation(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        $strings->warning($instruction);
        $strings->warning($instruction);
        self::assertCount(1, $context->frontiers);
    }
}
