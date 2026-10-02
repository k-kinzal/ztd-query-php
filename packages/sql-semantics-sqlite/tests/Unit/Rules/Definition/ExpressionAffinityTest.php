<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ExpressionAffinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;

#[CoversClass(ExpressionAffinity::class)]
#[Medium]
final class ExpressionAffinityTest extends TestCase
{
    public function testColumnFindsTheDeclaredColumnBehindParenthesesAndCollations(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER)');
        $query = $semantics->analyze('SELECT (a) COLLATE nocase, b + 1, 1 FROM t', [$table]);
        $rule = new ExpressionAffinity();

        self::assertSame($table->declarations()[0]->columns[0], $rule->column($query->field(0)->expression, $query->facts));
        self::assertNull($rule->column($query->field(1)->expression, $query->facts));
        self::assertNull($rule->column(null, $query->facts));
    }

    public function testOfIsTheAffinityOfAColumnOrACastAndNothingElse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER)');
        $query = $semantics->analyze('SELECT a, (b), CAST(a AS REAL), CAST(a AS NUMERIC), a || b, 1 FROM t', [$table]);
        $rule = new ExpressionAffinity();

        self::assertSame(Affinity::Text, $rule->of($query->field(0)->expression, $query->facts));
        self::assertSame(Affinity::Integer, $rule->of($query->field(1)->expression, $query->facts));
        self::assertSame(Affinity::Real, $rule->of($query->field(2)->expression, $query->facts));
        self::assertSame(Affinity::Numeric, $rule->of($query->field(3)->expression, $query->facts));
        self::assertNull($rule->of($query->field(4)->expression, $query->facts));
        self::assertNull($rule->of($query->field(5)->expression, $query->facts));
        self::assertNull($rule->of(null, $query->facts));
    }
}
