<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithDefinition;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithMaterialization;
use SqlSemantics\Platform\PostgreSql\Ir\Select;

#[CoversClass(WithDefinition::class)]
#[Small]
final class WithDefinitionTest extends TestCase
{
    public function testKeepsPolicyWithTheBody(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);
        $definition = new WithDefinition($body, WithMaterialization::Materialized);

        self::assertSame($body, $definition->body);
        self::assertSame(WithMaterialization::Materialized, $definition->materialization);
    }
}
