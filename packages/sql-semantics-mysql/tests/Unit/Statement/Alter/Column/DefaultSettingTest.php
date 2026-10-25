<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(DefaultSetting::class)]
#[Medium]
final class DefaultSettingTest extends TestCase
{
    public function testRenderExcludesAColumnTimestampAttributeInAnAlterDefaultAction(): void
    {
        $this->expectExceptionMessage('ALTER COLUMN SET DEFAULT accepts a literal or a parenthesized expression');

        new DefaultSetting(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral(new \SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp()));
    }

    public function testRenderExcludesABareClockCallInAnAlterDefaultAction(): void
    {
        $this->expectExceptionMessage('ALTER COLUMN SET DEFAULT accepts a literal or a parenthesized expression');

        new DefaultSetting(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral(new \SqlSemantics\Platform\MySql\Statement\Call\ClockCall(\SqlSemantics\Platform\MySql\Statement\Call\Clock::Now)));
    }

    public function testRenderRetainsAParenthesizedClockDefault(): void
    {
        $clock = new \SqlSemantics\Platform\MySql\Statement\Call\ClockCall(\SqlSemantics\Platform\MySql\Statement\Call\Clock::Now);
        $default = new \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression($clock);
        $command = new DefaultSetting(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), $default);

        self::assertSame($default, $command->default);
        self::assertSame($clock, $default->expression);
    }

    public function testDeriveCommandDerivesTheValueInTheChangedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER a SET DEFAULT (b + x)', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $alter);

        self::assertSame(['Column x does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testRenderWritesEveryForm(): void
    {
        self::assertSame("ALTER TABLE t ALTER a SET DEFAULT 'x', ALTER b SET DEFAULT (1), ALTER c DROP DEFAULT", (new Semantics(Dialect::MySql))->analyze("ALTER TABLE t ALTER a SET DEFAULT 'x', ALTER b SET DEFAULT (1), ALTER c DROP DEFAULT")->toString());
    }
}
