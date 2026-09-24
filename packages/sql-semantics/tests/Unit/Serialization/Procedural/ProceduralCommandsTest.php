<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\ProceduralCommands;

#[CoversClass(ProceduralCommands::class)]
#[Medium]
final class ProceduralCommandsTest extends TestCase
{
    public function testWriteRoutesProceduralStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertSame('LOCK INSTANCE FOR BACKUP', ProceduralCommands::write($binder->bind('LOCK INSTANCE FOR BACKUP'))?->toString());
        self::assertNull(ProceduralCommands::write($binder->bind('SELECT 1')));
    }

    #[TestWith(['HANDLER t OPEN', 'HANDLER `t` OPEN'])]
    #[TestWith(['HANDLER t CLOSE', 'HANDLER `t` CLOSE'])]
    #[TestWith(['HANDLER t READ FIRST', 'HANDLER `t` READ FIRST'])]
    #[TestWith(['HANDLER t READ k NEXT', 'HANDLER `t` READ `k` NEXT'])]
    #[TestWith(['HANDLER t READ k = (1)', 'HANDLER `t` READ `k` = (1)'])]
    #[TestWith(['GET DIAGNOSTICS @n = NUMBER', 'GET CURRENT DIAGNOSTICS @`n` = NUMBER'])]
    #[TestWith(['GET DIAGNOSTICS CONDITION 1 @m = MESSAGE_TEXT', 'GET CURRENT DIAGNOSTICS CONDITION 1 @`m` = MESSAGE_TEXT'])]
    #[TestWith(["SIGNAL SQLSTATE '45000'", "SIGNAL SQLSTATE '45000'"])]
    #[TestWith(['RESIGNAL', 'RESIGNAL'])]
    #[TestWith(['DESCRIBE t', 'DESCRIBE `t`'])]
    #[TestWith(['CALL p()', 'CALL `p`()'])]
    #[TestWith(["LOAD DATA INFILE 'f' INTO TABLE t", "LOAD DATA INFILE 'f' INTO TABLE `t`"])]
    #[TestWith(["HELP 'x'", "HELP 'x'"])]
    #[TestWith(["IMPORT TABLE FROM 'f'", "IMPORT TABLE FROM 'f'"])]
    #[TestWith(['UNLOCK INSTANCE', 'UNLOCK INSTANCE'])]
    #[TestWith(['CREATE RESOURCE GROUP g TYPE = USER', 'CREATE RESOURCE GROUP `g` TYPE = USER ENABLE'])]
    #[TestWith(['ALTER RESOURCE GROUP g ENABLE', 'ALTER RESOURCE GROUP `g` ENABLE'])]
    #[TestWith(['SET RESOURCE GROUP g', 'SET RESOURCE GROUP `g`'])]
    public function testWriteRoutesEveryProceduralStatement(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))'));
        self::assertSame($expected, ProceduralCommands::write($binder->bind($sql, strict: false))?->toString());
    }

    public function testWriteRoutesABulkLoad(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-9.1.0'))->build('CREATE TABLE t(id INT)'));
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` ALGORITHM = BULK", ProceduralCommands::write($binder->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK"))?->toString());
    }
}
