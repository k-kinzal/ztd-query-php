<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Report\ValueReader
 */
#[CoversClass(\Deriver\Report\ValueReader::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
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
}
