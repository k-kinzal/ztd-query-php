<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\LoadFacts;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(LoadFacts::class)]
#[Medium]
final class LoadFactsTest extends TestCase
{
    public function testDeriveReportsAnUnknownColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze("LOAD DATA INFILE 'f' INTO TABLE t (a, z)", [$t]);

        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveFindsTheInvisibleColumnsOfTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE v (a INT, e INT INVISIBLE)')->declarations();

        self::assertSame([], $semantics->analyze("LOAD DATA INFILE 'f' INTO TABLE v (a, e)", $tables)->facts->diagnostics);
    }

    public function testDeriveWarnsOfTheCharacterSetTheStatementNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT)');
        $facts = $semantics->analyze("LOAD DATA INFILE 'f' INTO TABLE t CHARACTER SET utf8", [$t])->facts;

        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8Alias->value], array_map(static fn ($warning): string => $warning->message(), $facts->warnings));
    }
}
