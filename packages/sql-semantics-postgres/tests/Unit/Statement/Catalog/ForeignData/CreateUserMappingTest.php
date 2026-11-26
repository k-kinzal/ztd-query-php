<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\CreateUserMapping::class)]
#[Medium]
final class CreateUserMappingTest extends TestCase
{
    public function testRenderWritesUser(): void
    {
        self::assertSame('CREATE USER MAPPING FOR USER SERVER s OPTIONS (user \'bob\')', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE USER MAPPING FOR USER SERVER s OPTIONS (user \'bob\')')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE USER MAPPING IF NOT EXISTS FOR public SERVER s')->facts->diagnostics);
    }
}
