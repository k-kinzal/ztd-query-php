<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\Insert;
use SqlSemantics\Platform\MySql\Ir\Mutation\InsertRow;
use SqlSemantics\Platform\MySql\Ir\Mutation\InsertValue;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(Insert::class)]
#[Small]
final class InsertTest extends TestCase
{
    public function testToStringKeepsTargetColumnAndRowOrder(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('t'), [new Identifier('b'), new Identifier('a')]);
        $row = new InsertRow([new InsertValue(null), new InsertValue(new IntegerLiteral('-2'))]);
        $value = new Insert($target, [$row, $row]);

        self::assertSame('INSERT INTO `app`.`t` (`b`, `a`) VALUES (NULL, -2), (NULL, -2)', $value->toString());
    }

    public function testProfileIdBeginsWithTheNativeGrammarRelease(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('t'), [new Identifier('a')]);
        $row = new InsertRow([new InsertValue(null)]);

        self::assertStringStartsWith('mysql-9.1.0/', (new Insert($target, [$row]))->profileId());
    }

    public function testRejectsAWidthThatDoesNotMatchTheTarget(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('t'), [new Identifier('b'), new Identifier('a')]);
        $row = new InsertRow([new InsertValue(null)]);
        $this->expectExceptionMessage('must match the target width');

        new Insert($target, [$row]);
    }
}
