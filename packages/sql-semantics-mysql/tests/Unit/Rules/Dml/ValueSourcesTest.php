<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\ValueSources;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(ValueSources::class)]
#[Medium]
final class ValueSourcesTest extends TestCase
{
    public function testValuesSeparatesWrappersFromSetOperands(): void
    {
        $values = new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')])]);
        $wrapped = new M\Query\ParenthesizedQuery($values);
        self::assertSame($values, (new ValueSources())->values($wrapped));
        self::assertNull((new ValueSources())->values(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))])));
    }
    public function testDeferredKeepsSyntaxWarningsWithoutContextualizingCalls(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t(id INT)')->declarations();
        $operation = $semantics->analyze('INSERT INTO t VALUES ROW(1) ORDER BY FOUND_ROWS(), CAST(1 AS CHAR CHARACTER SET utf8)', $tables);
        self::assertCount(1, $operation->warnings());
        self::assertInstanceOf(M\Notice\Deprecation::class, $operation->warnings()[0]);
        self::assertSame(M\Notice\Deprecated::Utf8Alias, $operation->warnings()[0]->construct);
    }


    public function testHasDestinationSeparatesInsertRowsFromStatementOutput(): void
    {
        $values = new M\Query\ValuesQuery([new M\Query\ValueRow([])]);
        $query = new M\Query\QueryStatement($values, into: new M\Query\Into\IntoDumpfile(new M\Literal\Text('f')), intoPosition: M\Query\Into\IntoPosition::AfterQuery);
        self::assertTrue((new ValueSources())->hasDestination(new M\Query\ParenthesizedQuery($query)));
        self::assertFalse((new ValueSources())->hasDestination($values));
        $into = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))));
        $this->expectExceptionMessage('An INSERT source supplies rows without an INTO result destination.');
        new M\Dml\Insert\InsertQuery($into, $query);
    }

}
