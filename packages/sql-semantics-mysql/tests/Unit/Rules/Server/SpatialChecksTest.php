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

    public function testAttributesReportsRepeatedAndMissingAttributes(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 5 DEFINITION 'd' DEFINITION 'e'");

        self::assertSame(['ER_SRS_MULTIPLE_ATTRIBUTE_DEFINITIONS', 'ER_SRS_MISSING_MANDATORY_ATTRIBUTE'], array_map(static fn (Diagnostic $problem): string => $problem instanceof SpatialProblem ? $problem->rule->value : '', $create->facts->diagnostics));
    }

    public function testAttributeReportsBlankControlAndLongValues(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE SPATIAL REFERENCE SYSTEM 5 NAME \'\' DEFINITION \'d\' ORGANIZATION \' o\' IDENTIFIED BY 99999999999 DESCRIPTION \'a\\tb\'');

        self::assertSame(['ER_SRS_NAME_CANT_BE_EMPTY_OR_WHITESPACE', 'ER_SRS_ORGANIZATION_CANT_BE_EMPTY_OR_WHITESPACE', 'ER_DATA_OUT_OF_RANGE', 'ER_SRS_INVALID_CHARACTER_IN_ATTRIBUTE'], array_map(static fn (Diagnostic $problem): string => $problem instanceof SpatialProblem ? $problem->rule->value : '', $create->facts->diagnostics));
    }
}
