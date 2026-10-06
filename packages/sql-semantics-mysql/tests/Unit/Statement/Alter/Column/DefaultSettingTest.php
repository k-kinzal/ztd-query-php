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
    public function testDeriveCommandDerivesTheValueInTheChangedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER a SET DEFAULT (b + x)', [$table]);

        self::assertSame(['Column x does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testRenderWritesEveryForm(): void
    {
        self::assertSame("ALTER TABLE t ALTER COLUMN a SET DEFAULT 'x', ALTER COLUMN b SET DEFAULT (1), ALTER COLUMN c DROP DEFAULT", (new Semantics(Dialect::MySql))->analyze("ALTER TABLE t ALTER a SET DEFAULT 'x', ALTER b SET DEFAULT (1), ALTER c DROP DEFAULT")->toString());
    }
}
