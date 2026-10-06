<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(DefaultOfColumn::class)]
#[Medium]
final class DefaultOfColumnTest extends TestCase
{
    public function testDeriveScalarHasTheFactsOfTheColumn(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $default = new DefaultOfColumn(new ColumnUse(new Name('a')));
        $fact = $derivation->scalar($default, $derivation->environment());
        $column = $derivation->scalar($default->column, $derivation->environment());

        self::assertEquals([$column->type, $column->nullability], [$fact->type, $fact->nullability]);
        self::assertInstanceOf(Invalid::class, $fact->type);
    }

    public function testRenderGluesTheParenthesis(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-5.6.51', null, ParameterStyle::Native)));
        (new DefaultOfColumn(new ColumnUse(new Name('a'))))->render($out);

        self::assertSame('DEFAULT(a)', (new Lexical())->join($out->pieces()));
    }
}
