<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(NameConversion::class)]
#[Medium]
final class NameConversionTest extends TestCase
{
    public function testDescribeNamesTheConversion(): void
    {
        self::assertSame('the character set conversion MySQL applies to the text it names a column after: character_set_client, or the character set of an introducer', (new NameConversion())->describe());
    }

    public function testADerivedColumnNamedAfterNonAsciiTextIsNotFoundByName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT x FROM (SELECT 'é') AS d", []);

        self::assertInstanceOf(Dependent::class, $operation->field('x')->type);
        self::assertInstanceOf(NameConversion::class, $operation->field('x')->type->missing[0]);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertNotNull((new Semantics(Dialect::MySql))->analyze("SELECT * FROM (SELECT 'é') AS d", [])->fields());
    }

    public function testADerivedColumnNamedAfterAsciiTextIsKnown(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT x FROM (SELECT 1+1) AS d', []);

        self::assertInstanceOf(MissingColumn::class, $operation->field('x')->resolution);
        self::assertSame('1+1', (new Semantics(Dialect::MySql))->analyze('SELECT * FROM (SELECT 1+1) AS d', [])->field(0)->name?->value);
    }
}
