<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TimeKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CurrentTime::class)]
#[Medium]
final class CurrentTimeTest extends TestCase
{
    public function testDeriveScalarGivesTextThatIsNeverNull(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CURRENT_TIME, CURRENT_DATE, CURRENT_TIMESTAMP', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(CurrentTime::class, $statement->columns[1]->expression);
        self::assertSame(TimeKeyword::Date, $statement->columns[1]->expression->keyword);
        $fact = $operation->facts->scalar($statement->columns[1]->expression);
        self::assertEquals(new Known(Storage::Text), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertEquals(new Known(Storage::Text), $operation->field(0)->type);
        self::assertEquals(new Known(Storage::Text), $operation->field(2)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheKeywordInUpperCase(): void
    {
        self::assertSame('SELECT CURRENT_TIME AS c1, CURRENT_DATE AS c2, CURRENT_TIMESTAMP AS c3', (new Semantics(Dialect::Sqlite))->analyze('select current_time AS c1, Current_Date AS c2, current_timestamp AS c3')->toString());
    }

    public function testRenderWritesANewlyBuiltKeyword(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new CurrentTime(TimeKeyword::Timestamp))]));

        self::assertSame('SELECT CURRENT_TIMESTAMP', $operation->toString());
    }
}
