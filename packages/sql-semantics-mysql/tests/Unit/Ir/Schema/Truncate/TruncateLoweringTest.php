<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Truncate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(TruncateLowering::class)]
#[Medium]
final class TruncateLoweringTest extends TestCase
{
    public function testLowerBindsTheCurrentDatabaseBeforePublication(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('logs'));
        $declarations = $semantics->context(['CREATE TABLE logs.today (id int)', 'CREATE TABLE archive.today (id int)']);
        $table = $semantics->analyze('TRUNCATE today', $declarations);

        self::assertInstanceOf(TruncateTable::class, $table);
        self::assertSame('logs', $table->target->name->schema->value);
        self::assertSame('today', $table->target->name->table->value);
    }

    public function testLowerRetainsTheSessionTargetWhenATableShadowsTheSameName(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('logs'));
        $declarations = $semantics->context(['CREATE TABLE logs.today (id int)', 'CREATE TEMPORARY TABLE logs.today (id int)']);
        $table = $semantics->analyze('TRUNCATE today', $declarations);

        self::assertInstanceOf(TruncateTable::class, $table);
        self::assertSame(ExternalRelationKind::TemporaryTable, $table->target->kind);
    }

    public function testReferrersDoNotCrossTheExactDatabaseBoundary(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context([
            'CREATE TABLE first_db.parent (id INT PRIMARY KEY)',
            'CREATE TABLE second_db.parent (id INT PRIMARY KEY)',
            'CREATE TABLE second_db.child (pid INT, FOREIGN KEY (pid) REFERENCES parent(id))',
        ]);
        $result = $semantics->analyze('TRUNCATE first_db.parent', $catalog);
        self::assertInstanceOf(TruncateTable::class, $result);

        self::assertInstanceOf(AnalysisContext::class, $catalog);
        self::assertSame([], (new TruncateLowering())->referrers($catalog, $result->target));
    }
}
