<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\HandlerPrefix;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(HandlerPrefix::class)]
#[Medium]
final class HandlerPrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ProgramRule|null, string|null}>
     */
    public static function providerConditions(): iterable
    {
        yield 'same general class' => ['DECLARE EXIT HANDLER FOR SQLEXCEPTION,SQLWARNING,SQLEXCEPTION SET @x=?;', ProgramRule::DuplicateHandler, null];
        yield 'code conversion' => ['DECLARE EXIT HANDLER FOR 1,4294967297 SET @x=?;', ProgramRule::DuplicateHandler, null];
        yield 'sqlstate' => ["DECLARE EXIT HANDLER FOR SQLSTATE '45000',SQLSTATE '45000' SET @x=?;", ProgramRule::DuplicateHandler, null];
        yield 'declared alias' => ['DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR c,1 SET @x=?;', ProgramRule::DuplicateHandler, null];
        yield 'undeclared alias' => ['DECLARE EXIT HANDLER FOR c,SQLEXCEPTION,SQLEXCEPTION SET @x=?;', ProgramRule::UndefinedCondition, 'c'];
        yield 'earlier handler' => ['DECLARE EXIT HANDLER FOR 1 BEGIN END; DECLARE EXIT HANDLER FOR 1 SET @x=?;', ProgramRule::DuplicateHandler, null];
        yield 'enclosing alias' => ['DECLARE c CONDITION FOR 1; BEGIN DECLARE EXIT HANDLER FOR c,1 SET @x=?; END;', ProgramRule::DuplicateHandler, null];
        yield 'shadowed alias' => ['DECLARE c CONDITION FOR 1; BEGIN DECLARE c CONDITION FOR 2; DECLARE EXIT HANDLER FOR c,1 SET @x=?; END;', null, null];
        yield 'outer handler does not conflict' => ['DECLARE EXIT HANDLER FOR 1 BEGIN END; BEGIN DECLARE EXIT HANDLER FOR 1 SET @x=?; END;', null, null];
        yield 'inner handler does not escape' => ['DECLARE EXIT HANDLER FOR 1 BEGIN DECLARE EXIT HANDLER FOR 2 BEGIN END; END; DECLARE EXIT HANDLER FOR 2 SET @x=?;', null, null];
    }

    #[DataProvider('providerConditions')]
    public function testObserveKeepsLexicalIdentityBeforeAnUnfinishedBody(string $body, ?ProgramRule $rule, ?string $name): void
    {
        $sql = 'CREATE PROCEDURE d.p() BEGIN ' . $body . ' END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals($rule === null ? [] : [new ParseFailure(new ProgramProblem($rule, $name), true)], array_column($notices, 1));
    }

    public function testConditionsUsesTheOrdinaryNumericIdentity(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions(new LanguageProfile(GrammarRelease::MySql847)), new \SqlSemantics\Lowering\Leaves(), new LanguageProfile(GrammarRelease::MySql847));
        $code = new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1'));
        [$handled, $failure] = (new HandlerPrefix())->conditions([$code, $code], $lowering);

        self::assertSame([$code], $handled);
        self::assertEquals(new ParseFailure(new ProgramProblem(ProgramRule::DuplicateHandler), true), $failure);
    }
}
