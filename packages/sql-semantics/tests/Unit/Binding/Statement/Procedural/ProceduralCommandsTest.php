<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\ProceduralCommands;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProceduralCommands::class)]
#[Medium]
final class ProceduralCommandsTest extends TestCase
{
    public function testBindRoutesEachProceduralForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
        self::assertSame('HELP', $binder->bind("HELP 'x'")->kind->value);
        self::assertSame('IMPORT', $binder->bind("IMPORT TABLE FROM 'x'")->kind->value);
        self::assertSame('LOCK', $binder->bind('LOCK INSTANCE FOR BACKUP')->kind->value);
    }

    public function testBindLeavesOtherDialectsToTheirBinders(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('LOCK TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Locking\LockRelationsStatement::class, $statement);
    }

    #[TestWith(['mysql-5.6.51', 'CALL p()', \SqlSemantics\Model\Statement\Procedural\CallStatement::class, 'CALL `p`()'])]
    #[TestWith(['mysql-5.6.51', 'DESCRIBE t', \SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement::class, 'DESCRIBE `t`'])]
    #[TestWith(['mysql-5.6.51', 'SIGNAL SQLSTATE \'45000\'', \SqlSemantics\Model\Statement\Procedural\SignalStatement::class, 'SIGNAL SQLSTATE \'45000\''])]
    #[TestWith(['mysql-5.6.51', 'RESIGNAL', \SqlSemantics\Model\Statement\Procedural\ResignalStatement::class, 'RESIGNAL'])]
    #[TestWith(['mysql-5.6.51', 'GET DIAGNOSTICS @a = NUMBER', \SqlSemantics\Model\Statement\Procedural\GetDiagnosticsStatement::class, 'GET CURRENT DIAGNOSTICS @`a` = NUMBER'])]
    #[TestWith(['mysql-5.6.51', 'HANDLER t OPEN', \SqlSemantics\Model\Statement\Cursor\Handler\OpenHandlerStatement::class, 'HANDLER `t` OPEN'])]
    #[TestWith(['mysql-5.6.51', 'LOAD DATA INFILE \'f\' INTO TABLE t', \SqlSemantics\Model\Statement\Loading\LoadFileStatement::class, 'LOAD DATA INFILE \'f\' INTO TABLE `t`'])]
    #[TestWith(['mysql-8.4.7', 'CALL p()', \SqlSemantics\Model\Statement\Procedural\CallStatement::class, 'CALL `p`()'])]
    #[TestWith(['mysql-8.4.7', 'DESCRIBE t', \SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement::class, 'DESCRIBE `t`'])]
    #[TestWith(['mysql-8.4.7', 'SIGNAL SQLSTATE \'45000\'', \SqlSemantics\Model\Statement\Procedural\SignalStatement::class, 'SIGNAL SQLSTATE \'45000\''])]
    #[TestWith(['mysql-8.4.7', 'RESIGNAL', \SqlSemantics\Model\Statement\Procedural\ResignalStatement::class, 'RESIGNAL'])]
    #[TestWith(['mysql-8.4.7', 'GET DIAGNOSTICS @a = NUMBER', \SqlSemantics\Model\Statement\Procedural\GetDiagnosticsStatement::class, 'GET CURRENT DIAGNOSTICS @`a` = NUMBER'])]
    #[TestWith(['mysql-8.4.7', 'HANDLER t OPEN', \SqlSemantics\Model\Statement\Cursor\Handler\OpenHandlerStatement::class, 'HANDLER `t` OPEN'])]
    #[TestWith(['mysql-8.4.7', 'LOAD DATA INFILE \'f\' INTO TABLE t', \SqlSemantics\Model\Statement\Loading\LoadFileStatement::class, 'LOAD DATA INFILE \'f\' INTO TABLE `t`'])]
    #[TestWith(['mysql-8.4.7', 'LOCK INSTANCE FOR BACKUP', \SqlSemantics\Model\Statement\Locking\LockInstanceStatement::class, 'LOCK INSTANCE FOR BACKUP'])]
    #[TestWith(['mysql-8.4.7', 'UNLOCK INSTANCE', \SqlSemantics\Model\Statement\Locking\UnlockInstanceStatement::class, 'UNLOCK INSTANCE'])]
    #[TestWith(['mysql-8.4.7', 'CREATE RESOURCE GROUP g TYPE = USER', \SqlSemantics\Model\Statement\Server\ResourceGroup\CreateResourceGroupStatement::class, 'CREATE RESOURCE GROUP `g` TYPE = USER ENABLE'])]
    #[TestWith(['mysql-8.4.7', 'ALTER RESOURCE GROUP g ENABLE', \SqlSemantics\Model\Statement\Server\ResourceGroup\AlterResourceGroupStatement::class, 'ALTER RESOURCE GROUP `g` ENABLE'])]
    #[TestWith(['mysql-8.4.7', 'SET RESOURCE GROUP g', \SqlSemantics\Model\Statement\Server\ResourceGroup\SetResourceGroupStatement::class, 'SET RESOURCE GROUP `g`'])]
    public function testBindRoutesEveryProceduralCommand(string $version, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
