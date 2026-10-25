<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference;

#[CoversClass(CreateSpatialReference::class)]
#[Medium]
final class CreateSpatialReferenceTest extends TestCase
{
    public function testRenderWritesIfNotExists(): void
    {
        self::assertSame("CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 9 NAME 'n' DEFINITION 'd'", (new Semantics(Dialect::MySql))->analyze("create spatial reference system if not exists 9 name 'n' definition 'd'")->toString());
    }

    public function testDeriveStatementLeavesCatalogConditionsToTheConsumer(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 9 NAME 'n' DEFINITION 'd'");
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testInputPreservesARequestWithAMissingDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 9 NAME 'n'");
        self::assertSame("CREATE SPATIAL REFERENCE SYSTEM 9 NAME 'n'", $operation->toString());
        self::assertInstanceOf(CreateSpatialReference::class, $operation->statement);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Definition, $operation->statement->problem?->attribute);
        self::assertSame($operation->statement->problem?->message(), $operation->facts->diagnostics[0]->message());
    }
    /**
     * @return iterable<string, array{\SqlSemantics\Platform\MySql\Statement\Literal\Numeral}>
     */
    public static function providerNonIntegerTokens(): iterable
    {
        foreach (['1.5', '1e2', '18446744073709551616'] as $number) {
            yield $number => [new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral($number)];
        }
    }

    #[DataProvider('providerNonIntegerTokens')]
    public function testIdentifierRequiresAnIntegerToken(\SqlSemantics\Platform\MySql\Statement\Literal\Numeral $number): void
    {
        $this->expectExceptionMessage('A spatial reference identifier is an integer token.');

        new CreateSpatialReference(false, false, $number, []);
    }

    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute>}>
     */
    public static function providerContradictoryDeclarations(): iterable
    {
        $name = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('n'));
        $definition = new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Definition, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('GEOGCS[]'));
        yield 'reserved zero' => ['0', [$name, $definition]];
        yield 'range' => ['4294967296', [$name, $definition]];
        yield 'missing name' => ['7', [$definition]];
        yield 'missing definition' => ['7', [$name]];
        yield 'empty list' => ['7', []];
        yield 'blank name' => ['7', [new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text(' ')), $definition]];
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute> $attributes
     */
    #[DataProvider('providerContradictoryDeclarations')]
    public function testRequestRetainsIntrinsicDeclarationProblems(string $srid, array $attributes): void
    {
        $statement = new CreateSpatialReference(false, false, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral($srid), $attributes);
        self::assertNotNull($statement->problem);
        self::assertSame($attributes, $statement->attributes);
        self::assertSame($srid, $statement->srid->text);
    }

}
