<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(CreateTableLike::class)]
#[Medium]
final class CreateTableLikeTest extends TestCase
{
    public function testDeriveStatementCopiesTheSourceDeclaration(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $source = $semantics->analyze('CREATE TABLE s (a INT NOT NULL, b INT INVISIBLE)');
        $copy = $semantics->analyze('CREATE TABLE c LIKE s', [$source]);
        $table = $copy->declarations()[0];

        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $copy);
        self::assertInstanceOf(DeclaredTable::class, $copy->relationInformation()->table);
        self::assertSame(['a', 'b'], [$table->columns[0]->name->value, $table->implicit[0]->column->name->value]);
        self::assertNotSame($source->declarations()[0]->columns[0], $table->columns[0]);
    }

    public function testDeriveStatementLeavesAnUndeclaredSourceIncomplete(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE c LIKE s');

        self::assertFalse($create->declarations()[0]->complete);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerEnclosures')]
    public function testRenderRetainsTheLikeClauseEnclosure(string $version, bool $enclosed): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-' . $version);
        $sql = 'CREATE TABLE IF NOT EXISTS c ' . ($enclosed ? '(LIKE db.s)' : 'LIKE db.s');
        $request = $semantics->analyze($sql);
        self::assertInstanceOf(CreateTableLike::class, $request);
        self::assertSame($enclosed, $request->enclosed);
        self::assertSame($sql, $request->toString());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerEnclosures(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version . ' bare' => [$version, false];
            yield $version . ' enclosed' => [$version, true];
        }
    }

    public function testDeriveStatementRefusesAViewSource(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TABLE c LIKE v', [$table, $view])->diagnostics()));
    }
}
