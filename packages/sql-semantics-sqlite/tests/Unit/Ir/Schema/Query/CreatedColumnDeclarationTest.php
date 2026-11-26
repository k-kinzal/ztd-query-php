<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration;

#[CoversClass(CreatedColumnDeclaration::class)]
#[Small]
final class CreatedColumnDeclarationTest extends TestCase
{
    public function testHasOnlyTheNativeCreationTypeNames(): void
    {
        self::assertSame(['', 'INT', 'NUM', 'REAL', 'TEXT'], array_column(CreatedColumnDeclaration::cases(), 'value'));
    }
}
