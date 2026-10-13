<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\SpatialChecks;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(SpatialChecks::class)]
#[Medium]
final class SpatialChecksTest extends TestCase
{
    public function testSridReportsAnIdentifierOutOfRange(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP SPATIAL REFERENCE SYSTEM 4294967296');

        self::assertInstanceOf(SpatialProblem::class, $operation->facts->diagnostics[0]);
    }

    public function testDefinitionsReportsRepeatedAndMissingAttributes(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 5 DEFINITION 'd' DEFINITION 'e'");

        self::assertSame(['ER_SRS_MULTIPLE_ATTRIBUTE_DEFINITIONS', 'ER_SRS_MISSING_MANDATORY_ATTRIBUTE'], array_map(static fn (Diagnostic $problem): string => $problem instanceof SpatialProblem ? $problem->rule->value : '', $create->facts->diagnostics));
    }

    public function testAttributeReportsBlankControlAndLongValues(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE SPATIAL REFERENCE SYSTEM 5 NAME \'\' DEFINITION \'d\' ORGANIZATION \' o\' IDENTIFIED BY 99999999999 DESCRIPTION \'a\\tb\'');

        self::assertSame(['ER_SRS_NAME_CANT_BE_EMPTY_OR_WHITESPACE', 'ER_SRS_ORGANIZATION_CANT_BE_EMPTY_OR_WHITESPACE', 'ER_DATA_OUT_OF_RANGE', 'ER_SRS_INVALID_CHARACTER_IN_ATTRIBUTE'], array_map(static fn (Diagnostic $problem): string => $problem instanceof SpatialProblem ? $problem->rule->value : '', $create->facts->diagnostics));
    }

    public function testAttributesReportsAMissingNameBeforeTheOrganization(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 2 ORGANIZATION 'a''b' IDENTIFIED BY 04111845221216");

        self::assertSame(['ER_SRS_MISSING_MANDATORY_ATTRIBUTE', 'ER_SRS_MISSING_MANDATORY_ATTRIBUTE', 'ER_DATA_OUT_OF_RANGE'], array_map(static fn (Diagnostic $problem): string => $problem instanceof SpatialProblem ? $problem->rule->value : '', $create->facts->diagnostics));
    }

    public function testAttributesReportsTheNameBeforeAMissingDefinition(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 5 NAME 'a\tb'");

        self::assertSame(['ER_SRS_INVALID_CHARACTER_IN_ATTRIBUTE', 'ER_SRS_MISSING_MANDATORY_ATTRIBUTE'], array_map(static fn (Diagnostic $problem): string => $problem instanceof SpatialProblem ? $problem->rule->value : '', $create->facts->diagnostics));
    }
    public function testCreateKeepsLiteralAndDefinitionOrderBeforeIdentifierRange(): void
    {
        $first = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a\nb' NAME 'c'")->facts->diagnostics[0];
        $second = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a' NAME 'b' DESCRIPTION 'c\nd'")->facts->diagnostics[0];

        self::assertInstanceOf(SpatialProblem::class, $first);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::LineFeed, $first->rule);
        self::assertSame("a\nb", $first->value);
        self::assertInstanceOf(SpatialProblem::class, $second);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::RepeatedAttribute, $second->rule);
    }
}
