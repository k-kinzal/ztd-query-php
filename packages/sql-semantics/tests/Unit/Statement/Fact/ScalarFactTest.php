<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ScalarFact::class)]
#[Medium]
final class ScalarFactTest extends TestCase
{
    public function testResolutionIsNullForAnExpressionThatIsNoNameUse(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 'a'");
        $expression = $query->field(0)->expression;

        self::assertNotNull($expression);
        $fact = $query->facts->scalar($expression);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame(Storage::Text, $fact->type->descriptor);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
    }

    public function testResolutionIsKeptForANameUse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT a FROM t WHERE a > 1', [$table]);
        $expression = $query->field('a')->expression;

        self::assertNotNull($expression);
        $fact = $query->facts->scalar($expression);
        self::assertInstanceOf(ResolvedColumn::class, $fact->resolution);
        self::assertSame($table->declarations()[0]->columns[0], $fact->resolution->declaration());
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testTheFactIsAPlainValue(): void
    {
        $fact = new ScalarFact(new Known(Storage::Real), Nullability::Dependent);

        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertNull($fact->resolution);
    }
}
