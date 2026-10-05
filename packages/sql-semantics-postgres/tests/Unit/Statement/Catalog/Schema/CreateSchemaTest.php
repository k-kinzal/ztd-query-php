<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema::class)]
#[Medium]
final class CreateSchemaTest extends TestCase
{
    public function testRenderWritesTheAuthorization(): void
    {
        self::assertSame('CREATE SCHEMA IF NOT EXISTS AUTHORIZATION joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA IF NOT EXISTS AUTHORIZATION joe')->toString());
    }

    public function testRenderWritesTheElements(): void
    {
        self::assertSame('CREATE SCHEMA s CREATE TABLE t (a int4) CREATE VIEW v AS SELECT 1', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA s CREATE TABLE t (a int4) CREATE VIEW v AS SELECT 1')->toString());
    }

    public function testDeriveStatementReportsElementsWithIfNotExists(): void
    {
        self::assertSame('CREATE SCHEMA IF NOT EXISTS cannot include schema elements', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA IF NOT EXISTS s CREATE TABLE t (a int4)')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsAReservedName(): void
    {
        self::assertSame('unacceptable schema name "pg_app"', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA pg_app')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsAnElementOfAnotherSchema(): void
    {
        self::assertSame('CREATE specifies a schema (x) different from the one being created (s)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA s CREATE TABLE x.t (a int4)')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementDeclaresInTheNewSchema(): void
    {
        self::assertSame('s', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA s CREATE TABLE t (a int4)')->declarations()[0]->name->schema?->value);
    }

    public function testDeriveStatementReadsTheElementsWithTheNewSchemaSearchedFirst(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE s.t (x int4)', [])->declarations()[0], $semantics->analyze('CREATE TABLE public.t (y int4)', [])->declarations()[0]];
        self::assertSame(['x', 'y'], [
            $semantics->analyze('CREATE SCHEMA s CREATE VIEW v AS SELECT * FROM t', $context)->declarations()[0]->columns[0]->name->value,
            $semantics->analyze('CREATE VIEW v AS SELECT * FROM t', $context)->declarations()[0]->columns[0]->name->value,
        ]);
    }

    public function testDeriveStatementLetsAnElementSeeTheRelationsOfEarlierSteps(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA s CREATE VIEW v AS SELECT a FROM x CREATE TABLE x (a int4)', []);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame(['x', 'v'], array_map(static fn (\SqlSemantics\Statement\Declaration\Table $table): string => $table->name->name->value, $operation->declarations()));
        self::assertTrue($operation->declarations()[1]->complete);
    }

    public function testDeriveStatementRunsViewsInTheOrderWritten(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA s CREATE VIEW v AS SELECT a FROM w CREATE VIEW w AS SELECT 1 AS a', []);
        self::assertSame('Relation w does not exist.', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementDeclaresNothingForASessionOwner(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA AUTHORIZATION CURRENT_USER CREATE TABLE t (a int4)')->declarations());
    }

    public function testRejectsASchemaWithoutNameOrOwner(): void
    {
        $this->expectExceptionMessage('A schema is named or owned.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema(null);
    }
}
