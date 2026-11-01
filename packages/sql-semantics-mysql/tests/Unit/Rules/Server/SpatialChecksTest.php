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
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        (new SpatialChecks())->srid($derivation, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4294967296'));
        self::assertInstanceOf(SpatialProblem::class, $derivation->facts()->diagnostics[0]);
    }

    public function testDefinitionsReportsRepeatedAndMissingAttributes(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        $definition = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Definition, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('d'));
        (new SpatialChecks())->create($derivation, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('5'), [$definition, $definition]);
        self::assertSame(['ER_SRS_MULTIPLE_ATTRIBUTE_DEFINITIONS', 'ER_SRS_MISSING_MANDATORY_ATTRIBUTE'], array_map(static fn (Diagnostic $problem): string => $problem instanceof SpatialProblem ? $problem->rule->value : '', $derivation->facts()->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerIntrinsicDeclarations(): iterable
    {
        yield 'attribute ordering' => ['CREATE SPATIAL REFERENCE SYSTEM 5 NAME \'\' DEFINITION \'d\' ORGANIZATION \' o\' IDENTIFIED BY 99999999999 DESCRIPTION \'a\\tb\'', 'name cannot be an empty string'];
        yield 'missing name before organization' => ["CREATE SPATIAL REFERENCE SYSTEM 2 ORGANIZATION 'a''b' IDENTIFIED BY 04111845221216", 'Missing mandatory attribute NAME'];
        yield 'invalid name before missing definition' => ["CREATE SPATIAL REFERENCE SYSTEM 5 NAME 'a\tb'", 'Invalid character in attribute NAME'];
        yield 'drop range' => ['DROP SPATIAL REFERENCE SYSTEM 4294967296', 'SRID is out of range'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIntrinsicDeclarations')]
    public function testDeclarationRetainsItsFirstIntrinsicProblem(string $sql, string $message): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $statement = $operation;
        self::assertTrue($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference);
        self::assertNotNull($statement->problem);
        self::assertStringContainsString($message, $statement->problem->message());
        self::assertSame($statement->problem->message(), $operation->diagnostics()[0]->message());
    }

    public function testRepeatedKeepsTheFirstDuplicateRole(): void
    {
        $name = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a'));
        $definition = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Definition, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('d'));
        self::assertNull((new SpatialChecks())->repeated([$name, $definition]));
        self::assertSame($definition->kind, (new SpatialChecks())->repeated([$name, $definition, $definition, $name])?->attribute);
        self::assertSame($name->kind, (new SpatialChecks())->declaration(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0'), [$name, $name])?->attribute);
    }

    public function testIdentifierChecksTheModifiableRange(): void
    {
        $checks = new SpatialChecks();
        self::assertNull($checks->identifier(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4294967295')));
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierZero, $checks->identifier(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0'))?->rule);
    }

    public function testAttributeRetainsEveryIntrinsicValueCondition(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        $attribute = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Organization, new \SqlSemantics\Platform\MySql\Statement\Literal\Text(' o'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('99999999999'));
        (new SpatialChecks())->attribute($derivation, $attribute);
        self::assertCount(2, $derivation->facts()->diagnostics);
    }

    public function testValueProblemsChecksLengthAndControlCharacters(): void
    {
        $attribute = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text(str_repeat('a', 80) . "\t"));
        self::assertSame(['ER_SRS_NAME_CANT_BE_EMPTY_OR_WHITESPACE', 'ER_SRS_INVALID_CHARACTER_IN_ATTRIBUTE', 'ER_SRS_ATTRIBUTE_STRING_TOO_LONG'], array_map(static fn (SpatialProblem $problem): string => $problem->rule->value, (new SpatialChecks())->valueProblems($attribute)));
    }

    public function testCreateKeepsLiteralAndDefinitionOrderBeforeIdentifierRange(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        $name = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a'));
        (new SpatialChecks())->create($derivation, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0'), [$name, $name]);
        $first = $derivation->facts()->diagnostics[0];
        self::assertInstanceOf(SpatialProblem::class, $first);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::RepeatedAttribute, $first->rule);
    }
    public function testAttributesRetainsBothMissingOperandConditions(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        (new SpatialChecks())->attributes($derivation, []);
        self::assertSame(['Missing mandatory attribute NAME (ER_SRS_MISSING_MANDATORY_ATTRIBUTE).', 'Missing mandatory attribute DEFINITION (ER_SRS_MISSING_MANDATORY_ATTRIBUTE).'], array_map(static fn (Diagnostic $problem): string => $problem->message(), $derivation->facts()->diagnostics));
    }

}
