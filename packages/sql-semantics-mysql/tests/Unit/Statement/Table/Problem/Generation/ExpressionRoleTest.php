<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole;

#[CoversClass(ExpressionRole::class)]
#[Small]
final class ExpressionRoleTest extends TestCase
{
    public function testSeparatesGenerationExpressionsFromExpressionDefaults(): void
    {
        self::assertSame(['Generated', 'Default'], array_map(static fn (ExpressionRole $role): string => $role->name, ExpressionRole::cases()));
    }
}
