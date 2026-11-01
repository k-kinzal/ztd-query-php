<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\RefusedDefinitionCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefusedDefinitionCommand::class)]
#[Medium]
final class RefusedDefinitionCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerOrder(): iterable
    {
        yield '8.4 opens the tables the query reads first' => ['8.4.7', 'CREATE TABLE t (a INT, a INT) AS SELECT * FROM absent', 1146];
        yield '8.4 opens the tables a functional key reads before the database' => ['8.4.7', 'CREATE TABLE nodb.t (a INT, INDEX i (((TABLE absent))))', 1146];
        yield '8.4 checks the engine before the columns' => ['8.4.7', 'CREATE TABLE t (a INT, a INT) ENGINE = nosuch', 1286];
        yield '8.4 checks the database before the columns' => ['8.4.7', 'CREATE TABLE nodb.t (a INT, a INT)', 1049];
        yield '8.4 reports an existing table before the columns' => ['8.4.7', 'CREATE TABLE present (a INT, a INT)', 1050];
        yield '8.4 reports an empty definition after the table' => ['8.4.7', 'CREATE TABLE t (PRIMARY KEY (a))', 1072];
        yield '8.4 checks functional keys before ordinary key columns' => ['8.4.7', 'CREATE TABLE t (a INT, INDEX i (missing, ((TABLE present))))', 3758];
        yield '8.4 reports the written problem last' => ['8.4.7', 'CREATE TABLE t (a INT, a INT)', 1060];
        yield '9.1 refuses an empty definition before the existing table' => ['9.1.0', 'CREATE TABLE IF NOT EXISTS present (PRIMARY KEY (a))', 4028];
        yield '5.7 reports the written problem before an existing table' => ['5.7.44', 'CREATE TABLE present (a INT, a INT)', 1060];
        yield '5.7 reports the written problem before an unknown database' => ['5.7.44', 'CREATE TABLE nodb.t (a INT PRIMARY KEY, b INT PRIMARY KEY)', 1068];
        yield '5.7 reports an empty definition before the keys' => ['5.7.44', 'CREATE TABLE t (PRIMARY KEY (a))', 1113];
        yield '5.7 opens the tables the query reads first' => ['5.7.44', 'CREATE TABLE t (a INT, a INT) AS SELECT * FROM absent', 1146];
        yield '5.6 refuses a spatial key of InnoDB before its arity' => ['5.6.51', 'CREATE TABLE t (a POINT NOT NULL, SPATIAL INDEX (a, missing))', 1464];
        yield '5.7 reports wrong arguments for a spatial key of several parts on MyISAM' => ['5.7.44', 'CREATE TABLE t (a POINT NOT NULL, b POINT NOT NULL, SPATIAL INDEX (a, b)) ENGINE = MyISAM', 1070];
        yield 'a repeated partition name precedes an existing table on 8.4' => ['8.4.7', 'CREATE TABLE present (a INT, a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (1), PARTITION p0 VALUES LESS THAN (2))', 1517];
        yield 'a repeated partition name precedes IF NOT EXISTS on 8.4' => ['8.4.7', 'CREATE TABLE IF NOT EXISTS present (a INT, a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (1), PARTITION p0 VALUES LESS THAN (2))', 1517];
        yield 'an unknown database precedes a repeated partition name on 8.4' => ['8.4.7', 'CREATE TABLE nodb.t (a INT, a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (1), PARTITION p0 VALUES LESS THAN (2))', 1049];
        yield 'a repeated partition name precedes an unknown database on 5.7' => ['5.7.44', 'CREATE TABLE nodb.t (a INT, a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (1), PARTITION p0 VALUES LESS THAN (2))', 1517];
        yield 'a MAXVALUE bound that is not last precedes a duplicate column on 8.4' => ['8.4.7', 'CREATE TABLE t (a INT, a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN MAXVALUE, PARTITION p1 VALUES LESS THAN (1))', 1481];
        yield 'a temporary partitioned table precedes a duplicate column on 5.7' => ['5.7.44', 'CREATE TEMPORARY TABLE t (a INT, a INT) PARTITION BY HASH(a)', 1562];
        yield 'mixed subpartitioning precedes a duplicate column on 8.4' => ['8.4.7', 'CREATE TABLE t (a INT, a INT) PARTITION BY HASH(a) SUBPARTITION BY HASH(a) SUBPARTITIONS 2', 1500];
        yield 'a constant partition function precedes a duplicate column on 8.4' => ['8.4.7', 'CREATE TABLE t (a INT, a INT) PARTITION BY HASH((USER()))', 1064];
    }

    #[DataProvider('providerOrder')]
    public function testExecuteReportsTheConditionsInTheServerOrder(string $version, string $sql, int $code): void
    {
        $session = (new Instance($version, [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE present (a INT)');
        $reply = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($code, $reply->getCode());
    }

    public function testExecuteCompletesWhenIfNotExistsFindsTheTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE present (a INT)');
        $reply = $session->run('CREATE TABLE IF NOT EXISTS present (a INT, a INT)')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([['Note', 1050, "Table 'present' already exists"]], $session->diagnostics->conditions);
    }

    public function testExecuteDefersTheWrittenProblemOfAPreparedDefinition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $prepared = $session->run("PREPARE s FROM 'CREATE TABLE t (a INT, a INT)'")[0];
        $executed = $session->run('EXECUTE s')[0];

        self::assertInstanceOf(Completion::class, $prepared);
        self::assertInstanceOf(SqlError::class, $executed);
        self::assertSame(1060, $executed->getCode());
    }

    public function testExecuteRunsAStoredDefinitionTheReleaseAccepts(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
        $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [$column]);

        self::assertInstanceOf(Completion::class, (new RefusedDefinitionCommand())->execute($definition, $session));
        self::assertNotNull($session->instance->dictionary->schema('d')?->table('t'));
    }

    public function testFunctionalRefusesAFunctionalPrimaryKeyBeforeAForbiddenExpression(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $analysis = $session->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, PRIMARY KEY (((SELECT 1))))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $analysis);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable::class, $analysis->body);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3756);

        (new RefusedDefinitionCommand())->functional($analysis->body);
    }

    public function testFactsResolveTheTablesAStoredDefinitionReads(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $analysis = $session->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, a INT) AS SELECT * FROM absent');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $analysis);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable::class, $analysis->body);
        $missing = array_filter((new RefusedDefinitionCommand())->facts($analysis->body, $session)->diagnostics, static fn ($diagnostic): bool => $diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable);

        self::assertCount(1, $missing);
    }

    public function testOperationBindsTheExecutableStatementOfAStoredDefinition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
        $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [$column]);
        $operation = (new RefusedDefinitionCommand())->operation($definition, $session);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $operation);
        self::assertSame('CREATE TABLE t (a INT)', $operation->toString());
    }

    public function testSpatialReportsTheEngineBeforeTheArity(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $analysis = $session->analyze('CREATE PROCEDURE p() CREATE TABLE t (a POINT NOT NULL, b POINT NOT NULL, SPATIAL INDEX (a, b))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $analysis);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable::class, $analysis->body);

        self::assertSame(1464, (new RefusedDefinitionCommand())->spatial($analysis->body, $session)->getCode());
        self::assertSame(1070, (new RefusedDefinitionCommand())->spatial($analysis->body, (new Instance('8.4.7', [], ['d']))->connect(database: 'd'))->getCode());
    }
}
