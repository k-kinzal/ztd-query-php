<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowHeading;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(ShowHeading::class)]
#[Small]
final class ShowHeadingTest extends TestCase
{
    public function testNameBoundsWholeCharactersAndRetainsTheOriginalDiagnostic(): void
    {
        $pattern = str_repeat('a', 245) . 'é';
        $statement = new ShowDatabases(new ShowLike(new Text($pattern)));
        $operation = new Operation((new Semantics(Dialect::MySql))->context(), $statement);

        self::assertSame('Database (' . str_repeat('a', 245), $operation->field(0)->name?->value);
        self::assertCount(1, $operation->facts->warnings);
        self::assertInstanceOf(TrimmedName::class, $operation->facts->warnings[0]);
        self::assertSame('Database (' . $pattern . ')', $operation->facts->warnings[0]->name->value);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testNameRetainsTheUnresolvedTableLabelAfterShortening(): void
    {
        $statement = new ShowTables(database: new Name('d'), filter: new ShowLike(new Text(str_repeat('é', 123))));
        $operation = new Operation((new Semantics(Dialect::MySql))->context(), $statement);

        $name = $operation->field(0)->name;
        self::assertInstanceOf(Name::class, $name);
        self::assertSame(255, strlen($name->value));
        self::assertCount(2, $operation->facts->warnings);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
        self::assertSame('Tables_in_d (' . str_repeat('é', 123) . ')', $operation->facts->diagnostics[0]->name->value);
    }

    public function testPatternRetainsTheReleaseSpecificNulBoundary(): void
    {
        $statement = new ShowDatabases(new ShowLike(new Text("x\0" . str_repeat('a', 300))));
        $modern = new Operation((new Semantics(Dialect::MySql))->context(), $statement);
        $legacy = new Operation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context(), $statement);

        self::assertSame('Database (x', $modern->field(0)->name?->value);
        self::assertCount(1, $modern->facts->warnings);
        self::assertSame('Database (x)', $legacy->field(0)->name?->value);
        self::assertSame([], $legacy->facts->warnings);
    }

    public function testPatternConvertsLegacySupplementaryCharactersWithoutChangingTheOperand(): void
    {
        $statement = new ShowDatabases(new ShowLike(new Text('😀')));
        $operation = new Operation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context(), $statement);

        self::assertSame('Database (?)', $operation->field(0)->name?->value);
        self::assertInstanceOf(NameConversion::class, $operation->facts->warnings[0]);
        self::assertSame('😀', $statement->filter instanceof ShowLike ? $statement->filter->pattern->value : null);
    }
}
