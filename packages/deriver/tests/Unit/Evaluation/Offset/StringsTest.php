<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Offset\Strings;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Offset\Strings
 */
#[CoversClass(Strings::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
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
final class StringsTest extends TestCase
{
    public function testIndexDistinguishesExistenceChecksFromReads(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new Strings($context);
        self::assertSame('Error', $strings->index(null, $instruction)->literal);
        self::assertSame('TypeError', $strings->index(Term::array([]), $instruction)->literal);
        self::assertSame('uninitialized', $strings->index(Term::array([]), $instruction, true)->kind);
        self::assertSame(1, $strings->index(Term::constant(true), $instruction)->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testStringIndexAcceptsIntegerSpellingsButRejectsDecimalStrings(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new Strings($context);
        self::assertSame(1, $strings->stringIndex(Term::constant(' +01 '), $instruction, false)->native());
        self::assertSame('TypeError', $strings->stringIndex(Term::constant('1.0'), $instruction, false)->literal);
        self::assertSame(1, $strings->stringIndex(Term::constant('1x'), $instruction, false)->native());
        self::assertSame('uninitialized', $strings->stringIndex(Term::constant('1x'), $instruction, true)->kind);
    }
    public function testReadUsesBytesNegativePositionsAndSilentAbsence(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new Strings($context);
        self::assertSame('c', $strings->read(Term::constant('abc'), Term::constant(-1), $instruction)->native());
        self::assertSame('', $strings->read(Term::constant('abc'), Term::constant(3), $instruction)->native());
        self::assertSame('uninitialized', $strings->read(Term::constant('abc'), Term::constant(3), $instruction, true)->kind);
        self::assertTrue($strings->read(Term::constant('abc', true), Term::constant(0), $instruction)->isSecret());
    }
    public function testWriteReturnsFirstByteAndPreservesTheRemainder(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $write = (new Strings($context))->write(Term::constant('abc'), Term::constant(1), Term::constant('xy'), $instruction);
        self::assertSame('axc', $write['value']->native());
        self::assertSame('x', $write['result']->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testWritePadsWithSpacesAndRejectsAnEmptyRightHandString(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new Strings($context);
        $write = $strings->write(Term::constant('a'), Term::constant(3), Term::constant('Z'), $instruction);
        self::assertSame('a  Z', $write['value']->native());
        $failed = $strings->write(Term::constant('abc'), Term::constant(1), Term::constant(''), $instruction);
        self::assertSame('abc', $failed['value']->native());
        self::assertSame('Error', $failed['result']->literal);
    }
    public function testWriteRejectsAnOversizedAllocationBeforePadding(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $write = (new Strings($context))->write(Term::constant('a'), Term::constant(PHP_INT_MAX), Term::constant('Z'), $instruction);
        self::assertSame('OFFSET_OPERATION', $write['result']->literal);
        self::assertSame('MEMORY_LIMIT', $context->stopReason);
    }
    public function testConvertRecordsArrayConversionWithoutExecutingObjectCode(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new Strings($context);
        self::assertSame('Array', $strings->convert(Term::array([]), $instruction)->native());
        self::assertSame('OFFSET_OPERATION', $strings->convert(new Term('object', 'id'), $instruction)->literal);
    }
    public function testWarningDeduplicatesDiagnosticsAtOneOffsetOperation(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $strings = new Strings($context);
        $strings->warning($instruction);
        $strings->warning($instruction);
        self::assertCount(1, $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIndexContracts')]
    public function testIndexFollowsTheStringOffsetConversionContract(?Term $key, bool $silent, string $kind, mixed $literal, int $warnings): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('offset', 'read', new SourceRef('test', 'a.php', 0, 1), 'result');
        $result = (new Strings($context))->index($key, $instruction, $silent);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
        self::assertCount($warnings, $context->frontiers);
    }
    /**
     * @return iterable<string,array{Term|null,bool,string,mixed,int}>
     */
    public static function providerIndexContracts(): iterable
    {
        yield 'append' => [null,false,'throwable','Error',0];
        yield 'array' => [Term::array([]),false,'throwable','TypeError',0];
        yield 'silent array' => [Term::array([]),true,'uninitialized',null,0];
        yield 'object' => [new Term('object', 'o'),false,'throwable','TypeError',0];
        yield 'silent object' => [new Term('object', 'o'),true,'uninitialized',null,0];
        yield 'closure' => [new Term('closure', 'c'),false,'throwable','TypeError',0];
        yield 'silent enum' => [new Term('enum', 'e'),true,'uninitialized',null,0];
        yield 'integer' => [Term::constant(2),false,'constant',2,0];
        yield 'negative' => [Term::constant(-2),true,'constant',-2,0];
        yield 'true' => [Term::constant(true),false,'constant',1,1];
        yield 'silent true' => [Term::constant(true),true,'constant',1,0];
        yield 'null' => [Term::constant(null),false,'constant',0,1];
        yield 'silent null' => [Term::constant(null),true,'constant',0,0];
        yield 'integral float' => [Term::constant(2.0),false,'constant',2,1];
        yield 'silent integral float' => [Term::constant(2.0),true,'constant',2,0];
        yield 'silent lossy float' => [Term::constant(2.5),true,'constant',2,1];
        yield 'integer string' => [Term::constant('02'),false,'constant',2,0];
        yield 'signed whitespace' => [Term::constant(" \t+2\n"),true,'constant',2,0];
        yield 'negative string' => [Term::constant('-2'),false,'constant',-2,0];
        yield 'leading integer' => [Term::constant('2tail'),false,'constant',2,1];
        yield 'silent leading integer' => [Term::constant('2tail'),true,'uninitialized',null,0];
        yield 'decimal' => [Term::constant('2.0'),false,'throwable','TypeError',0];
        yield 'leading decimal point' => [Term::constant('.2'),false,'throwable','TypeError',0];
        yield 'exponent' => [Term::constant('2e1'),false,'throwable','TypeError',0];
        yield 'nonnumeric' => [Term::constant('key'),false,'throwable','TypeError',0];
        yield 'silent nonnumeric' => [Term::constant('key'),true,'uninitialized',null,0];
        yield 'maximum' => [Term::constant('9223372036854775807'),false,'constant',9223372036854775807,0];
        yield 'minimum' => [Term::constant('-9223372036854775808'),false,'constant',-9223372036854775807 - 1,0];
        yield 'positive overflow' => [Term::constant('9223372036854775808'),false,'throwable','TypeError',0];
        yield 'negative overflow' => [Term::constant('-9223372036854775809'),true,'uninitialized',null,0];
        yield 'too many digits' => [Term::constant('100000000000000000000'),false,'throwable','TypeError',0];
        yield 'zero padded maximum' => [Term::constant('0009223372036854775807'),true,'constant',9223372036854775807,0];
        yield 'unknown key' => [Term::parameter('key'),false,'opaque','OFFSET_OPERATION',0];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerByteReads')]
    public function testReadPreservesByteBoundariesAndConfidentialAbsence(string $bytes, int $index, bool $silent, bool $stringSecret, bool $keySecret, string $kind, mixed $literal, int $warnings): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('offset', 'read', new SourceRef('test', 'a.php', 0, 1), 'result');
        $result = (new Strings($context))->read(Term::constant($bytes, $stringSecret), Term::constant($index, $keySecret), $instruction, $silent);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
        self::assertSame($stringSecret || $keySecret, $result->isSecret());
        self::assertCount($warnings, $context->frontiers);
    }
    /**
     * @return iterable<string,array{string,int,bool,bool,bool,string,mixed,int}>
     */
    public static function providerByteReads(): iterable
    {
        yield 'first' => ['abc',0,false,false,false,'constant','a',0];
        yield 'last' => ['abc',2,false,false,false,'constant','c',0];
        yield 'negative last' => ['abc',-1,false,false,false,'constant','c',0];
        yield 'negative first' => ['abc',-3,false,false,false,'constant','a',0];
        yield 'past end' => ['abc',3,false,false,false,'constant','',1];
        yield 'before start' => ['abc',-4,false,false,false,'constant','',1];
        yield 'silent past end' => ['abc',3,true,false,false,'uninitialized',null,0];
        yield 'silent before start' => ['abc',-4,true,false,false,'uninitialized',null,0];
        yield 'empty' => ['',0,false,false,false,'constant','',1];
        yield 'byte sequence' => ["\xc3\xa9",1,false,false,false,'constant',"\xa9",0];
        yield 'secret string' => ['abc',1,false,true,false,'constant','b',0];
        yield 'secret index' => ['abc',1,false,false,true,'constant','b',0];
        yield 'secret absent string' => ['abc',3,true,true,false,'uninitialized',null,0];
        yield 'secret absent index' => ['abc',-4,true,false,true,'uninitialized',null,0];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerByteWrites')]
    public function testWritePreservesTheOriginalOnFailureAndReturnsTheAssignedByte(int $index, mixed $assigned, string $expected, string $kind, mixed $literal, int $warnings): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('offset', 'write', new SourceRef('test', 'a.php', 0, 1), 'result');
        $before = Term::constant('abc');
        $result = (new Strings($context))->write($before, Term::constant($index), Term::fromNative($assigned), $instruction);
        self::assertSame($expected, $result['value']->native());
        self::assertSame($kind, $result['result']->kind);
        self::assertSame($literal, $result['result']->literal);
        self::assertCount($warnings, $context->frontiers);
        self::assertSame('abc', $before->native());
    }
    /**
     * @return iterable<string,array{int,mixed,string,string,mixed,int}>
     */
    public static function providerByteWrites(): iterable
    {
        yield 'first' => [0,'x','xbc','constant','x',0];
        yield 'last' => [2,'x','abx','constant','x',0];
        yield 'negative first' => [-3,'x','xbc','constant','x',0];
        yield 'negative last' => [-1,'x','abx','constant','x',0];
        yield 'before start' => [-4,'x','abc','constant',null,1];
        yield 'append position' => [3,'x','abcx','constant','x',0];
        yield 'pad gap' => [5,'x','abc  x','constant','x',0];
        yield 'empty assignment' => [1,'','abc','throwable','Error',0];
        yield 'null assignment' => [1,null,'abc','throwable','Error',0];
        yield 'long assignment' => [1,'xyz','axc','constant','x',1];
        yield 'integer conversion' => [1,42,'a4c','constant','4',1];
        yield 'array conversion' => [1,[],'aAc','constant','A',1];
    }
    public function testWriteRetainsSecretsFromEveryOperand(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('offset', 'write', new SourceRef('test', 'a.php', 0, 1), 'result');
        $strings = new Strings($context);
        $fromString = $strings->write(Term::constant('abc', true), Term::constant(0), Term::constant('x'), $instruction);
        $fromKey = $strings->write(Term::constant('abc'), Term::constant(1, true), Term::constant('x'), $instruction);
        $fromValue = $strings->write(Term::constant('abc'), Term::constant(2), Term::constant('x', true), $instruction);
        self::assertTrue($fromString['value']->isSecret());
        self::assertTrue($fromString['result']->isSecret());
        self::assertTrue($fromKey['value']->isSecret());
        self::assertTrue($fromKey['result']->isSecret());
        self::assertTrue($fromValue['value']->isSecret());
        self::assertTrue($fromValue['result']->isSecret());
    }
    public function testIndexRetainsConfidentialityWhenAnInvalidKeyIsTestedSilently(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('offset', 'read-silent', new SourceRef('test', 'a.php', 0, 1), 'result');
        $strings = new Strings($context);
        $array = new Term('array', attributes:['open' => false], secret:true);
        $arrayResult = $strings->index($array, $instruction, true);
        $stringResult = $strings->index(Term::constant('invalid', true), $instruction, true);
        $largeResult = $strings->index(Term::constant('9223372036854775808', true), $instruction, true);
        self::assertSame('uninitialized', $arrayResult->kind);
        self::assertTrue($arrayResult->secret);
        self::assertSame('uninitialized', $stringResult->kind);
        self::assertTrue($stringResult->secret);
        self::assertSame('uninitialized', $largeResult->kind);
        self::assertTrue($largeResult->secret);
        self::assertSame([], $context->frontiers);
    }

}
