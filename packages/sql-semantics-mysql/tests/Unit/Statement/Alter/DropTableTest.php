<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(DropTable::class)]
#[Medium]
final class DropTableTest extends TestCase
{
    public function testDeriveStatementResolvesEveryTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $drop = $semantics->analyze('DROP TABLE t, u', [$table]);

        self::assertInstanceOf(DropTable::class, $drop);
        self::assertInstanceOf(DeclaredTable::class, $drop->tables[0]->relationInformation()->table);
        self::assertInstanceOf(MissingTable::class, $drop->tables[1]->relationInformation()->table);
    }

    public function testRenderWritesEveryPart(): void
    {
        self::assertSame('DROP TEMPORARY TABLES IF EXISTS t RESTRICT', (new Semantics(Dialect::MySql))->analyze('drop temporary tables if exists t restrict')->toString());
    }

    public function testRenderPreservesRepeatedTableIdentities(): void
    {
        $drop = new DropTable(false, false, [
            new \SqlSemantics\Platform\MySql\Statement\Alter\TargetTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))),
            new \SqlSemantics\Platform\MySql\Statement\Alter\TargetTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))),
        ]);
        $published = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql))->context(), $drop);
        self::assertSame('DROP TABLE t, t', $published->toString());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerReleases')]
    public function testDeriveStatementRetainsARepeatedTargetAsAnOrderedCondition(string $version): void
    {
        $drop = (new Semantics(Dialect::MySql, 'mysql-' . $version))->analyze('DROP TEMPORARY TABLES t, t');
        self::assertInstanceOf(DropTable::class, $drop);
        self::assertCount(2, $drop->tables);
        self::assertSame('DROP TEMPORARY TABLES t, t', $drop->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $drop->warnings()[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable::class, $drop->warnings()[0]->problem);
        self::assertSame($drop->tables[1]->name, $drop->warnings()[0]->problem->name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version];
        }
    }

    public function testDeriveStatementRefusesAViewWithoutIfExists(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is a view: DROP TABLE reports it as an unknown table.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('DROP TABLE v', [$table, $view])->diagnostics()));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('DROP TABLE IF EXISTS v', [$table, $view])->diagnostics()));
    }
}
