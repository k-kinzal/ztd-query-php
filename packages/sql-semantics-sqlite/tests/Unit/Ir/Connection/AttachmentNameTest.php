<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Connection\AttachmentName;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;

#[CoversClass(AttachmentName::class)]
#[Small]
final class AttachmentNameTest extends TestCase
{
    public function testToStringKeepsTheTypeWhileTheNameUsesSqliteConversion(): void
    {
        $numeric = new AttachmentName(new IntegerLiteral('25'));
        $empty = new AttachmentName(new NullLiteral());
        $literal = new AttachmentName(new TextLiteral("it's"));

        self::assertSame('25', $numeric->name->value);
        self::assertSame('25', $numeric->toString());
        self::assertSame('', $empty->name->value);
        self::assertSame('NULL', $empty->toString());
        self::assertSame("'it''s'", $literal->toString());
    }
}
