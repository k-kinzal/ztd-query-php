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
use SqlSemantics\Platform\MySql\Lowering\Query\SyntaxAliases;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;

#[CoversClass(SyntaxAliases::class)]
#[Medium]
final class SyntaxAliasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerAliases(): iterable
    {
        yield 'missing' => ["SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS(n FOR ORDINALITY)) WHERE ?", true];
        yield 'empty' => ["SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS(n FOR ORDINALITY)) AS `` WHERE ?", true];
        yield 'quoted' => ["SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS(n FOR ORDINALITY)) AS `j``n` WHERE ?", false];
        yield 'named' => ["SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS(n FOR ORDINALITY)) j WHERE ?", false];
        yield 'incomplete' => ["SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS(n FOR ?", false];
        yield 'unreachable body' => ["CREATE PROCEDURE p() BEGIN IF 0 THEN SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS(n FOR ORDINALITY)); END IF; SELECT ?; END", true];
    }

    #[DataProvider('providerAliases')]
    public function testCompletedRefusesTheAliasBeforeLaterOperands(string $sql, bool $refused): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '?'), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals($refused ? [new ParseFailure(new Misuse(MisuseRule::TableFunctionWithoutAlias), true)] : [], array_column($notices, 1));
    }
}
