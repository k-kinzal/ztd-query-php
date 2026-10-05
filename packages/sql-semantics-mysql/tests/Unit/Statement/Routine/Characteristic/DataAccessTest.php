<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Characteristic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\DataAccess;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(DataAccess::class)]
#[Medium]
final class DataAccessTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, AccessLevel}>
     */
    public static function providerRenderWritesTheKeywordsOfTheLevel(): iterable
    {
        yield 'MySQL 5.6 CONTAINS SQL' => ['mysql-5.6.51', 'alter procedure p contains sql', 'ALTER PROCEDURE p CONTAINS SQL', AccessLevel::ContainsSql];
        yield 'MySQL 5.7 NO SQL' => ['mysql-5.7.44', 'alter function f no sql', 'ALTER FUNCTION f NO SQL', AccessLevel::NoSql];
        yield 'MySQL 8.0 READS SQL DATA' => ['mysql-8.0.44', 'alter procedure p reads sql data', 'ALTER PROCEDURE p READS SQL DATA', AccessLevel::ReadsSqlData];
        yield 'MySQL 9.1 MODIFIES SQL DATA' => ['mysql-9.1.0', 'alter function f modifies sql data', 'ALTER FUNCTION f MODIFIES SQL DATA', AccessLevel::ModifiesSqlData];
    }

    #[DataProvider('providerRenderWritesTheKeywordsOfTheLevel')]
    public function testRenderWritesTheKeywordsOfTheLevel(string $release, string $sql, string $expected, AccessLevel $level): void
    {
        $alter = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);
        $characteristic = $statement->characteristics[0] ?? null;
        self::assertInstanceOf(DataAccess::class, $characteristic);

        self::assertSame($level, $characteristic->level);
        self::assertSame($expected, $alter->toString());
    }

    public function testRenderKeepsEveryWrittenLevelInOrder(): void
    {
        $sql = 'CREATE PROCEDURE p() NO SQL MODIFIES SQL DATA CONTAINS SQL SELECT 1';

        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze(strtolower($sql))->toString());
    }

    public function testRenderWritesAConstructedCharacteristic(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new DataAccess(AccessLevel::ModifiesSqlData))->render($out);

        self::assertSame('MODIFIES SQL DATA', (new Lexical())->join($out->pieces()));
    }
}
