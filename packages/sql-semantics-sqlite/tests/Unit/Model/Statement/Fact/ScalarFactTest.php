<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ScalarFact::class)]
#[Medium]
final class ScalarFactTest extends TestCase
{
    public function testResolutionIsNullForAnExpressionThatIsNoNameUse(): void
    {
        $query = (new Inspection())->inspect("SELECT 'a'");
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
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $query = $semantics->inspect('SELECT a FROM t WHERE a > 1', [$table]);
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
