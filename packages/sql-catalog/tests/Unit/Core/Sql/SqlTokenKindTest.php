<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Sql\SqlTokenKind;

#[CoversClass(SqlTokenKind::class)]
final class SqlTokenKindTest extends TestCase
{
    public function testEveryKindHasADistinctName(): void
    {
        $values = array_column(SqlTokenKind::cases(), 'value');
        self::assertSame($values, array_values(array_unique($values)));
        self::assertContains('parameter', $values);
    }
}
