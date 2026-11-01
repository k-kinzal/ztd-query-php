<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\CastText;
use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;

#[CoversClass(CastText::class)]
#[Small]
final class CastTextTest extends TestCase
{
    public function testWriteRetainsTheResolvedCharacterSetAndLength(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT CAST(7 AS CHAR(5) CHARACTER SET ascii)');
        self::assertInstanceOf(QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        $item = $operation->query->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        self::assertInstanceOf(Cast::class, $item->expression);
        $writer = new ViewText($operation->statementInformation(), '', '');
        self::assertSame('cast(7 as char(5) charset ascii)', (new CastText($writer->expressions))->write($item->expression));
    }
}
