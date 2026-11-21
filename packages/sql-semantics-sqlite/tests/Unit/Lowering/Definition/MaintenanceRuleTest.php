<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\MaintenanceRule;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;

#[CoversClass(MaintenanceRule::class)]
#[Medium]
final class MaintenanceRuleTest extends TestCase
{
    public function testCommandLowersEachAdministrationCommandToItsOwnRequest(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(Vacuum::class, $semantics->inspect('VACUUM')->statement);
        self::assertInstanceOf(Vacuum::class, $semantics->inspect('VACUUM main')->statement);
        self::assertInstanceOf(Pragma::class, $semantics->inspect('PRAGMA a')->statement);
        self::assertInstanceOf(Pragma::class, $semantics->inspect('PRAGMA a = 1')->statement);
        self::assertInstanceOf(Pragma::class, $semantics->inspect('PRAGMA a(-1)')->statement);
        self::assertInstanceOf(Reindex::class, $semantics->inspect('REINDEX')->statement);
        self::assertInstanceOf(Reindex::class, $semantics->inspect('REINDEX a.b')->statement);
        self::assertInstanceOf(Analyze::class, $semantics->inspect('ANALYZE')->statement);
        self::assertInstanceOf(Analyze::class, $semantics->inspect('ANALYZE a')->statement);
    }

    public function testCommandReadsTheFirstNameOfAQualifiedPragmaAsItsSchema(): void
    {
        $statement = (new Semantics())->inspect('PRAGMA aux.page_size')->statement;

        self::assertInstanceOf(Pragma::class, $statement);
        self::assertSame('aux', $statement->name->schema?->value);
        self::assertSame('page_size', $statement->name->name->value);
        self::assertNull($statement->value);
    }

    public function testIntoIsNullUnlessATargetIsWritten(): void
    {
        $semantics = new Semantics();
        $plain = $semantics->inspect('VACUUM')->statement;
        $into = $semantics->inspect("VACUUM INTO 'f'")->statement;

        self::assertInstanceOf(Vacuum::class, $plain);
        self::assertInstanceOf(Vacuum::class, $into);
        self::assertNull($plain->into);
        self::assertNotNull($into->into);
    }

    public function testValueKeepsEachOperandKindApart(): void
    {
        $semantics = new Semantics();
        $number = $semantics->inspect('PRAGMA a = +5')->statement;
        $negative = $semantics->inspect('PRAGMA a = -5')->statement;
        $name = $semantics->inspect('PRAGMA a = full')->statement;
        $on = $semantics->inspect('PRAGMA a = ON')->statement;
        $default = $semantics->inspect('PRAGMA a(default)')->statement;

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
