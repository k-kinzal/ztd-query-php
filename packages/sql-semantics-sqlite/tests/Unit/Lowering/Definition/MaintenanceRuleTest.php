<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\MaintenanceRule;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(MaintenanceRule::class)]
#[Medium]
final class MaintenanceRuleTest extends TestCase
{
    public function testCommandLowersEachAdministrationCommandToItsOwnRequest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(Vacuum::class, $semantics->analyze('VACUUM')->statement);
        self::assertInstanceOf(Vacuum::class, $semantics->analyze('VACUUM main')->statement);
        self::assertInstanceOf(Pragma::class, $semantics->analyze('PRAGMA a')->statement);
        self::assertInstanceOf(Pragma::class, $semantics->analyze('PRAGMA a = 1')->statement);
        self::assertInstanceOf(Pragma::class, $semantics->analyze('PRAGMA a(-1)')->statement);
        self::assertInstanceOf(Reindex::class, $semantics->analyze('REINDEX')->statement);
        self::assertInstanceOf(Reindex::class, $semantics->analyze('REINDEX a.b')->statement);
        self::assertInstanceOf(Analyze::class, $semantics->analyze('ANALYZE')->statement);
        self::assertInstanceOf(Analyze::class, $semantics->analyze('ANALYZE a')->statement);
    }

    public function testCommandReadsTheFirstNameOfAQualifiedPragmaAsItsSchema(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('PRAGMA aux.page_size')->statement;

        self::assertInstanceOf(Pragma::class, $statement);
        self::assertSame('aux', $statement->name->schema?->value);
        self::assertSame('page_size', $statement->name->name->value);
        self::assertNull($statement->value);
    }

    public function testIntoIsNullUnlessATargetIsWritten(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('VACUUM')->statement;
        $into = $semantics->analyze("VACUUM INTO 'f'")->statement;

        self::assertInstanceOf(Vacuum::class, $plain);
        self::assertInstanceOf(Vacuum::class, $into);
        self::assertNull($plain->into);
        self::assertNotNull($into->into);
    }

    public function testValueKeepsEachOperandKindApart(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $number = $semantics->analyze('PRAGMA a = +5')->statement;
        $negative = $semantics->analyze('PRAGMA a = -5')->statement;
        $name = $semantics->analyze('PRAGMA a = full')->statement;
        $on = $semantics->analyze('PRAGMA a = ON')->statement;
        $default = $semantics->analyze('PRAGMA a(default)')->statement;

        self::assertInstanceOf(Pragma::class, $number);
        self::assertInstanceOf(Pragma::class, $negative);
        self::assertInstanceOf(Pragma::class, $name);
        self::assertInstanceOf(Pragma::class, $on);
        self::assertInstanceOf(Pragma::class, $default);
        self::assertInstanceOf(SignedNumber::class, $number->value);
        self::assertSame(NumberSign::Plus, $number->value->sign);
        self::assertInstanceOf(SignedNumber::class, $negative->value);
        self::assertSame(NumberSign::Minus, $negative->value->sign);
        self::assertInstanceOf(Name::class, $name->value);
        self::assertSame(PragmaKeyword::On, $on->value);
        self::assertSame(PragmaKeyword::Default, $default->value);
    }
}
