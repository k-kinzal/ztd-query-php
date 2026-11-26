<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithDefinition;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(WithDefinition::class)]
#[Small]
final class WithDefinitionTest extends TestCase
{
    public function testKeepsTheBody(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);
        $definition = new WithDefinition($body);

        self::assertSame($body, $definition->body);
    }
}
