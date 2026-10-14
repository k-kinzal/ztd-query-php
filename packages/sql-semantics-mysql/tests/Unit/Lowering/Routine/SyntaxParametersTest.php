<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\SyntaxParameters;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(SyntaxParameters::class)]
#[Medium]
final class SyntaxParametersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDuplicates(): iterable
    {
        yield 'procedure 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p(a INT, A INT) nonsense'];
        yield 'function 8.4' => ['mysql-8.4.7', 'CREATE FUNCTION f(a INT, A INT) RETURNS VARCHAR RETURN 1'];
        yield 'accent 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p(`á` INT, A INT) nonsense'];
    }

    #[DataProvider('providerDuplicates')]
    public function testCompletedKeepsTheDuplicateBeforeLaterSyntax(string $release, string $sql): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release), $sql, strlen($sql), new LanguageProfile(GrammarRelease::from($release)));

        self::assertEquals([new ParseFailure(new ProgramProblem(ProgramRule::DuplicateParameter, 'A'), true)], array_column($notices, 1));
    }

    public function testCompletedAcceptsDistinctParameters(): void
    {
        $sql = 'CREATE PROCEDURE p(a INT, b INT) BEGIN END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([], $notices);
    }
}
