<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(VariableHint::class)]
#[Small]
final class VariableHintTest extends TestCase
{
    public function testNameAnswersSetVar(): void
    {
        self::assertSame(HintName::SetVar, (new VariableHint('sql_mode', new HintLiteral(HintLiteralKind::Text, 'ANSI')))->name());
    }

    public function testTextWritesTheVariableAndTheValue(): void
    {
        self::assertSame("SET_VAR(`sql_mode` = 'ANSI')", (new VariableHint('sql_mode', new HintLiteral(HintLiteralKind::Text, 'ANSI')))->text());
        self::assertSame('SET_VAR(`div_precision_increment` = 10)', (new VariableHint('div_precision_increment', new HintLiteral(HintLiteralKind::Integer, '10')))->text());
    }

    public function testAnEmptyVariableIsRefused(): void
    {
        $this->expectExceptionMessage('A variable has a name.');

        new VariableHint('', new HintLiteral(HintLiteralKind::Integer, '1'));
    }
}
