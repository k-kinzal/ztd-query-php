<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Pragma;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Pragma\NumericArgument;
use SqlSemantics\Model\Configuration\Pragma\Sign;
use SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Sign::class)]
#[Medium]
final class SignTest extends TestCase
{
    public function testRepresentsEverySignSpelling(): void
    {
        self::assertSame(['', '+', '-'], array_column(Sign::cases(), 'value'));
    }

    #[TestWith(['PRAGMA cache_size=100', Sign::Unsigned])]
    #[TestWith(['PRAGMA cache_size=+100', Sign::Positive])]
    #[TestWith(['PRAGMA main.cache_size(-100)', Sign::Negative])]
    public function testClassifiesTheWrittenSign(string $sql, Sign $sign): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind($sql);
        self::assertInstanceOf(AssignPragmaStatement::class, $statement);
        self::assertInstanceOf(NumericArgument::class, $statement->value);
        self::assertSame($sign, $statement->value->sign);
        self::assertSame('100', $statement->value->literal->text);
    }
}
