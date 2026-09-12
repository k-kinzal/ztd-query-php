<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Type\CastTypeResolver;

#[CoversClass(CastTypeResolver::class)]
final class CastTypeResolverTest extends TestCase
{
    public function testExtractDecimalCastPreservesDeclaredPrecision(): void
    {
        $resolver = new CastTypeResolver();
        self::assertSame('DECIMAL(12,2)', $resolver->extractDecimalCast('decimal(12,2)'));
        self::assertSame('DECIMAL(12,0)', $resolver->extractDecimalCast('DECIMAL(12)'));
        self::assertSame('DECIMAL(65,30)', $resolver->extractDecimalCast('NUMERIC'));
    }

    public function testMapNativeTypeToCastTypeUsesMySqlCastFamilies(): void
    {
        $resolver = new CastTypeResolver();
        self::assertSame('SIGNED', $resolver->mapNativeTypeToCastType('integer'));
        self::assertSame('BINARY', $resolver->mapNativeTypeToCastType('VARBINARY(16)'));
        self::assertSame('DATETIME', $resolver->mapNativeTypeToCastType('TIMESTAMP'));
        self::assertSame('CHAR', $resolver->mapNativeTypeToCastType('unknown'));
    }

}
