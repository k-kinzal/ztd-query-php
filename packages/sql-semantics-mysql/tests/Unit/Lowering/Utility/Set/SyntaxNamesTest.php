<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\InputLocals;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\SyntaxNames;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;

#[CoversClass(SyntaxNames::class)]
#[Medium]
final class SyntaxNamesTest extends TestCase
{
    public function testAssignmentRetainsPositionsWithoutAnExpression(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-8.4.7.php'), new Leaves(), $profile);
        $node = (new MySqlParser('mysql-8.4.7'))->parse('SET NAMES = missing')->find('option_value_no_option_type')[0];
        $failure = (new SyntaxNames())->assignment($node, $lowering);

        self::assertNotNull($failure);
        self::assertInstanceOf(RefusedNamesAssignment::class, $failure->problem);
        self::assertSame([10, 19, false], [$failure->problem->offset, $failure->problem->end, $failure->aborts]);
    }

    public function testCompletedWaitsForTheModernAssignmentList(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-8.4.7.php'), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('SET NAMES = !1, @a = !2');
        $rule = new SyntaxNames();

        self::assertNull($rule->completed($tree->find('option_value_no_option_type')[0], $lowering));
        $notice = $rule->completed($tree->find('set')[0], $lowering);
        self::assertNotNull($notice);
        self::assertTrue($notice->aborts);
        self::assertSame(NoticePhase::Contextualization, $notice->phase);
    }

    public function testPrecedingUsesOrdinaryVariableLookup(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-8.4.7.php'), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('SET @@missing_var = 0, NAMES = 1');
        $notice = (new SyntaxNames())->preceding($tree->find('option_value_no_option_type')[0], $lowering, new InputLocals());

        self::assertNotNull($notice);
        self::assertInstanceOf(UnknownSystemVariable::class, $notice->problem);
        self::assertSame('missing_var', $notice->problem->name);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerScopes(): iterable
    {
        yield 'parameter' => ['CREATE PROCEDURE p(names INT) SET NAMES=1', true];
        yield 'local' => ['CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES=1; END', true];
        yield 'outer local' => ['CREATE PROCEDURE p() BEGIN DECLARE names INT; BEGIN SET NAMES=1; END; END', true];
        yield 'closed local' => ['CREATE PROCEDURE p() BEGIN BEGIN DECLARE names INT; END; SET NAMES=1; END', false];
        yield 'condition' => ["CREATE PROCEDURE p() BEGIN DECLARE names CONDITION FOR SQLSTATE '45000'; SET NAMES=1; END", false];
    }

    #[DataProvider('providerScopes')]
    public function testScopedRetainsOnlyVisibleVariableDeclarations(string $sql, bool $shadowed): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-8.4.7.php'), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $rule = new SyntaxNames();
        $failure = $rule->assignment($tree->find('option_value_no_option_type')[0], $lowering);
        self::assertNotNull($failure);
        self::assertInstanceOf(RefusedNamesAssignment::class, $failure->problem);

        self::assertSame($shadowed, $rule->scoped($tree, $failure->problem, $lowering)->shadowed);
    }
}
