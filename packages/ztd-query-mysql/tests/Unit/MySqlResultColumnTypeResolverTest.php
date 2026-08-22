<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlColumnTypeMapper;
use ZtdQuery\Platform\MySql\MySqlMysqliResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\MySqlPdoResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\MySqlResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlResultColumnTypeResolver::class)]
#[UsesClass(MySqlMysqliResultColumnTypeResolver::class)]
#[UsesClass(MySqlPdoResultColumnTypeResolver::class)]
#[UsesClass(MySqlColumnTypeMapper::class)]
final class MySqlResultColumnTypeResolverTest extends TestCase
{
    public function testSelectsResolverFromMetadataShape(): void
    {
        $resolver = new MySqlResultColumnTypeResolver();

        self::assertSame(ColumnTypeFamily::INTEGER, $resolver->resolve(['native_type' => 'LONGLONG'])->family);
        self::assertSame(ColumnTypeFamily::JSON, $resolver->resolve(['type' => MYSQLI_TYPE_JSON])->family);
    }

    public function testMysqliShapeDoesNotFallBackToPdoMetadata(): void
    {
        $type = (new MySqlResultColumnTypeResolver())->resolve([
            'type' => 'invalid',
            'native_type' => 'LONGLONG',
        ]);

        self::assertSame(ColumnTypeFamily::UNKNOWN, $type->family);
    }
}
