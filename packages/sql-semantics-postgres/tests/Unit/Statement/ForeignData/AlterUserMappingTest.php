<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\ForeignData\AlterUserMapping::class)]
#[Medium]
final class AlterUserMappingTest extends TestCase
{
    public function testRenderWritesTheChanges(): void
    {
        self::assertSame('ALTER USER MAPPING FOR bob SERVER s OPTIONS (SET password \'x\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER USER MAPPING FOR bob SERVER s OPTIONS (SET password \'x\')')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER USER MAPPING FOR CURRENT_ROLE SERVER s OPTIONS (DROP a)')->facts->diagnostics);
    }
}
