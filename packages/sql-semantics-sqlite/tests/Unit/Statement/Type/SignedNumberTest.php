<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(SignedNumber::class)]
#[Medium]
final class SignedNumberTest extends TestCase
{
    public function testRenderKeepsTheNativeSpacingAroundSignedNumbers(): void
    {
        $query = (new Inspection())->inspect('SELECT CAST(1 AS DECIMAL( + 10 , - 2 )) AS c1');
        $cast = $query->field(0)->expression;

        self::assertInstanceOf(Cast::class, $cast);
        self::assertNotNull($cast->target);
        self::assertSame(NumberSign::Minus, $cast->target->arguments[1]->sign);
        self::assertInstanceOf(IntegerLiteral::class, $cast->target->arguments[1]->number);
        self::assertSame('2', $cast->target->arguments[1]->number->digits);
        self::assertSame('SELECT CAST(1 AS DECIMAL( + 10 , - 2 )) AS c1', $query->toString());
    }

    public function testRenderWritesEveryKindOfNumericLiteral(): void
    {
        $query = (new Inspection())->inspect('SELECT CAST(1 AS DECIMAL(10)), CAST(1 AS DECIMAL(0x10)), CAST(1 AS DECIMAL(1.5))');
        $numbers = array_map(static fn (Field $field): ?string => $field->expression instanceof Cast ? $field->expression->target?->arguments[0]->number::class : null, $query->fields()->items ?? []);

        self::assertSame([IntegerLiteral::class, HexLiteral::class, RealLiteral::class], $numbers);
        self::assertSame('SELECT CAST(1 AS DECIMAL(10)), CAST(1 AS DECIMAL(0x10)), CAST(1 AS DECIMAL(1.5))', $query->toString());
    }

    public function testRenderWritesANewlyBuiltNumber(): void
    {
        $semantics = new Inspection();
        $target = new TypeName([new Word(new Name('DECIMAL'))], [new SignedNumber(NumberSign::Plus, new IntegerLiteral('10')), new SignedNumber(null, new RealLiteral('2', '5'))]);
        $operation = new InspectedStatement($semantics->context(), new Select([new ResultColumn(new Cast(new IntegerLiteral('1'), $target))]));

        self::assertSame('SELECT CAST(1 AS DECIMAL(+10,2.5))', $operation->toString());
    }
}
