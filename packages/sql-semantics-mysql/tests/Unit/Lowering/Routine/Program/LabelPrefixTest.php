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
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\LabelPrefix;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(LabelPrefix::class)]
#[Medium]
final class LabelPrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ProgramRule|null}>
     */
    public static function providerPrefixes(): iterable
    {
        yield 'unfinished leave' => ['a: BEGIN LEAVE á', ProgramRule::LeaveWithoutLabel];
        yield 'end label' => ['a: BEGIN END á', ProgramRule::EndLabelMismatch];
        yield 'block iteration' => ['a: BEGIN ITERATE a', ProgramRule::IterateWithoutLabel];
        yield 'loop iteration' => ['a: LOOP ITERATE a', null];
        yield 'duplicate' => ['a: BEGIN a: BEGIN', ProgramRule::RedefinedLabel];
        yield 'siblings' => ['BEGIN a: BEGIN END a; a: BEGIN END a; END', null];
        yield 'handler isolation' => ['a: BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION LEAVE a', ProgramRule::LeaveWithoutLabel];
        yield 'handler own scope' => ['a: BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION,SQLWARNING b: BEGIN LEAVE b; END b; LEAVE a; END a', null];
    }

    #[DataProvider('providerPrefixes')]
    public function testObserveRetainsTheFirstCompletedLabelCondition(string $body, ?ProgramRule $rule): void
    {
        $sql = 'CREATE PROCEDURE d.p() ' . $body;
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44', clientCharset: 'latin1'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql5744), client: Charset::known('latin1'));
        $errors = array_values(array_filter(array_column($notices, 1), static fn ($notice): bool => $notice instanceof ParseFailure));
        self::assertCount($rule === null ? 0 : 1, $errors);
        $problem = $errors[0]->problem ?? null;
        self::assertSame($rule, $problem instanceof RefusedProgramBody && $problem->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem ? $problem->problem->rule : null);
    }

    public function testClosingIgnoresAnUnrelatedReduction(): void
    {
        $parser = new MySqlParser();
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering((new \SqlSemantics\Platform\MySql\Platform())->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        self::assertNull((new LabelPrefix())->closing($parser->parse('SELECT 1'), $lowering));
    }

    public function testHandlerAndClosingIgnoreUnrelatedReductions(): void
    {
        $prefix = new LabelPrefix();
        $parser = new MySqlParser();
        $tree = $parser->parse('SELECT 1');
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $prefix->handler($tree);
        self::assertNull($prefix->closing($tree, $lowering));
    }
}
