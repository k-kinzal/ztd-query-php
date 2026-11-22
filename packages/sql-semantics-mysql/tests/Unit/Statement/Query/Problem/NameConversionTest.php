<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\DependentField;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(NameConversion::class)]
#[Medium]
final class NameConversionTest extends TestCase
{
    public function testDescribeNamesTheConversion(): void
    {
        self::assertSame('the conversion MySQL applies to text in the character set latin2 when it names a column after it', (new NameConversion('latin2'))->describe());
    }

    public function testADerivedColumnNamedAfterNonAsciiTextIsNotFoundByName(): void
    {
        $operation = (new Inspection())->diagnose("SELECT x FROM (SELECT 'é') AS d", []);
        $star = (new Inspection())->diagnose("SELECT * FROM (SELECT 'é', _latin2'é') AS d", []);

        self::assertInstanceOf(Dependent::class, $operation->field('x')->type);
        self::assertEquals(new SessionState('character_set_client'), $operation->field('x')->type->missing[0]);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertEquals([new NameConversion('latin2')], $star->fields()?->at(1)->slot->unnamed);
        self::assertInstanceOf(DependentField::class, $star->fields()?->lookup('é'));
    }

    public function testAnUnaliasedItemNamedAfterNonAsciiTextHasADependentName(): void
    {
        $operation = (new Inspection())->diagnose("SELECT 'ü', _utf16'ab'", []);

        self::assertNull($operation->field(0)->name);
        self::assertEquals([new SessionState('character_set_client')], $operation->field(0)->slot->unnamed);
        self::assertEquals([new NameConversion('utf16')], $operation->field(1)->slot->unnamed);
        self::assertInstanceOf(DependentField::class, $operation->fields()?->lookup('ü'));
    }

    public function testADerivedColumnNamedAfterAsciiTextIsKnown(): void
    {
        $operation = (new Inspection())->diagnose('SELECT x FROM (SELECT 1+1) AS d', []);

        self::assertInstanceOf(MissingColumn::class, $operation->field('x')->resolution);
        self::assertSame('1+1', (new Inspection())->diagnose('SELECT * FROM (SELECT 1+1) AS d', [])->field(0)->name?->value);
    }
}
