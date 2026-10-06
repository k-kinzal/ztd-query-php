<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;

#[CoversClass(ShowCreateTable::class)]
#[Medium]
final class ShowCreateTableTest extends TestCase
{
    public function testDeriveStatementLeavesTheShapeOpen(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TABLE t');
        self::assertInstanceOf(ShowCreateTable::class, $show->statement);
        $missing = (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TABLE t', []);
        self::assertSame('Relation t does not exist.', $missing->facts->diagnostics[0]->message());
        self::assertFalse($show->shape()?->complete());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE TABLE t', (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TABLE t')->toString());
    }

    public function testDeriveStatementAnswersTheColumnsOfTheDeclaredKind(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['Table', 'Create Table'], array_map(static fn ($slot): ?string => $slot->name?->value, $semantics->analyze('SHOW CREATE TABLE t', [$table, $view])->shape()->slots ?? []));
        self::assertSame(['View', 'Create View', 'character_set_client', 'collation_connection'], array_map(static fn ($slot): ?string => $slot->name?->value, $semantics->analyze('SHOW CREATE TABLE v', [$table, $view])->shape()->slots ?? []));
    }
}
