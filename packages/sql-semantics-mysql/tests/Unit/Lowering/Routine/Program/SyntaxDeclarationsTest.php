<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\SyntaxDeclarations;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(SyntaxDeclarations::class)]
#[Medium]
final class SyntaxDeclarationsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ProgramRule, string|null}>
     */
    public static function providerConflicts(): iterable
    {
        yield 'variable 5.6' => ['mysql-5.6.51', 'DECLARE x INT; DECLARE X INT;', ProgramRule::DuplicateVariable, 'X'];
        yield 'condition 5.7' => ['mysql-5.7.44', "DECLARE c CONDITION FOR SQLSTATE '45000'; DECLARE C CONDITION FOR 1;", ProgramRule::DuplicateCondition, 'C'];
        yield 'cursor 8.0' => ['mysql-8.0.44', 'DECLARE c CURSOR FOR SELECT 1; DECLARE C CURSOR FOR SELECT 2;', ProgramRule::DuplicateCursor, 'C'];
        yield 'variable after cursor 8.4' => ['mysql-8.4.7', 'DECLARE c CURSOR FOR SELECT 1; DECLARE x INT;', ProgramRule::DeclarationAfterCursorOrHandler, null];
        yield 'handler 9.1' => ['mysql-9.1.0', 'DECLARE CONTINUE HANDLER FOR 1 BEGIN END; DECLARE CONTINUE HANDLER FOR 1 BEGIN END;', ProgramRule::DuplicateHandler, null];
    }

    #[DataProvider('providerConflicts')]
    public function testCompletedKeepsLocalConflictsBeforeMalformedBody(string $release, string $declarations, ProgramRule $rule, ?string $name): void
    {
        $sql = 'CREATE PROCEDURE d.p() BEGIN ' . $declarations . ' PARTITION BROKEN; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release), $sql, strlen($sql), new LanguageProfile(GrammarRelease::from($release)));

        self::assertEquals([new ParseFailure(new ProgramProblem($rule, $name), true)], array_column($notices, 1));
    }

    public function testCompletedPreservesNestedScopesAndOuterConditionReferences(): void
    {
        $sql = 'CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE c CONDITION FOR 1; BEGIN DECLARE x INT; DECLARE CONTINUE HANDLER FOR c BEGIN END; PARTITION BROKEN; END; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([], $notices);
    }
}
