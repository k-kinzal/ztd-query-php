<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions::class)]
#[Medium]
final class DefinitionsTest extends TestCase
{
    public function testDeriveProvidesTheDeclaration(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (x int CHECK (x > 0))', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $statement->facts->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $n2);
        self::assertSame(true, $n2->table === $statement->declarations()[0]);
    }

    public function testFactDoesNotProvideTheDeclaration(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (x int)', []);
        $derivation = new \SqlSemantics\Construction\Derivation($statement->context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        self::assertSame('1 0', count((new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions())->fact($n1, $derivation, null)->shape->slots) . ' ' . count($derivation->facts()->declarations));
    }

    public function testNameIsTemporaryForATemporaryTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TEMP TABLE n (x int)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        self::assertSame('pg_temp', (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions())->name($n1, null)->schema?->value);
    }

    public function testElementsResolvesTheParents(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n () INHERITS (t, zz)', $context);
        self::assertSame(1, count($statement->facts->diagnostics));
    }

    public function testReportReportsRepeatedAndSystemColumnNames(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int, a int, xmin int, b serial[], PRIMARY KEY (a), PRIMARY KEY (b))', []);
        self::assertSame([
          0 => 'column "a" specified more than once',
          1 => 'column name "xmin" conflicts with a system column name',
          2 => 'array of serial is not implemented',
          3 => 'multiple primary keys for table "n" are not allowed',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}
