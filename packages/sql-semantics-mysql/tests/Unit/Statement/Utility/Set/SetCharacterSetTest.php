<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SetCharacterSet::class)]
#[Medium]
final class SetCharacterSetTest extends TestCase
{
    public function testDeriveItemDerivesNothing(): void
    {
        $set = (new Inspection())->inspect('SET CHARSET latin1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        self::assertInstanceOf(SetCharacterSet::class, $set->statement->items[0]);
        self::assertSame('latin1', $set->statement->items[0]->charset->name?->value);
    }

    public function testRenderWritesCharset(): void
    {
        self::assertSame('SET CHARSET `binary`', (new Inspection('mysql-5.7.44'))->inspect('set char set binary')->toString());
    }
}
