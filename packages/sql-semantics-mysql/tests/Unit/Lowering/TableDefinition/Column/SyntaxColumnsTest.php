<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column\SyntaxColumns;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute;

#[CoversClass(SyntaxColumns::class)]
#[Medium]
final class SyntaxColumnsTest extends TestCase
{
    public function testObserveRetainsTheDeclarationTargetWithoutReplacingItWithAReference(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('CREATE TABLE d.t(a INT REFERENCES other.p(id))');
        (new SyntaxColumns())->observe($tree->find('table_ident')[0], $lowering, $tree->tokens());
        (new SyntaxColumns())->observe($tree->find('table_ident')[1], $lowering, $tree->tokens());
        self::assertSame('t', $lowering->columnTable?->name->value);
        self::assertSame('d', $lowering->columnTable->schema?->value);
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerLegacyReleases(): iterable
    {
        yield '5.6' => [GrammarRelease::MySql5651];
        yield '5.7' => [GrammarRelease::MySql5744];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyReleases')]
    public function testCompletedKeepsTheLegacyColumnBoundary(GrammarRelease $release): void
    {
        $sql = 'CREATE TABLE d.t(a BIT DEFAULT CURRENT_TIMESTAMP, BOGUS)';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));
        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertInstanceOf(InvalidColumnAttribute::class, $notices[0][1]->problem);
        self::assertSame('d', $notices[0][1]->problem->scope?->schema?->value);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyReleases')]
    public function testAttributesKeepALegacyAlterationBeforeLaterMalformedInstructions(GrammarRelease $release): void
    {
        $sql = 'CREATE PROCEDURE d.p() BEGIN IF 0 THEN ALTER TABLE t MODIFY a BIT AUTO_INCREMENT; END IF; BOGUS; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));
        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertInstanceOf(InvalidColumnAttribute::class, $notices[0][1]->problem);
        self::assertSame('AUTO_INCREMENT', $notices[0][1]->problem->attribute);
        self::assertSame('d', $notices[0][1]->problem->scope?->schema?->value);
    }

    public function testCompletedDoesNotRepeatAnInnerCastRefusalAtTableCompletion(): void
    {
        $sql = 'CREATE TABLE d.t(a INT, PRIMARY KEY ((CAST(1 AT LOCAL AS SIGNED))))';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertSame("This version of MySQL doesn't yet support 'AT LOCAL'.", $notices[0][1]->problem->message());
    }

    public function testCompletedRequiresTheModernStatementBeforeItsColumnCondition(): void
    {
        $sql = 'CREATE TABLE d.t(a BIT DEFAULT CURRENT_TIMESTAMP, BOGUS)';
        self::assertSame([], (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847)));
    }

    public function testCompletedRetainsTheProgramDatabaseForAnUnreachableInstruction(): void
    {
        $sql = 'CREATE PROCEDURE d.p() BEGIN IF 0 THEN ALTER TABLE t MODIFY a BIT AUTO_INCREMENT; END IF; BOGUS; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertInstanceOf(InvalidColumnAttribute::class, $notices[0][1]->problem);
        self::assertSame('d', $notices[0][1]->problem->scope?->schema?->value);
        self::assertSame('p', $notices[0][1]->problem->scope->name->value);
    }
}
