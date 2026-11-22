<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TextLiteral::class)]
#[Medium]
final class TextLiteralTest extends TestCase
{
    public function testDeriveScalarGivesTextAndKeepsTheDecodedValue(): void
    {
        $operation = (new Inspection())->inspect("SELECT 'it''s', ''", []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(TextLiteral::class, $statement->columns[0]->expression);
        self::assertInstanceOf(TextLiteral::class, $statement->columns[1]->expression);
        self::assertSame("it's", $statement->columns[0]->expression->value);
        self::assertSame('', $statement->columns[1]->expression->value);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertEquals(new Known(Storage::Text), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderDoublesEveryEmbeddedQuote(): void
    {
        self::assertSame("SELECT 'it''s', '', ''''", (new Inspection())->inspect("select 'it''s', '', ''''")->toString());
    }

    public function testRenderWritesANewlyBuiltLiteral(): void
    {
        $semantics = new Inspection();
        $operation = new InspectedStatement($semantics->context(), new Select([new ResultColumn(new TextLiteral("it's")), new ResultColumn(new TextLiteral('a"b'))]));

        self::assertSame("SELECT 'it''s', 'a\"b'", $operation->toString());
    }
}
