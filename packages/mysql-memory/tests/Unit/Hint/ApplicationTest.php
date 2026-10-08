<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\Application;
use MySqlMemory\Hint\Blocks;
use MySqlMemory\Hint\Printer;
use MySqlMemory\Hint\Registration;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Application::class)]
#[Small]
final class ApplicationTest extends TestCase
{
    public function testApplySetsTheVariablesAndWarnsAboutRefusedValues(): void
    {
        $session = (new Instance())->connect();
        $registration = new Registration(new Blocks($session->instance->dictionary, ''), $session->variables->catalog, new Printer());
        $registration->group = new ResourceGroupHint('SYS_default');
        $registration->variables = [
            'sort_buffer_size' => new VariableHint('sort_buffer_size', new HintLiteral(HintLiteralKind::Integer, '1')),
            'unique_checks' => new VariableHint('unique_checks', new HintLiteral(HintLiteralKind::Word, 'OFF')),
            'sql_mode' => new VariableHint('sql_mode', new HintLiteral(HintLiteralKind::Word, 'NOPE')),
        ];
        $application = (new Application())->apply($registration, $session);

        self::assertSame([
            [3661, "Unable to bind resource group SYS_default with thread id (1).(System resource group can't be bound with a session thread)."],
            [1292, "Truncated incorrect sort_buffer_size value: '1'"],
            [1231, "Variable 'sql_mode' can't be set to the value of 'NOPE'"],
        ], $application->warnings);
        self::assertSame([32768, 'OFF'], [$session->variables->read('sort_buffer_size'), $session->variables->read('unique_checks')]);
    }

    public function testApplyKeepsOnlyTheValueWarningsOfAPreparedStatement(): void
    {
        $session = (new Instance())->connect();
        $registration = new Registration(new Blocks($session->instance->dictionary, ''), $session->variables->catalog, new Printer());
        $registration->warnings = [[3126, 'Hint X() is ignored as conflicting/duplicated']];
        $registration->group = new ResourceGroupHint('nope');

        self::assertSame([[3651, "Resource Group 'nope' does not exist."]], (new Application())->apply($registration, $session, true)->warnings);
    }

    public function testReportRaisesTheWarnings(): void
    {
        $session = (new Instance())->connect();
        $application = new Application();
        $application->warnings = [[3125, 'MAX_EXECUTION_TIME hint is supported by top-level standalone SELECT statements only']];
        $application->report($session);

        self::assertSame([['Warning', 3125, 'MAX_EXECUTION_TIME hint is supported by top-level standalone SELECT statements only']], $session->diagnostics->conditions);
    }

    public function testRestoreGivesTheVariablesTheirSessionValuesBack(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET SESSION sort_buffer_size = 100000');
        $registration = new Registration(new Blocks($session->instance->dictionary, ''), $session->variables->catalog, new Printer());
        $registration->variables = [
            'sort_buffer_size' => new VariableHint('sort_buffer_size', new HintLiteral(HintLiteralKind::Integer, '200000')),
            'div_precision_increment' => new VariableHint('div_precision_increment', new HintLiteral(HintLiteralKind::Integer, '10')),
        ];
        $application = (new Application())->apply($registration, $session);
        $during = [$session->variables->read('sort_buffer_size'), $session->variables->read('div_precision_increment')];
        $application->restore($session);

        self::assertSame([[200000, 10], [100000, 4], false], [$during, [$session->variables->read('sort_buffer_size'), $session->variables->read('div_precision_increment')], array_key_exists('div_precision_increment', $session->variables->session)]);
    }

    public function testValueAnswersTheValueAndItsType(): void
    {
        [$integer, $integral] = Application::value(new HintLiteral(HintLiteralKind::Integer, '18446744073709551615'));
        [$decimal, $fixed] = Application::value(new HintLiteral(HintLiteralKind::Decimal, '1.50'));
        [$word, $text] = Application::value(new HintLiteral(HintLiteralKind::Word, 'ON'));

        self::assertSame(['18446744073709551615', Kind::Integer, '1.50', 2, 'ON', Kind::String], [$integer, $integral->kind, $decimal, $fixed->decimals, $word, $text->kind]);
        self::assertSame(7, Application::value(new HintLiteral(HintLiteralKind::Integer, '7'))[0]);
    }
}
