<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\Grammar\Source\SqlVersionRegistry;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\TerminalInventory;
use SqlFaker\MySql\MySqlCatalogWitnesses;
use SqlFaker\MySql\MySqlLexicalSamples;

#[CoversClass(MySqlCatalogWitnesses::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(TerminalInventory::class)]
#[UsesClass(MySqlLexicalSamples::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
final class MySqlCatalogWitnessesTest extends TestCase
{
    public function testWitness(): void
    {
        self::assertSame(
            ['id' => 'mysql.symbol.IDENT.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['MY_LEX_START']],
            (new MySqlCatalogWitnesses())->witness('mysql.symbol.IDENT.0', 'users', ['IDENT'], ['MY_LEX_START']),
        );
    }

    public function testWitnessKeepsTheTextALexemeNeedsAroundIt(): void
    {
        self::assertSame(
            'COUNT(',
            (new MySqlCatalogWitnesses())->witness('id', 'COUNT', ['COUNT_SYM'], [], 'COUNT(')['context_sql'] ?? null,
        );
    }

    public function testFromTables(): void
    {
        self::assertSame(
            ['SELECT_SYM' => ['mysql.symbol.SELECT_SYM.0'], 'COUNT_SYM' => ['mysql.function.COUNT_SYM.0']],
            array_map(
                static fn (array $witnesses): array => array_column($witnesses, 'id'),
                (new MySqlCatalogWitnesses())->fromTables(['SELECT_SYM' => ['SELECT']], ['COUNT_SYM' => ['COUNT']]),
            ),
        );
    }

    public function testFromTablesLeavesOutTheTerminalsDefaultSqlModeNeverEmits(): void
    {
        self::assertSame([], (new MySqlCatalogWitnesses())->fromTables(['NOT2_SYM' => ['!'], 'OR_OR_SYM' => ['||']], []));
    }

    public function testShapeSamples(): void
    {
        self::assertSame(
            ['name.column', '.5', '<=', '<=>', '*/', ';', '@name', "@'name'", '@@name'],
            array_column((new MySqlCatalogWitnesses())->shapeSamples(), 0),
        );
    }

    public function testStateSamples(): void
    {
        $samples = (new MySqlCatalogWitnesses())->stateSamples(['MY_LEX_IDENT_OR_DOLLAR_QUOTED_TEXT'], true);

        self::assertSame([['$$text$$', ['DOLLAR_QUOTED_STRING_SYM'], ['MY_LEX_START', 'MY_LEX_IDENT_OR_DOLLAR_QUOTED_TEXT']]], $samples['DOLLAR_QUOTED_STRING_SYM']);
    }

    public function testStateSamplesReadsADollarSignAsANameWhereTheVersionHasNoQuotedString(): void
    {
        $samples = (new MySqlCatalogWitnesses())->stateSamples(['MY_LEX_IDENT_OR_DOLLAR_QUOTED_TEXT'], false);

        self::assertContains(['$identifier', ['IDENT'], ['MY_LEX_START', 'MY_LEX_IDENT_OR_DOLLAR_QUOTED_TEXT']], $samples['@COVERAGE']);
    }

    public function testStateSamplesReportsAVersionThatReadsDollarQuotesWithoutAStateForThem(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dollar-quoted string state was not found');

        (new MySqlCatalogWitnesses())->stateSamples(['MY_LEX_START'], true);
    }

    public function testFromSamples(): void
    {
        $terminals = (new MySqlCatalogWitnesses())->fromSamples([], ['MY_LEX_START'], false);

        self::assertContains(
            ['id' => 'mysql.family.@COVERAGE.0', 'sql' => 'name.column', 'tokens' => ['IDENT', '.', 'IDENT'], 'units' => ['MY_LEX_IDENT_SEP', 'MY_LEX_IDENT_START']],
            $terminals['@COVERAGE'],
        );
    }

    public function testForStructure(): void
    {
        [$terminals, $entries] = (new MySqlCatalogWitnesses())->forStructure([], Grammar::load('mysql-8.4.7'), 'mysql-8.4.7');

        self::assertContains('END_OF_INPUT', $entries);
        self::assertSame(['mysql.punctuation.' . bin2hex('!')], array_column($terminals['!'], 'id'));
    }

    public function testForStructureWitnessesTheCubeKeywordOnlyOnTheVersionsThatSpellIt(): void
    {
        [$terminals] = (new MySqlCatalogWitnesses())->forStructure([], Grammar::load('mysql-5.7.44'), 'mysql-5.7.44');

        self::assertSame(['mysql.parser.WITH_CUBE_SYM'], array_column($terminals['WITH_CUBE_SYM'], 'id'));
    }
}
