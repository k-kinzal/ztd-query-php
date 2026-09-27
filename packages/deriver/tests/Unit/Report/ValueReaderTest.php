<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \Deriver\Report\ValueReader
 */
#[CoversClass(\Deriver\Report\ValueReader::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\SecretFingerprint::class)]
#[UsesClass(\Deriver\Report\Decode\Fields::class)]
#[UsesClass(\Deriver\Report\Decode\Scalar::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ValueReaderTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testFromJsonRejectsUnsupportedVersions(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        \Deriver\Report\ValueReader::fromJson('{"schemaVersion":"2","values":{}}');
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testFromJsonRejectsDanglingReferences(): void
    {
        $json = '{"schemaVersion":"1","values":{"v0":{"kind":"array","literal":{"type":"null","value":null},"operands":[{"key":{"type":"int64","value":"0"},"value":"v9"}],"attributes":[],"redacted":false,"secret":false}}}';
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        \Deriver\Report\ValueReader::fromJson($json);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testFromJsonRejectsCycles(): void
    {
        $json = '{"schemaVersion":"1","values":{"v0":{"kind":"array","literal":{"type":"null","value":null},"operands":[{"key":{"type":"int64","value":"0"},"value":"v0"}],"attributes":[],"redacted":false,"secret":false}}}';
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        \Deriver\Report\ValueReader::fromJson($json);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testFromJsonEnforcesDepthRegardlessOfRecordOrder(): void
    {
        $json = \Tests\Fake\ValueDocument::encode(\Deriver\Value\Term::fromNative([[[1]]]));
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        \Deriver\Report\ValueReader::fromJson($json, maximumDepth:2);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testReadRestoresExactScalarsAndSharedTerms(): void
    {
        $child = \Deriver\Value\Term::constant("\xff\0");
        $value = \Deriver\Value\Term::array(['first' => $child,'second' => $child,'minimum' => \Deriver\Value\Term::constant(-9223372036854775807 - 1),'maximum' => \Deriver\Value\Term::constant(9223372036854775807),'negative-zero' => \Deriver\Value\Term::constant(-0.0)]);
        $decoded = \Deriver\Report\ValueReader::fromJson(\Tests\Fake\ValueDocument::encode($value))->read('v0');
        self::assertSame($decoded->operands['first'], $decoded->operands['second']);
        self::assertSame("\xff\0", $decoded->operands['first']->native());
        self::assertSame(-9223372036854775807 - 1, $decoded->operands['minimum']->native());
        self::assertSame(9223372036854775807, $decoded->operands['maximum']->native());
        self::assertIsFloat($decoded->operands['negative-zero']->literal);
        self::assertSame('8000000000000000', bin2hex(pack('E', $decoded->operands['negative-zero']->literal)));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testReadKeepsRedactedValuesOpaqueAndConfidential(): void
    {
        $value = \Deriver\Value\Term::array(['secret' => \Deriver\Value\Term::constant('hidden', true)]);
        $json = \Tests\Fake\ValueDocument::encode($value, false);
        self::assertStringNotContainsString(base64_encode('hidden'), $json);
        $decoded = \Deriver\Report\ValueReader::fromJson($json)->read('v0');
        self::assertSame('opaque', $decoded->kind);
        self::assertSame('REDACTED', $decoded->literal);
        self::assertTrue($decoded->isSecret());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testReadRestoresExplicitSecrecyWithoutChangingTheExpressionIdentity(): void
    {
        $value = \Deriver\Value\Term::array(['secret' => \Deriver\Value\Term::constant('hidden', true)]);
        $json = \Tests\Fake\ValueDocument::encode($value);
        $decoded = \Deriver\Report\ValueReader::fromJson($json)->read('v0');
        self::assertFalse($decoded->secret);
        self::assertTrue($decoded->operands['secret']->secret);
        self::assertSame($json, \Tests\Fake\ValueDocument::encode($decoded));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOperandsPreservesStringAndIntegerKeysInOrder(): void
    {
        $value = \Deriver\Value\Term::fromNative(['+1' => 'string',1 => 'integer']);
        $decoded = \Deriver\Report\ValueReader::fromJson(\Tests\Fake\ValueDocument::encode($value))->read('v0');
        self::assertSame(['+1',1], array_keys($decoded->operands));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testAttributesDecodesArbitraryIdentifierBytes(): void
    {
        $value = new \Deriver\Value\Term('custom', attributes:["\xff" => "\0\xff",'~b64~reserved' => 7]);
        $decoded = \Deriver\Report\ValueReader::fromJson(\Tests\Fake\ValueDocument::encode($value))->read('v0');
        self::assertSame($value->attributes, $decoded->attributes);
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidReaderLimits')]
    public function testFromJsonRejectsInvalidResourceLimits(int $nodes, int $depth): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('Invalid value-reader limits');
        \Deriver\Report\ValueReader::fromJson('{"schemaVersion":"1","values":{}}', $nodes, $depth);
    }

    /**
     * @return iterable<string,array{int,int}>
     */
    public static function providerInvalidReaderLimits(): iterable
    {
        yield 'zero nodes' => [0, 1];
        yield 'negative nodes' => [-1, 1];
        yield 'zero depth' => [1, 0];
        yield 'negative depth' => [1, -1];
        yield 'over maximum depth' => [1, 513];
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    public function testFromJsonAcceptsExactNodeAndDepthLimits(): void
    {
        $json = '{"schemaVersion":"1","values":{"v1":{"kind":"constant","literal":{"type":"int64","value":"7"},"operands":[],"attributes":[],"redacted":false,"secret":false},"v0":{"kind":"array","literal":{"type":"null","value":null},"operands":[{"key":{"type":"int64","value":"0"},"value":"v1"}],"attributes":[],"redacted":false,"secret":false}}}';
        $reader = \Deriver\Report\ValueReader::fromJson($json, 2, 2);
        self::assertSame([7], $reader->read('v0')->native());
        self::assertSame($reader->read('v1'), $reader->read('v0')->operands[0]);
        self::assertSame(7, \Deriver\Report\ValueReader::fromJson(\Tests\Fake\ValueDocument::encode(\Deriver\Value\Term::constant(7)), 1, 512)->read('v0')->literal);
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    public function testFromJsonRejectsAnAdditionalNodeEvenWhenItIsUnreferenced(): void
    {
        $json = '{"schemaVersion":"1","values":{"v0":{"kind":"constant","literal":{"type":"null","value":null},"operands":[],"attributes":[],"redacted":false,"secret":false},"v1":{"kind":"constant","literal":{"type":"null","value":null},"operands":[],"attributes":[],"redacted":false,"secret":false}}}';
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('node limit');
        \Deriver\Report\ValueReader::fromJson($json, 1);
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testFromJsonRejectsReportsLargerThanTheByteLimitBeforeParsing(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('report larger than 32 MiB');
        \Deriver\Report\ValueReader::fromJson(str_repeat(' ', 33554433));
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testFromJsonAcceptsAValidReportAtTheExactByteLimit(): void
    {
        $json = \Tests\Fake\ValueDocument::encode(\Deriver\Value\Term::constant(7));
        $reader = \Deriver\Report\ValueReader::fromJson(str_pad($json, 33554432));
        self::assertSame(7, $reader->read('v0')->literal);
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMalformedValueRecords')]
    public function testFromJsonValidatesEveryRecordAndPayload(string $json, string $message): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage($message);
        \Deriver\Report\ValueReader::fromJson($json);
    }

    /**
     * @return iterable<string,array{string,string}>
     * @throws JsonException If fixture documents cannot be encoded
     */
    public static function providerMalformedValueRecords(): iterable
    {
        $node = ['kind' => 'constant', 'literal' => ['type' => 'null', 'value' => null], 'operands' => [], 'attributes' => [], 'redacted' => false, 'secret' => false];
        yield 'array root' => ['[]', 'Unsupported Deriver report schema'];
        yield 'null root' => ['null', 'Unsupported Deriver report schema'];
        yield 'missing version' => ['{"values":{}}', 'schemaVersion'];
        yield 'wrong version type' => ['{"schemaVersion":1,"values":{}}', 'schemaVersion'];
        yield 'value array' => ['{"schemaVersion":"1","values":[]}', 'values'];
        foreach (['wrong', '0', 'v-1', 'v1suffix', "v1\n"] as $id) {
            yield 'identifier ' . $id => [json_encode(['schemaVersion' => '1', 'values' => (object)[$id => $node]], JSON_THROW_ON_ERROR), 'Invalid value table identifier'];
        }
        yield 'scalar record' => ['{"schemaVersion":"1","values":{"v0":7}}', 'Invalid value table identifier or record'];
        yield 'empty kind' => [json_encode(['schemaVersion' => '1', 'values' => ['v0' => array_replace($node, ['kind' => ''])]], JSON_THROW_ON_ERROR), 'Value kinds must not be empty'];
        yield 'invalid orphan record' => [json_encode(['schemaVersion' => '1', 'values' => ['v0' => $node, 'v1' => array_replace($node, ['kind' => ''])]], JSON_THROW_ON_ERROR), 'Value kinds must not be empty'];
        yield 'redacted literal' => [json_encode(['schemaVersion' => '1', 'values' => ['v0' => array_replace($node, ['redacted' => true, 'literal' => ['type' => 'int64', 'value' => '7']])]], JSON_THROW_ON_ERROR), 'redacted record must not contain a payload'];
        yield 'redacted attributes' => [json_encode(['schemaVersion' => '1', 'values' => ['v0' => array_replace($node, ['redacted' => true, 'attributes' => [['key' => 'type', 'value' => ['type' => 'bytes', 'value' => base64_encode('int')]]]])]], JSON_THROW_ON_ERROR), 'redacted record must not contain a payload'];
        yield 'redacted operands' => [json_encode(['schemaVersion' => '1', 'values' => ['v0' => array_replace($node, ['redacted' => true, 'operands' => [['key' => ['type' => 'int64', 'value' => '0'], 'value' => 'v1']]]), 'v1' => $node]], JSON_THROW_ON_ERROR), 'redacted record must not contain a payload'];
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidOperandKeys')]
    public function testOperandsRejectsInvalidDuplicateAndNoncanonicalKeys(string $json): void
    {
        $reader = \Deriver\Report\ValueReader::fromJson(\Tests\Fake\ValueDocument::encode(\Deriver\Value\Term::constant(1)));
        $record = json_decode($json, false, flags:JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $record);
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('Invalid, duplicate, or noncanonical PHP operand key');
        $reader->operands($record);
    }

    /**
     * @return iterable<string,array{string}>
     * @throws JsonException If fixture documents cannot be encoded
     */
    public static function providerInvalidOperandKeys(): iterable
    {
        foreach ([['type' => 'null', 'value' => null], ['type' => 'bool', 'value' => true], ['type' => 'float64', 'value' => '3ff0000000000000'], ['type' => 'bytes', 'value' => base64_encode('1')]] as $index => $key) {
            yield 'invalid category ' . $index => [json_encode(['operands' => [['key' => $key, 'value' => 'v0']]], JSON_THROW_ON_ERROR)];
        }
        $operand = ['key' => ['type' => 'int64', 'value' => '1'], 'value' => 'v0'];
        yield 'duplicate' => [json_encode(['operands' => [$operand, $operand]], JSON_THROW_ON_ERROR)];
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    public function testAttributesRejectsRepeatedNamesAfterByteDecoding(): void
    {
        $reader = \Deriver\Report\ValueReader::fromJson(\Tests\Fake\ValueDocument::encode(\Deriver\Value\Term::constant(1)));
        $record = new stdClass();
        $record->attributes = [(object)['key' => 'name', 'value' => (object)['type' => 'null', 'value' => null]], (object)['key' => 'name', 'value' => (object)['type' => 'null', 'value' => null]]];
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('Duplicate value attribute');
        $reader->attributes($record);
    }

    /**
     * @throws JsonException If encoded records cannot be decoded
     */
    public function testReadPreservesRedactedKindAndCannotMistakeRedactionForPublicNull(): void
    {
        $reader = \Deriver\Report\ValueReader::fromJson('{"schemaVersion":"1","values":{"v0":{"kind":"array","literal":{"type":"null","value":null},"operands":[],"attributes":[],"redacted":true,"secret":false}}}');
        $value = $reader->read('v0');
        self::assertSame('opaque', $value->kind);
        self::assertSame('REDACTED', $value->literal);
        self::assertSame(['redactedKind' => 'array', 'type' => 'mixed'], $value->attributes);
        self::assertTrue($value->secret);
        self::assertFalse($value->isConcrete());
    }
}
