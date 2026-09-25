<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Pragma;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Pragma\Argument;
use SqlSemantics\Model\Configuration\Pragma\IdentifierArgument;
use SqlSemantics\Model\Configuration\Pragma\NumericArgument;
use SqlSemantics\Model\Configuration\Pragma\TextArgument;
use SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Argument::class)]
#[Medium]
final class ArgumentTest extends TestCase
{
    /**
     * @param class-string<Argument> $class
     */
    #[TestWith(["PRAGMA journal_mode='wal'", TextArgument::class, "PRAGMA \"journal_mode\" = 'wal'"])]
    #[TestWith(['PRAGMA journal_mode=wal', IdentifierArgument::class, 'PRAGMA "journal_mode" = "wal"'])]
    #[TestWith(['PRAGMA main.cache_size(-2000)', NumericArgument::class, 'PRAGMA "main"."cache_size" = - 2000'])]
    public function testClassifiesEachScalarArgumentForm(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(AssignPragmaStatement::class, $statement);
        self::assertInstanceOf($class, $statement->value);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
