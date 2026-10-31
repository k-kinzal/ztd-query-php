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
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\VariablePrefix;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(VariablePrefix::class)]
#[Medium]
final class VariablePrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPrefixes(): iterable
    {
        yield 'one list' => ['DECLARE x,X INT COLLATE binary;', 'X'];
        yield 'previous declaration' => ['DECLARE x INT; DECLARE X INT COLLATE binary;', 'X'];
        yield 'closed nested scope' => ['DECLARE x INT; BEGIN DECLARE y INT; END; BEGIN DECLARE y,Y INT COLLATE binary; END;', 'Y'];
    }

    #[DataProvider('providerPrefixes')]
    public function testObserveKeepsDuplicateNamesAheadOfTypesAndLaterSyntax(string $body, string $name): void
    {
        $sql = 'CREATE PROCEDURE p() BEGIN ' . $body . ' SELECT END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new ProgramProblem(ProgramRule::DuplicateVariable, $name), true)], array_column($notices, 1));
    }

    public function testObserveAllowsShadowingWithoutPublishingAnOuterDuplicate(): void
    {
        $sql = 'CREATE PROCEDURE p() BEGIN DECLARE x INT; BEGIN DECLARE x INT; END; BEGIN DECLARE x INT; END; SELECT END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([], $notices);
    }
}
