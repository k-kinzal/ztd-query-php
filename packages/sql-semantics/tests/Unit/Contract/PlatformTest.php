<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\Platform;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Platform::class)]
#[Medium]
final class PlatformTest extends TestCase
{
    public function testProfileFixesTheDefaultReleaseOfTheDatabase(): void
    {
        $profile = Platforms::of('sqlite')->profile(null, null, ParameterStyle::Native);

        self::assertSame(GrammarRelease::Sqlite3472, $profile->grammar);
        self::assertSame(ParameterStyle::Native, $profile->parameters);
    }

    public function testProfileRefusesAReleaseTheDatabaseDoesNotShip(): void
    {
        $this->expectExceptionMessage('sqlite');

        Platforms::of('sqlite')->profile('0.0.1', null, ParameterStyle::Native);
    }

    public function testParserAnswersTheSameParserForOneProfile(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        self::assertSame($platform->parser($profile), $platform->parser($profile));
    }

    public function testLowerTurnsAParseTreeIntoStatementsAndRecordsTheLeaves(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $leaves = new Leaves();

        $statements = $platform->lower($platform->parser($profile)->parse('SELECT a FROM t; SELECT 2'), $profile, $leaves);

        self::assertCount(2, $statements);
        self::assertInstanceOf(Select::class, $statements[0]);
        self::assertCount(1, $statements[0]->columns);
        self::assertCount(3, $leaves->all());
    }

    public function testProductionsSpellTheProductionOfARootNode(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        $tree = $platform->parser($profile)->parse('SELECT 1');

        self::assertSame('input: cmdlist', $platform->productions($profile)->signature($tree));
    }

    public function testCodecSpellsNamesOfTheProfile(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        self::assertSame('`a b`', $platform->codec($profile)->name(new Name('a b'), NameUse::Column));
    }

    public function testLeafKeysKeyTokensOfTheProfile(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $token = $platform->parser($profile)->tokenize("SELECT 'x'")[1];

        self::assertSame('string:x', $platform->leafKeys($profile)->key($token, 'term: STRING', 0));
    }

    public function testContextAppliesTheNameRulesOfTheDatabase(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        $context = $platform->context($profile, null, [], false);

        self::assertSame(Comparison::AsciiInsensitive, $context->columnNames);
        self::assertSame(['temp', 'main'], array_map(static fn (Name $name): string => $name->value, $context->searchPath));
        self::assertFalse($context->complete);
    }

    public function testStatementNamespaceClosesTheValueDomainOfTheDatabase(): void
    {
        self::assertSame('SqlSemantics\\Platform\\Sqlite\\Statement\\', Platforms::of('sqlite')->statementNamespace());
        self::assertStringStartsWith(Platforms::of('sqlite')->statementNamespace(), Select::class);
    }
}
