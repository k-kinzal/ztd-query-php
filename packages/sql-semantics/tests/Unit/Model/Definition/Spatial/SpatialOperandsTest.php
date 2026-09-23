<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Spatial\SpatialOperands;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SpatialOperands::class)]
#[Medium]
final class SpatialOperandsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testStatementRejectsReleasesWithoutSpatialDefinitionDdl(string $version): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        SpatialOperands::statement($origin, 4120);
    }

    public function testTextRejectsADifferentDatabaseLanguage(): void
    {
        $name = Expression::literal('Greek', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $name);
        $this->expectException(InvalidStructure::class);
        SpatialOperands::text($name);
    }

}
