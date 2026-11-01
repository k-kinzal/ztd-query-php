<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Attributes;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Attributes::class)]
#[Small]
final class AttributesTest extends TestCase
{
    public function testMergeMergesAsJsonMergePatchAndOrdersTheMembers(): void
    {
        self::assertSame('{"a": 1, "ab": 1, "longer": 2}', (new Attributes())->merge('{"comment": "x", "longer": 1}', '{"longer": 2, "ab": 1, "a": 1, "comment": null}'));
    }

    public function testMergeRefusesWhatIsNotAJsonObject(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3982);
        $this->expectExceptionMessage('The user attribute must be a valid JSON object');

        (new Attributes())->merge(null, '[1]');
    }

    public function testCommentSetsTheCommentMember(): void
    {
        self::assertSame('{"a": 1, "comment": "hi\'x"}', (new Attributes())->comment('{"a": 1}', "hi'x"));
    }

    public function testPatchMergesNestedObjects(): void
    {
        $patched = (new Attributes())->patch(JsonNode::parse('{"o": {"a": 1, "b": 2}}'), JsonNode::parse('{"o": {"b": null, "c": 3}}'));

        self::assertSame('{"o": {"a": 1, "c": 3}}', $patched->text());
    }

    public function testTextOrdersTheMembersAsTheServerWritesThem(): void
    {
        self::assertSame('{"b": 1, "aa": 2}', (new Attributes())->text(new JsonNode(JsonKind::Object, ['aa' => new JsonNode(JsonKind::Integer, '2'), 'b' => new JsonNode(JsonKind::Integer, '1')])));
    }
}
