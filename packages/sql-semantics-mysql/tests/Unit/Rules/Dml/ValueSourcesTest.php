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
        self::assertCount(1, $operation->facts->warnings);
        self::assertInstanceOf(M\Notice\Deprecation::class, $operation->facts->warnings[0]);
        self::assertSame(M\Notice\Deprecated::Utf8Alias, $operation->facts->warnings[0]->construct);
    }

}
