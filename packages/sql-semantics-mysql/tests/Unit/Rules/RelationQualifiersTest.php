<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\RelationQualifiers;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(RelationQualifiers::class)]
#[Medium]
final class RelationQualifiersTest extends TestCase
{
    public function testAdmitsTheDatabaseOfTheTableEvenUnderAnAlias(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('d'));
        $t = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertSame([[], [], 1], [$semantics->analyze('SELECT d.t.a FROM t', [$t])->facts->diagnostics, $semantics->analyze('SELECT d.x.a FROM t AS x', [$t])->facts->diagnostics, count($semantics->analyze('SELECT other.t.a FROM t', [$t])->facts->diagnostics)]);
    }

    public function testAdmitsAnyDatabaseForADerivedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('d'));

        self::assertSame([], $semantics->analyze('SELECT other.x.a FROM (SELECT 1 AS a) AS x', [])->facts->diagnostics);
    }

    public function testNarrowedKeepsTheRelationsOfTheDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('d'));
        $t = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertInstanceOf(MissingColumn::class, $semantics->analyze('SELECT other.t.a FROM t', [$t])->facts->diagnostics[0]);
    }
}
