<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Utility\Maintenance\UtilityOptions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;

#[CoversClass(UtilityOptions::class)]
#[Medium]
final class UtilityOptionsTest extends TestCase
{
    public function testReadFoldsNamesAndKeepsTheLastRepetition(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('VACUUM (Verbose, "X" 2, ANALYSE, verbose off)'), ['VacuumStmt'])[0];
        $options = UtilityOptions::read($source, new Identifiers(Dialect::PostgreSql));
        self::assertSame(['X', 'analyze', 'verbose'], array_keys($options));
        self::assertSame([2, null, 'off'], array_map(static fn (array $option): string|int|null => $option[0], array_values($options)));
    }

    public function testBooleanReadsTheServerBooleanWords(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('VACUUM (a, b OFF, c 1, d TRUE)'), ['VacuumStmt'])[0];
        $options = UtilityOptions::read($source, new Identifiers(Dialect::PostgreSql));
        self::assertSame([true, false, true, true], array_map(static fn (array $option): bool => UtilityOptions::boolean($option), array_values($options)));
    }

    public function testIntegerRequiresAnIntegerConstant(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("VACUUM (a -3, b '3')"), ['VacuumStmt'])[0];
        $options = UtilityOptions::read($source, new Identifiers(Dialect::PostgreSql));
        self::assertSame(-3, UtilityOptions::integer($options['a']));
        $this->expectException(InvalidSql::class);
        UtilityOptions::integer($options['b']);
    }

    public function testBufferUsageLimitReadsTheQuantity(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("VACUUM (a '1GB', b)"), ['VacuumStmt'])[0];
        $options = UtilityOptions::read($source, new Identifiers(Dialect::PostgreSql));
        self::assertSame(1048576, UtilityOptions::bufferUsageLimit($options['a'])->kilobytes);
        $this->expectException(InvalidSql::class);
        UtilityOptions::bufferUsageLimit($options['b']);
    }
}
