<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateRange::class)]
#[Medium]
final class CreateRangeTest extends TestCase
{
    public function testRenderWritesTheAttributes(): void
    {
        self::assertSame('CREATE TYPE r AS RANGE (subtype = float8)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE r AS RANGE (subtype = float8)')->toString());
    }

    public function testDeriveStatementReportsTheMissingSubtype(): void
    {
        self::assertSame('type attribute "subtype" is required', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE floatrange AS RANGE (subtype_diff = float8mi)')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReadsTheAttributes(): void
    {
        $range = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE r AS RANGE (subtype = float8, subtype_diff = float8mi, multirange_type_name = rs)')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateRange::class, $range);
        self::assertEquals([new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument(\SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::Function, new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('float8mi')])), new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument(\SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::Type, new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('rs')]))], [$range->options[1]->value, $range->options[2]->value]);
    }

    public function testRejectsAnAttributeOfAnotherCommand(): void
    {
        $this->expectExceptionMessage('A range type attribute is recognized exactly when CREATE TYPE ... AS RANGE knows its name.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateRange(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('r')]), [new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute(new \SqlSemantics\Statement\Identifier\Name('subtype'), null)]);
    }
}
