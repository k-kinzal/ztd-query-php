<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\ServerInspection;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ServerInspection::class)]
#[Medium]
final class ServerInspectionTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindNormalizesStorageEngineSynonymsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('SHOW /* layout */ STORAGE ENGINES');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Inspection\ShowEnginesStatement::class, $statement);
        self::assertSame('SHOW ENGINES', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertCount(6, $statement->resultColumns());
        $copy = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Inspection\ShowEnginesStatement::class, $copy);
        self::assertSame(array_column($statement->resultColumns(), 'name'), array_column($copy->resultColumns(), 'name'));
    }

    #[TestWith(['show plugins', 'SHOW PLUGINS'])]
    #[TestWith(['show privileges', 'SHOW PRIVILEGES'])]
    #[TestWith(['show processlist', 'SHOW PROCESSLIST'])]
    #[TestWith(['show full processlist', 'SHOW FULL PROCESSLIST'])]
    public function testBindReadsEachServerListing(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql)));
    }
}
