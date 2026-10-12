<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Faker\Factory;
use Fuzz\Target\ParsedCoverage;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\MySql\MySqlProvider;
use SqlParser\MySql\MySqlParser;

#[CoversNothing]
#[Medium]
final class ParsedCoverageTest extends TestCase
{
    public function testIdsDistinguishesActualFunctionSyntaxFromAnIdentifierWithTheSameSpelling(): void
    {
        $coverage = new GrammarCoverage();
        $provider = new MySqlProvider(Factory::create(), 'mysql-8.4.7', $coverage);
        $provider->planner();
        $parsed = new ParsedCoverage(new MySqlParser('mysql-8.4.7'), $coverage->inventory());
        $label = static fn (string $id): string => explode(':', $id)[1];

        self::assertContains('function_call_nonkeyword#18', array_map($label, $parsed->ids('SELECT SYSDATE()')));
        self::assertNotContains('function_call_nonkeyword#18', array_map($label, $parsed->ids('SELECT SYSDATE')));
        self::assertNotContains('ident_keywords_unambiguous#3', array_map($label, $parsed->ids("ALTER LOGFILE GROUP ADDDATE ADD UNDOFILE 'text'")));
    }

    public function testIdsKeepsTheRoleNameSeparateFromItsHostname(): void
    {
        $coverage = new GrammarCoverage();
        $provider = new MySqlProvider(Factory::create(), 'mysql-8.4.7', $coverage);
        $provider->planner();
        $parsed = new ParsedCoverage(new MySqlParser('mysql-8.4.7'), $coverage->inventory());
        $label = static fn (string $id): string => explode(':', $id)[1];
        $ids = array_map($label, $parsed->ids('SET ROLE mm_lexical_probe@invalid.example'));

        self::assertContains('role_ident_or_text#0', $ids);
        self::assertContains('ident_or_text#2', $ids);
        self::assertNotContains('role_ident_or_text#2', $ids);
    }

    public function testIdsMeasuresReplacementTextIndependentlyOfDerivationProvenance(): void
    {
        $coverage = new GrammarCoverage();
        $provider = new MySqlProvider(Factory::create(), 'mysql-8.4.7', $coverage);
        $provider->planner();
        $parsed = new ParsedCoverage(new MySqlParser('mysql-8.4.7'), $coverage->inventory());
        $ids = $parsed->ids('SELECT 1 + 2');
        $arithmetic = array_filter($coverage->inventory()->entries, static fn (array $entry): bool => $entry['rule'] === 'bit_expr' && $entry['rhs'] === ['N:bit_expr', 'T:+', 'N:bit_expr']);

        self::assertCount(1, $arithmetic);
        self::assertContains(array_key_first($arithmetic), $ids);
        self::assertSame(count($ids), count(array_unique($ids)));
    }
}
