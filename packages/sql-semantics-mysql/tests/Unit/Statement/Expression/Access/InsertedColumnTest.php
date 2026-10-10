<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

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

    public function testDeriveScalarRaisesTheDeprecationOnlyOnceTheColumnResolves(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertCount(0, $semantics->analyze('SELECT VALUES(nosuch) FROM t', [$t])->facts->warnings);
        self::assertCount(1, $semantics->analyze('SELECT VALUES(a) FROM t', [$t])->facts->warnings);
    }

    public function testRenderGluesTheParenthesis(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new InsertedColumn(new ColumnUse(new Name('a'))))->render($out);

        self::assertSame('VALUES(a)', (new Lexical())->join($out->pieces()));
    }

    public function testDeriveScalarReadsTheWrittenTableInOnDuplicateKeyUpdate(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT PRIMARY KEY, b INT)');
        $facts = $semantics->analyze('INSERT INTO t (a, b) VALUES (2, 5) AS nw ON DUPLICATE KEY UPDATE b = VALUES(b) + nw.b', [$t])->facts;

        self::assertSame([[], [\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ValuesFunction->value]], [$facts->diagnostics, array_map(static fn ($warning): string => $warning->message(), $facts->warnings)]);
    }

    public function testDeriveScalarIsNullOutsideOnDuplicateKeyUpdate(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT)');
        $query = $semantics->analyze('SELECT VALUES(a) FROM t', [$t]);
        $type = $query->facts->output?->fields()?->at(0)->type;

        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $type);
        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ValuesElsewhere->value], [$type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain ? $type->descriptor->kind : null, $query->facts->warnings[0]->message()]);
    }
}
