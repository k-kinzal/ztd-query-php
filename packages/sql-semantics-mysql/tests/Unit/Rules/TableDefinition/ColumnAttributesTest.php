<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute;

#[CoversClass(ColumnAttributes::class)]
#[Small]
final class ColumnAttributesTest extends TestCase
{
    public function testGeneratedNamesTheFirstConflictingAttributeBeforeTheType(): void
    {
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Serial);
        $attributes = [new \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'))];

        self::assertSame('DEFAULT', (new ColumnAttributes())->generated($type, $attributes));
        self::assertSame('SERIAL', (new ColumnAttributes())->generated($type, []));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerAttributes(): iterable
    {
        yield 'replaced clock default' => ['BIT DEFAULT CURRENT_TIMESTAMP DEFAULT 0', []];
        yield 'replaced clock precision' => ['DATETIME(3) DEFAULT CURRENT_TIMESTAMP(2) DEFAULT CURRENT_TIMESTAMP(3)', []];
        yield 'replaced update precision' => ['DATETIME(3) ON UPDATE CURRENT_TIMESTAMP(2) ON UPDATE CURRENT_TIMESTAMP(3)', []];
        yield 'nonspatial SRID' => ['INT SRID 4326', ['SRID']];
        yield 'spatial SRID' => ['POINT SRID 4326', []];
        yield 'integer default' => ['INT DEFAULT CURRENT_TIMESTAMP', ['DEFAULT']];
        yield 'date default' => ['DATE DEFAULT CURRENT_TIMESTAMP', ['DEFAULT']];
        yield 'integer on update' => ['INT ON UPDATE CURRENT_TIMESTAMP', ['ON UPDATE']];
        yield 'default precision' => ['TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP', ['DEFAULT']];
        yield 'update precision' => ['DATETIME(3) ON UPDATE CURRENT_TIMESTAMP(2)', ['ON UPDATE']];
        yield 'matching precision' => ['DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerAttributes')]
    public function testProblemsResolvesTheAttributeAgainstItsColumnType(string $definition, array $expected): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(c ' . $definition . ')');
        $problems = array_values(array_filter($operation->facts->diagnostics, static fn ($problem): bool => $problem instanceof InvalidColumnAttribute));

        self::assertSame($expected, array_map(static fn ($problem): string => $problem->attribute, $problems));
    }
}
