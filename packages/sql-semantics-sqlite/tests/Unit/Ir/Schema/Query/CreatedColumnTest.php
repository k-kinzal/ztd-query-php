<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration;

#[CoversClass(CreatedColumn::class)]
#[Small]
final class CreatedColumnTest extends TestCase
{
    public function testKeepsTheResolvedNameAndDeclaredType(): void
    {
        $name = new Identifier('my result');
        $column = new CreatedColumn($name, CreatedColumnDeclaration::Real);

        self::assertSame($name, $column->name);
        self::assertSame(CreatedColumnDeclaration::Real, $column->type);
    }
}
