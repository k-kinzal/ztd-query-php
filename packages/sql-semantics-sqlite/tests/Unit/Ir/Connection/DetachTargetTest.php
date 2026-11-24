<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Connection\AttachmentName;
use SqlSemantics\Platform\Sqlite\Ir\Connection\DetachTarget;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;

#[CoversClass(DetachTarget::class)]
#[Small]
final class DetachTargetTest extends TestCase
{
    public function testSchemaAndNameKeepIdentityAndRequestedSpelling(): void
    {
        $target = new DetachTarget(new Identifier('alpha'), new AttachmentName(new TextLiteral('ALPHA')));

        self::assertSame('alpha', $target->schema->value);
        self::assertSame('ALPHA', $target->name->name->value);
    }
}
