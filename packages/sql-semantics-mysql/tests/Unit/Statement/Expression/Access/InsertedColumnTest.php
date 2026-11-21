<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

#[CoversClass(InsertedColumn::class)]
#[Medium]
final class InsertedColumnTest extends TestCase
{
    public function testDeriveScalarCanAlwaysBeNull(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));

        self::assertSame(Nullability::Nullable, $derivation->scalar(new InsertedColumn(new ColumnUse(new Name('a'))), $derivation->environment())->nullability);
    }

    public function testRenderGluesTheParenthesis(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new InsertedColumn(new ColumnUse(new Name('a'))))->render($out);

        self::assertSame('VALUES(a)', (new Lexical())->join($out->pieces()));
    }
}
