<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateAsAffinity;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateAsLayout;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration;
use SqlSemantics\Platform\Sqlite\Ir\Select;

#[CoversClass(CreateAsLayout::class)]
#[Small]
final class CreateAsLayoutTest extends TestCase
{
    public function testColumnsRetainTheNativeBooleanAliasFallback(): void
    {
        $query = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('TRUE'))]);
        $columns = (new CreateAsLayout())->columns($query);

        self::assertSame('column1', $columns[0]->name->value);
    }

    public function testValuesUseTheFirstRowAsAWholeToSelectCompoundAffinity(): void
    {
        $typed = new CastExpression(CastTarget::Text, new TextLiteral('value'));
        $first = new ValueRow([new TextLiteral('untyped'), $typed]);
        $second = new ValueRow([$typed, $typed]);
        $layout = (new CreateAsLayout())->values(new ValuesQuery([$first, $second]));

        self::assertSame(CreatedColumnDeclaration::Text, $layout[0]->type);
        self::assertSame(CreatedColumnDeclaration::Text, $layout[1]->type);
    }

    public function testHasAffinityChecksEveryFirstRowOperand(): void
    {
        $typed = new CastExpression(CastTarget::Blob, new TextLiteral('value'));
        $query = new ValuesQuery([new ValueRow([new IntegerLiteral('0'), $typed])]);

        self::assertTrue((new CreateAsLayout())->hasAffinity($query, new CreateAsAffinity()));
    }

    public function testNameReplacesAnExistingSuffixWhenItCollides(): void
    {
        $layout = new CreateAsLayout();
        $name = $layout->name(new Identifier('Text:1'), 2, ['text:1' => true]);

        self::assertSame('Text:2', $name->value);
    }
}
