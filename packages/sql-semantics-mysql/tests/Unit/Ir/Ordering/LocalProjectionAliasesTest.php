<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(LocalProjectionAliases::class)]
#[Small]
final class LocalProjectionAliasesTest extends TestCase
{
    public function testCheckPermitsRemappingAnEffectiveName(): void
    {
        $value = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('n'))]);
        (new LocalProjectionAliases())->check($value);

        self::assertSame('SELECT 1 AS `n`', $value->toString());
    }
}
