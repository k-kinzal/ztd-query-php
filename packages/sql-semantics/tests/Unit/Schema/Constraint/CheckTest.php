<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Schema\Constraint\Check;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Check::class)]
#[Medium]
final class CheckTest extends TestCase
{
    public function testLocalColumnsAreEmptyBecauseAPredicateConstrainsTheWholeRow(): void
    {
        $predicate = Expression::binary('>', Expression::reference(['a'], Dialect::MySql), Expression::literal(0, Dialect::MySql));
        $check = new Check($predicate, false, name: 'positive');
        self::assertSame([], $check->localColumns());
        self::assertSame(ConstraintKind::Check, $check->kind);
        self::assertSame('positive', $check->name);
        self::assertFalse($check->enforced);
        self::assertFalse($check->noInherit);
        self::assertSame('constraint', $check->source->name);
    }

    public function testBindsColumnChecksBeforeNamedTableChecks(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, CONSTRAINT positive CHECK (a > 0), b INT CHECK (b < 10))')->tables[0];
        self::assertInstanceOf(Check::class, $table->constraints[0]);
        self::assertInstanceOf(Check::class, $table->constraints[1]);
        self::assertNull($table->constraints[0]->name);
        self::assertSame('(`b` < 10)', $table->constraints[0]->predicate->structure()->toString());
        self::assertSame('positive', $table->constraints[1]->name);
        self::assertSame('(`a` > 0)', $table->constraints[1]->predicate->structure()->toString());
        self::assertTrue($table->constraints[1]->enforced);
        self::assertSame([], $table->constraints[1]->localColumns());
    }
}
