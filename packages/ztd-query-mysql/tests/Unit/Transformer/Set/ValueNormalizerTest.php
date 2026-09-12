<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer;

#[CoversClass(ValueNormalizer::class)]
final class ValueNormalizerTest extends TestCase
{
    public function testNormalizeSetValue(): void
    {
        $normalizer = new ValueNormalizer();
        self::assertSame('red,blue', $normalizer->normalizeSetValue('blue, red,blue,unknown', "SET('red','green','blue')"));
        self::assertSame('', $normalizer->normalizeSetValue('', "SET('red')"));
        self::assertSame('blue', $normalizer->normalizeSetValue('blue', "SET('red')"));
        self::assertSame('blue,red', $normalizer->normalizeSetValue('blue,red', 'TEXT'));
    }

    public function testExtractSetMembers(): void
    {
        $normalizer = new ValueNormalizer();
        self::assertSame(['red', "it's", ''], $normalizer->extractSetMembers("SET('red','it''s','')"));
        self::assertSame(['red', 'blue'], $normalizer->extractSetMembers(' SET(red, blue) '));
        self::assertSame([], $normalizer->extractSetMembers('ENUM(1,2)'));
    }

}
