<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Call\NamedConstant;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(NamedConstant::class)]
#[Small]
final class NamedConstantTest extends TestCase
{
    public function testCheckKeepsTheInvalidFactAndReportsBooleanValuesOnlyInModernReleases(): void
    {
        $sql = "SELECT NAME_CONST('x', TRUE) AS v";
        $modern = (new Semantics(Dialect::MySql))->analyze($sql);
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze($sql);

        self::assertInstanceOf(InvalidNamedConstant::class, $modern->facts->diagnostics[0]);
        self::assertInstanceOf(Invalid::class, $modern->field(0)->type);
        self::assertSame([], $legacy->facts->diagnostics);
    }

    public function testCheckReportsNullNamesAndAcceptsLiteralValues(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $null = $semantics->analyze('SELECT NAME_CONST(NULL, 1) AS v')->facts->diagnostics[0];

        self::assertInstanceOf(InvalidNamedConstant::class, $null);
        self::assertTrue($null->nullName);
        self::assertSame([], $semantics->analyze("SELECT NAME_CONST(TRUE, -1), NAME_CONST('x', NULL)")->facts->diagnostics);
    }

    public function testCheckPrecedesResolutionOfNonliteralArguments(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT NAME_CONST('a' COLLATE latin1_bin, missing)");

        self::assertInstanceOf(InvalidNamedConstant::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(Invalid::class, $operation->field(0)->type);
    }

    public function testLiteralDistinguishesNumericAndBooleanConstants(): void
    {
        self::assertTrue((new NamedConstant())->literal(new NumberLiteral('1')));
        self::assertFalse((new NamedConstant())->literal(new BooleanLiteral(true)));
    }

    public function testUnwrappedPreservesTheUnderlyingLiteral(): void
    {
        $literal = new NumberLiteral('1');

        self::assertSame($literal, (new NamedConstant())->unwrapped(new Grouped($literal)));
    }
}
