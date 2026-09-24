<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Document\JsonReturning;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(JsonReturning::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class JsonReturningTest extends TestCase
{
    #[TestWith(['text', null, true])]
    #[TestWith(['text', Format::Json, true])]
    #[TestWith(['text', Format::Utf8, false])]
    #[TestWith(['bytea', Format::Utf8, true])]
    #[TestWith(['bytea', Format::Utf16, false])]
    #[TestWith(['bytea', Format::Utf32, false])]
    public function testAcceptsOnlyAUtf8EncodingOfBytea(string $type, ?Format $format, bool $accepted): void
    {
        self::assertSame($accepted, JsonReturning::accepts(TypeDescriptor::builtin(Dialect::PostgreSql, $type), $format));
    }

    public function testKeepsTheTypeAndFormat(): void
    {
        $returning = new JsonReturning(TypeDescriptor::builtin(Dialect::PostgreSql, 'bytea'), Format::Utf8);
        self::assertSame('bytea', $returning->type->name);
        self::assertSame(Format::Utf8, $returning->format);
    }

    public function testRejectsAnEncodedTextResult(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonReturning(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Format::Utf8);
    }

    public function testRejectsAMySqlType(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonReturning(TypeDescriptor::builtin(Dialect::MySql, 'json'));
    }
}
