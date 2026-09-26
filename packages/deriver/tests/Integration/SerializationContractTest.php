<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Api\Project\EntryPoint;
use Deriver\Api\Query\QueryScope;
use Deriver\Api\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Small]
final class SerializationContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSecretEntriesAndDerivedReturnsAreRedactedByDefault(): void
    {
        $session = Analysis::session('<?php function target($secret){return "prefix:".$secret;}');
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('confidential-token', true)])]));
        $result = $session->derive($query);
        $safe = $result->toJson();
        $revealed = $result->toJson(true);
        self::assertStringNotContainsString(base64_encode('confidential-token'), $safe);
        self::assertStringNotContainsString(base64_encode('prefix:confidential-token'), $safe);
        self::assertStringContainsString('"redacted": true', $safe);
        self::assertStringContainsString(base64_encode('prefix:confidential-token'), $revealed);
        self::assertStringContainsString('"scope"', $safe);
        self::assertStringContainsString('"budget"', $safe);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testNonUtf8BytesAndFloatingPointSpecialValuesHaveLosslessTags(): void
    {
        $session = Analysis::session('<?php function target($input){return $input;}');
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::array(['bytes' => Term::constant("\xff\x00"), 'infinity' => Term::constant(INF), 'negative-zero' => Term::constant(-0.0)])])]));
        $json = $session->derive($query)->toJson();
        self::assertJson($json);
        self::assertStringContainsString(base64_encode("\xff\x00"), $json);
        self::assertStringContainsString('7ff0000000000000', $json);
        self::assertStringContainsString('8000000000000000', $json);
    }
    /**
     * @throws JsonException If lossless result encoding fails
     */
    public function testNonUtf8IdentifierMetadataRoundTripsWithoutReplacement(): void
    {
        $result = Analysis::returns("<?php function target(){return \xe8;}");
        $json = json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertStringContainsString('~b64~' . base64_encode("constant:\xe8"), $result->toJson());
        self::assertSame("constant:\xe8", (new \Deriver\Report\JsonText())->decode('~b64~' . base64_encode("constant:\xe8")));
    }

    /**
     * @throws JsonException If the lossless report cannot be encoded
     */
    public function testStorageGraphPreservesReturnedObjectCyclesAndLocalAliases(): void
    {
        $result = Analysis::returns('<?php class B{public ?B $self=null;public int $value=3;} function target(){$b=new B;$alias=$b;$b->self=$b;$x=1;$y=&$x;return $b;}');
        $outcome = $result->normalOutcomes[0];
        $identity = $outcome->values['return']->literal;
        self::assertIsString($identity);
        self::assertSame($identity, $outcome->storage->cells['object:'.$identity]->operands['self']->literal);
        self::assertSame(3, $outcome->storage->cells['object:'.$identity]->operands['value']->native());
        self::assertSame($outcome->storage->bindings['x']->literal, $outcome->storage->bindings['y']->literal);
        self::assertTrue(\Tests\Fake\ReportSchema::accepts($result->toJson()));
        $reader = \Deriver\Report\ValueReader::fromJson($result->toJson());
        self::assertSame('object', $reader->read('v0')->kind);
    }

    /**
     * @throws JsonException If the lossless report cannot be encoded
     */
    public function testExceptionalStorageIncludesPropertiesWrittenBeforeTheThrow(): void
    {
        $result = Analysis::returns('<?php class B{public int $value=1;} function target(){$b=new B;$b->value=4;throw new Error("failure");}');
        $outcome = $result->exceptionalOutcomes[0];
        $identity = $outcome->state['b']->literal;
        self::assertIsString($identity);
        self::assertSame(4, $outcome->storage->cells['object:'.$identity]->operands['value']->native());
        self::assertNotEmpty($outcome->evidence);
        self::assertTrue(\Tests\Fake\ReportSchema::accepts($result->toJson()));
    }
}
