<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversNothing]
final class ResultColumnTypeResolverTest extends TestCase
{
    public function testResolveReadsTheTypeADriverReported(): void
    {
        $type = (new FakeResultColumnTypeResolver())->resolve(['native_type' => 'INT']);

        self::assertSame(ColumnTypeFamily::INTEGER, $type->family);
    }

    public function testResolveAnswersATypeEvenWhereTheDriverSaidNothing(): void
    {
        $type = (new FakeResultColumnTypeResolver())->resolve([]);

        self::assertSame(ColumnTypeFamily::TEXT, $type->family);
    }

    public function testResolveTellsTwoDifferentNativeTypesApart(): void
    {
        $resolver = new FakeResultColumnTypeResolver();

        self::assertNotSame(
            $resolver->resolve(['native_type' => 'int'])->family,
            $resolver->resolve(['native_type' => 'double'])->family,
        );
    }
}
