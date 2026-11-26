<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Ir\Connection\AttachmentName;
use SqlSemantics\Platform\Sqlite\Ir\Connection\ConnectionOperands;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(ConnectionOperands::class)]
#[Small]
final class ConnectionOperandsTest extends TestCase
{
    public function testRowTreatsTheRootIdentifierAsText(): void
    {
        $value = (new Semantics())->analyze('ATTACH fixture_file AS ((archive))');

        self::assertInstanceOf(Attach::class, $value);
        self::assertInstanceOf(TextLiteral::class, $value->file->values[0]);
        self::assertSame('fixture_file', $value->file->values[0]->value);
        self::assertInstanceOf(AttachmentName::class, $value->name);
        self::assertSame('archive', $value->name->name->value);
    }

    public function testNameRetainsTheOriginalNumberedSlot(): void
    {
        $value = (new Semantics())->analyze("ATTACH ':memory:' AS ?005");

        self::assertInstanceOf(Attach::class, $value);
        self::assertInstanceOf(Parameter::class, $value->name);
        self::assertSame('005', $value->name->label);
    }
}
