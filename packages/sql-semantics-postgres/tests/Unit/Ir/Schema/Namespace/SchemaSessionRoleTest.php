<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;

#[CoversClass(SchemaSessionRole::class)]
final class SchemaSessionRoleTest extends TestCase
{
    public function testSessionAndEffectiveOwnersHaveDifferentNativeTokens(): void
    {
        self::assertSame('CURRENT_USER', SchemaSessionRole::Effective->value);
        self::assertSame('SESSION_USER', SchemaSessionRole::Session->value);
    }
}
