<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(NullLiteral::class)]
#[Medium]
final class NullLiteralTest extends TestCase
{
    public function testDeriveScalarGivesTheNullOnlyType(): void
    {
        $operation = (new Inspection())->inspect('SELECT NULL', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(NullLiteral::class, $statement->columns[0]->expression);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(NullOnly::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheKeywordInUpperCase(): void
    {
        self::assertSame('SELECT NULL AS c1, NULL AS c2', (new Inspection())->inspect('select null AS c1, Null AS c2')->toString());
    }

    public function testRenderWritesANewlyBuiltLiteral(): void
    {
        $semantics = new Inspection();
        $operation = new InspectedStatement($semantics->context(), new Select([new ResultColumn(new NullLiteral())]));

        self::assertSame('SELECT NULL', $operation->toString());
    }
}
