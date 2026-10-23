<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(StorageOptions::class)]
#[Medium]
final class StorageOptionsTest extends TestCase
{
    public function testHeaderRequiresASelectedDatabaseBeforeResolvingEngines(): void
    {
        $session = (new Instance())->connect();
        $header = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [], [], 0, false, new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2));
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $this->expectExceptionCode(1046);
        (new StorageOptions())->header($header, $session, $context);
    }

    public function testAlterWarnsOncePerDirectoryBeforeOpeningTheTarget(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("ALTER TABLE d.absent INDEX DIRECTORY 'i', DATA DIRECTORY 'd', INDEX DIRECTORY 'again'");
        self::assertSame([['Warning', 1618, '<DATA DIRECTORY> option ignored'], ['Warning', 1618, '<INDEX DIRECTORY> option ignored'], ['Error', 1146, "Table 'd.absent' doesn't exist"]], $session->diagnostics->conditions);
        $session->run("ALTER TABLE absent INDEX DIRECTORY 'i'");
        self::assertSame([['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testNamedRejectsAnUnknownEngineWhenSubstitutionIsDisabled(): void
    {
        $session = (new Instance())->connect();
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $this->expectExceptionCode(1286);
        (new StorageOptions())->named('missing', $context);
    }

    public function testResolveChecksPartitionEnginesBeforeOpeningTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run('ALTER TABLE d.absent ADD PARTITION (PARTITION p ENGINE missing)');

        self::assertSame([['Error', 1286, "Unknown storage engine 'missing'"]], $session->diagnostics->conditions);
    }

    public function testResolveReportsEveryUnknownEngineBeforeTheFallback(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET sql_mode=''; CREATE TABLE d.t(a INT) ENGINE first ENGINE second PARTITION BY HASH(a) (PARTITION p ENGINE third)");

        self::assertSame([1286, 1286, 1286, 1266], array_column($session->diagnostics->conditions, 1));
        self::assertSame("Unknown storage engine 'first'", $session->diagnostics->conditions[0][2]);
    }

    public function testResolveNeedsTheCurrentDatabaseBeforeEngineLookup(): void
    {
        $session = (new Instance())->connect();
        $session->run('CREATE TABLE t(a INT) ENGINE missing');

        self::assertSame([['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testCheckReportsLegacyTablespaceAndEngineErrorsInOrder(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->run('CREATE TABLE d.t(a INT) TABLESPACE absent');

        self::assertSame([['Error', 1812, 'InnoDB: A general tablespace named `absent` cannot be found.'], ['Error', 1031, "Table storage engine for 't' doesn't have this option"]], $session->diagnostics->conditions);
    }

    public function testTablespaceLookupPrecedesAnExistingModernTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(1)');
        $session->run('CREATE TABLE IF NOT EXISTS d.t TABLESPACE absent');
        self::assertSame([['Error', 3510, "Tablespace absent doesn't exist."]], $session->diagnostics->conditions);
    }

    public function testEngineUsesTheConfiguredDefaultAndResolvesAliases(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; SET default_storage_engine=MEMORY; CREATE TABLE a(c INT); CREATE TABLE b(c INT) ENGINE=HEAP');

        self::assertNotNull($session->instance->dictionary->table('d', 'a'));
        self::assertNotNull($session->instance->dictionary->table('d', 'b'));
        self::assertSame('MEMORY', $session->instance->dictionary->table('d', 'a')->definition->engine);
        self::assertSame('MEMORY', $session->instance->dictionary->table('d', 'b')->definition->engine);
    }

    public function testCheckRefusesAnUnknownEngineUnderNoEngineSubstitution(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run('CREATE TABLE d.t(c INT) ENGINE=absent');

        self::assertSame([['Error', 1286, "Unknown storage engine 'absent'"]], $session->diagnostics->conditions);
    }

    public function testEngineReportsBothSubstitutionWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET sql_mode=''; CREATE TABLE d.t(c INT) ENGINE=absent");

        self::assertSame([['Warning', 1286, "Unknown storage engine 'absent'"], ['Warning', 1266, "Using storage engine InnoDB for table 't'"]], $session->diagnostics->conditions);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
        self::assertSame('InnoDB', $session->instance->dictionary->table('d', 't')->definition->engine);
    }

    public function testAttributesRefusesPrimaryEngineAttributesAfterJsonValidation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("CREATE TABLE d.t(c INT ENGINE_ATTRIBUTE '{}')");

        self::assertSame([['Error', 3981, "Storage engine 'InnoDB' does not support ENGINE_ATTRIBUTE."]], $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerDirectories(): iterable
    {
        yield 'legacy' => ['5.6.51', 1103];
        yield 'modern' => ['8.4.7', 1525];
    }

    #[DataProvider('providerDirectories')]
    public function testDirectoriesPrecedeMissingColumnsAndDestinationSchemas(string $release, int $code): void
    {
        $session = (new Instance($release))->connect();
        $create = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('absent')), options: [new \SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind::DataDirectory, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('text'))]);
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode($code);
        (new StorageOptions())->directories($create, $session);
    }

    public function testBeforePartitionUsesTheOwningStoredInstructionAndPreservesSyntaxWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $session->run('CREATE PROCEDURE p() CREATE TABLE t(a INT) ENGINE missing PARTITION BY HASH(!USER())');
        self::assertSame([1287, 1286], array_column($session->diagnostics->conditions, 1));
        $session->run("SET sql_mode=''");
        $session->run('ALTER TABLE absent ENGINE missing PARTITION BY HASH(USER())');
        self::assertSame([1286, 1064], array_column($session->diagnostics->conditions, 1));
    }

}
