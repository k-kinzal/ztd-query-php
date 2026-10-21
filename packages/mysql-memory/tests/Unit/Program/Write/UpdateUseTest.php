<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Write;

use MySqlMemory\Program\Write\UpdateUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateUse::class)]
#[Small]
final class UpdateUseTest extends TestCase
{
    public function testErrorOpensTheOriginalTableBeforeTheColumnContradiction(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze("CREATE PROCEDURE p() UPDATE t,JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET t.a=2");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        $write = $operation->statement->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate::class, $write);
        $session->text = 'unrelated invalid input';
        self::assertSame(1146, (new UpdateUse())->error($write, $session)->getCode());
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated invalid input';
        self::assertSame(1060, (new UpdateUse())->error($write, $session)->getCode());
    }

    public function testNamesUsesNestedCollisionIdentitiesBeforeOpeningOuterTables(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = $session->analyze('CREATE PROCEDURE p() UPDATE t,(SELECT 1 FROM u,u) q SET t.a=(1,2)')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $definition);
        $write = $definition->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate::class, $write);
        $session->text = 'opaque non-SQL label';
        self::assertSame("Not unique table/alias: 'u'", (new UpdateUse())->error($write, $session)->getMessage());
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT); INSERT INTO t VALUES(1); INSERT INTO u VALUES(2)');
        $session->text = 'opaque non-SQL label';
        self::assertSame("Not unique table/alias: 'u'", (new UpdateUse())->error($write, $session)->getMessage());
        self::assertSame('opaque non-SQL label', $session->text);
    }

    public function testRaiseKeepsAnUnreachedInstructionDormant(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("CREATE PROCEDURE p() BEGIN IF @run THEN UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=2; END IF; END");
        self::assertNotEmpty($session->query('SET @run=0; CALL p()'));
        $session->query('SET @run=1');
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1060);
        $session->query('CALL p()');
    }
    public function testNamesKeepsTheSameCollisionAcrossDatabaseAndTableCreation(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51'))->connect();
        $first = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $write = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate(null, false, false, [$first, new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))], [new \SqlSemantics\Platform\MySql\Statement\Dml\Assignment(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        self::assertSame(1046, (new UpdateUse())->error($write, $session)->getCode());
        $session->query('CREATE DATABASE d; USE d');
        self::assertSame(1066, (new UpdateUse())->error($write, $session)->getCode());
        $session->query('CREATE TABLE t(a INT)');
        self::assertSame(1066, (new UpdateUse())->error($write, $session)->getCode());
    }


    public function testCheckBindsPreparedValuesBeforeJoinConditionsInMySql57(): void
    {
        $session = (new \MySqlMemory\Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $reply = $session->run("PREPARE st FROM 'UPDATE t JOIN u ON missing SET t.a=NOW(9)'");
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1426, $reply[0]->getCode());
        self::assertSame([1426], array_column($session->diagnostics->conditions, 1));
    }

}
