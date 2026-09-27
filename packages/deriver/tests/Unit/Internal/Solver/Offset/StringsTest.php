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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
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
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
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

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIndexContracts')]
    public function testIndexFollowsTheStringOffsetConversionContract(?\Deriver\Value\Term $key, bool $silent, string $kind, mixed $literal, int $warnings): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'read', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result');
        $result = (new \Deriver\Internal\Solver\Offset\Strings($context))->index($key, $instruction, $silent);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
        self::assertCount($warnings, $context->frontiers);
    }
    /**
     * @return iterable<string,array{\Deriver\Value\Term|null,bool,string,mixed,int}>
     */
    public static function providerIndexContracts(): iterable
    {
        yield 'append' => [null,false,'throwable','Error',0];
        yield 'array' => [\Deriver\Value\Term::array([]),false,'throwable','TypeError',0];
        yield 'silent array' => [\Deriver\Value\Term::array([]),true,'uninitialized',null,0];
        yield 'object' => [new \Deriver\Value\Term('object', 'o'),false,'throwable','TypeError',0];
        yield 'silent object' => [new \Deriver\Value\Term('object', 'o'),true,'uninitialized',null,0];
        yield 'closure' => [new \Deriver\Value\Term('closure', 'c'),false,'throwable','TypeError',0];
        yield 'silent enum' => [new \Deriver\Value\Term('enum', 'e'),true,'uninitialized',null,0];
        yield 'integer' => [\Deriver\Value\Term::constant(2),false,'constant',2,0];
        yield 'negative' => [\Deriver\Value\Term::constant(-2),true,'constant',-2,0];
        yield 'true' => [\Deriver\Value\Term::constant(true),false,'constant',1,1];
        yield 'silent true' => [\Deriver\Value\Term::constant(true),true,'constant',1,0];
        yield 'null' => [\Deriver\Value\Term::constant(null),false,'constant',0,1];
        yield 'silent null' => [\Deriver\Value\Term::constant(null),true,'constant',0,0];
        yield 'integral float' => [\Deriver\Value\Term::constant(2.0),false,'constant',2,1];
        yield 'silent integral float' => [\Deriver\Value\Term::constant(2.0),true,'constant',2,0];
        yield 'silent lossy float' => [\Deriver\Value\Term::constant(2.5),true,'constant',2,1];
        yield 'integer string' => [\Deriver\Value\Term::constant('02'),false,'constant',2,0];
        yield 'signed whitespace' => [\Deriver\Value\Term::constant(" \t+2\n"),true,'constant',2,0];
        yield 'negative string' => [\Deriver\Value\Term::constant('-2'),false,'constant',-2,0];
        yield 'leading integer' => [\Deriver\Value\Term::constant('2tail'),false,'constant',2,1];
        yield 'silent leading integer' => [\Deriver\Value\Term::constant('2tail'),true,'uninitialized',null,0];
        yield 'decimal' => [\Deriver\Value\Term::constant('2.0'),false,'throwable','TypeError',0];
        yield 'leading decimal point' => [\Deriver\Value\Term::constant('.2'),false,'throwable','TypeError',0];
        yield 'exponent' => [\Deriver\Value\Term::constant('2e1'),false,'throwable','TypeError',0];
        yield 'nonnumeric' => [\Deriver\Value\Term::constant('key'),false,'throwable','TypeError',0];
        yield 'silent nonnumeric' => [\Deriver\Value\Term::constant('key'),true,'uninitialized',null,0];
        yield 'maximum' => [\Deriver\Value\Term::constant('9223372036854775807'),false,'constant',9223372036854775807,0];
        yield 'minimum' => [\Deriver\Value\Term::constant('-9223372036854775808'),false,'constant',-9223372036854775807 - 1,0];
        yield 'positive overflow' => [\Deriver\Value\Term::constant('9223372036854775808'),false,'throwable','TypeError',0];
        yield 'negative overflow' => [\Deriver\Value\Term::constant('-9223372036854775809'),true,'uninitialized',null,0];
        yield 'too many digits' => [\Deriver\Value\Term::constant('100000000000000000000'),false,'throwable','TypeError',0];
        yield 'zero padded maximum' => [\Deriver\Value\Term::constant('0009223372036854775807'),true,'constant',9223372036854775807,0];
        yield 'unknown key' => [\Deriver\Value\Term::parameter('key'),false,'opaque','OFFSET_OPERATION',0];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerByteReads')]
    public function testReadPreservesByteBoundariesAndConfidentialAbsence(string $bytes, int $index, bool $silent, bool $stringSecret, bool $keySecret, string $kind, mixed $literal, int $warnings): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'read', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result');
        $result = (new \Deriver\Internal\Solver\Offset\Strings($context))->read(\Deriver\Value\Term::constant($bytes, $stringSecret), \Deriver\Value\Term::constant($index, $keySecret), $instruction, $silent);
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
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result');
        $before = \Deriver\Value\Term::constant('abc');
        $result = (new \Deriver\Internal\Solver\Offset\Strings($context))->write($before, \Deriver\Value\Term::constant($index), \Deriver\Value\Term::fromNative($assigned), $instruction);
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
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result');
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        $fromString = $strings->write(\Deriver\Value\Term::constant('abc', true), \Deriver\Value\Term::constant(0), \Deriver\Value\Term::constant('x'), $instruction);
        $fromKey = $strings->write(\Deriver\Value\Term::constant('abc'), \Deriver\Value\Term::constant(1, true), \Deriver\Value\Term::constant('x'), $instruction);
        $fromValue = $strings->write(\Deriver\Value\Term::constant('abc'), \Deriver\Value\Term::constant(2), \Deriver\Value\Term::constant('x', true), $instruction);
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
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'read-silent', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result');
        $strings = new \Deriver\Internal\Solver\Offset\Strings($context);
        $array = new \Deriver\Value\Term('array', attributes:['open' => false], secret:true);
        $arrayResult = $strings->index($array, $instruction, true);
        $stringResult = $strings->index(\Deriver\Value\Term::constant('invalid', true), $instruction, true);
        $largeResult = $strings->index(\Deriver\Value\Term::constant('9223372036854775808', true), $instruction, true);
        self::assertSame('uninitialized', $arrayResult->kind);
        self::assertTrue($arrayResult->secret);
        self::assertSame('uninitialized', $stringResult->kind);
        self::assertTrue($stringResult->secret);
        self::assertSame('uninitialized', $largeResult->kind);
        self::assertTrue($largeResult->secret);
        self::assertSame([], $context->frontiers);
    }

}
