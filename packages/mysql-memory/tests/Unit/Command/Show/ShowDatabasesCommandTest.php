<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\ShowDatabasesCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowDatabasesCommand::class)]
#[Small]
final class ShowDatabasesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowDatabasesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheDatabasesInNameOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE shop; CREATE DATABASE Archive');
        $all = $session->query('SHOW DATABASES')[0];
        $like = $session->query("SHOW SCHEMAS LIKE 'sh%'")[0];
        $cased = $session->query("SHOW DATABASES LIKE 'SHOP'")[0];

        self::assertInstanceOf(ResultSet::class, $all);
        self::assertInstanceOf(ResultSet::class, $like);
        self::assertInstanceOf(ResultSet::class, $cased);
        self::assertSame([['Archive'], ['information_schema'], ['mysql'], ['performance_schema'], ['shop'], ['sys']], $all->rows);
        self::assertSame(['Database', 256, 'SCHEMATA'], [$all->columns[0]->name, $all->columns[0]->length, $all->columns[0]->table]);
        self::assertSame(['Database (sh%)', [['shop']]], [$like->columns[0]->name, $like->rows]);
        self::assertSame([], $cased->rows);
    }

    public function testExecuteListsInformationSchemaFirstInMySql57(): void
    {
        $s = (new Instance('5.7.44'))->connect();
        $s->query('CREATE DATABASE a');

        $read1 = $s->query('SHOW DATABASES')[0];
        self::assertInstanceOf(ResultSet::class, $read1);
        self::assertSame([['information_schema'], ['a'], ['mysql'], ['performance_schema'], ['sys']], $read1->rows);
    }
}
