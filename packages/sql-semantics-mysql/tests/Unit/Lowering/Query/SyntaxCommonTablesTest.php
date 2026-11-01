<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Query\SyntaxCommonTables;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SyntaxCommonTables::class)]
#[Medium]
final class SyntaxCommonTablesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function providerDefinitions(): iterable
    {
        yield 'completed duplicate' => ['WITH x AS (SELECT 1), x AS (SELECT 2) SELECT ?', 'x'];
        yield 'quoted name' => ['WITH `x``y` AS (SELECT 1), `x``y` AS (SELECT 2) SELECT ?', 'x`y'];
        yield 'separate case' => ['WITH x AS (SELECT 1), X AS (SELECT 2) SELECT ?', null];
        yield 'incomplete duplicate' => ['WITH x AS (SELECT 1), x AS (SELECT ?) SELECT 1', null];
        yield 'nested duplicate' => ['WITH x AS (WITH y AS (SELECT 1), y AS (SELECT 2) SELECT 1) SELECT ?', 'y'];
        yield 'separate scope' => ['WITH x AS (SELECT 1), y AS (WITH x AS (SELECT 2) SELECT 1) SELECT ?', null];
        yield 'recursive' => ['WITH RECURSIVE x AS (SELECT 1), x AS (SELECT 2) SELECT ?', 'x'];
        yield 'stops after duplicate' => ['WITH x AS (SELECT 1), x AS (SELECT 2), y AS (SELECT BINARY 3) SELECT ?', 'x'];
    }

    #[DataProvider('providerDefinitions')]
    public function testCompletedRetainsTheNameWithoutItsQuery(string $sql, ?string $name): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '?'), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals($name === null ? [] : [new ParseFailure(new Misuse(MisuseRule::DuplicateCommonTable, new Name($name)), true)], array_column($notices, 1));
    }
}
