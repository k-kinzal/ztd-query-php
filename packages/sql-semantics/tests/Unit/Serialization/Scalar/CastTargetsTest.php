<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\CastTargets;
use SqlSemantics\Type\Identity;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CastTargets::class)]
#[Medium]
final class CastTargetsTest extends TestCase
{
    #[TestWith(['CHAR(3) CHARACTER SET utf8mb4', 'CHAR(3) CHARACTER SET `utf8mb4`'])]
    #[TestWith(['CHAR BINARY', 'CHAR BINARY'])]
    #[TestWith(['NCHAR(2)', 'NCHAR(2)'])]
    #[TestWith(['BINARY(2)', 'BINARY(2)'])]
    #[TestWith(['DECIMAL(5,2)', 'DECIMAL(5, 2)'])]
    #[TestWith(['DATETIME(3)', 'DATETIME(3)'])]
    #[TestWith(['REAL', 'DOUBLE'])]
    #[TestWith(['FLOAT(3)', 'FLOAT(3)'])]
    #[TestWith(['YEAR', 'YEAR'])]
    #[TestWith(['JSON', 'JSON'])]
    #[TestWith(['POINT', 'POINT'])]
    #[TestWith(['UNSIGNED', 'UNSIGNED'])]
    public function testWriteUsesTheMySqlCastVocabulary(string $target, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('SELECT CAST(1 AS ' . $target . ')');
        self::assertSame('SELECT CAST(1 AS ' . $expected . ')', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testWriteKeepsDeclarationsForOtherDialects(): void
    {
        self::assertSame('numeric', CastTargets::write(TypeDescriptor::builtin(Dialect::PostgreSql, 'numeric'))->toString());
    }

    public function testNumberWritesDecimalFloatAndDouble(): void
    {
        self::assertSame('DOUBLE', CastTargets::number(new Identity\Numeric\NumericStorage(Identity\BuiltinIdentity::DoublePrecision))->toString());
        self::assertSame('FLOAT', CastTargets::number(new Identity\Numeric\NumericStorage(Identity\BuiltinIdentity::Float))->toString());
        self::assertSame('DECIMAL', CastTargets::number(new Identity\Numeric\NumericStorage(Identity\BuiltinIdentity::Numeric))->toString());
    }

    public function testTextWritesBinaryAndCharacterTargets(): void
    {
        self::assertSame('BINARY', CastTargets::text(new Identity\StringStorage(Identity\BuiltinIdentity::Varbinary))->toString());
        self::assertSame('NCHAR', CastTargets::text(new Identity\StringStorage(Identity\BuiltinIdentity::Char, national: true))->toString());
    }

    public function testKeywordWritesAParameterlessTargetInUppercase(): void
    {
        self::assertSame('DATE', CastTargets::keyword(TypeDescriptor::builtin(Dialect::MySql, 'date'))->toString());
    }
}
