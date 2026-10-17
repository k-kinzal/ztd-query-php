<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Pattern\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Mode::class)]
#[Small]
final class ModeTest extends TestCase
{
    public function testOfIgnoresCaseInACaseInsensitiveCollationUnlessMatchTypeSaysOtherwise(): void
    {
        self::assertSame(
            [true, false, false, true],
            [
                Mode::of(Collation::known('utf8mb4_0900_ai_ci'), '', 'regexp_like')->caseless,
                Mode::of(Collation::known('utf8mb4_0900_ai_ci'), 'ic', 'regexp_like')->caseless,
                Mode::of(Collation::known('utf8mb4_bin'), '', 'regexp_like')->caseless,
                Mode::of(Collation::binary(), 'ci', 'regexp_like')->caseless,
            ],
        );
    }

    public function testOfReadsTheLineFlags(): void
    {
        $mode = Mode::of(Collation::binary(), 'mnu', 'regexp_like');

        self::assertSame([true, true, true], [$mode->multiline, $mode->dotAll, $mode->unixLines]);
    }

    public function testOfRefusesALetterThatIsNoFlag(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to regexp_instr');

        Mode::of(Collation::binary(), 'cx', 'regexp_instr');
    }

    public function testWithSetsAndClearsAnInlineFlag(): void
    {
        $mode = (new Mode())->with('x', true)->with('w', true)->with('s', true)->with('s', false);

        self::assertSame([true, true, false], [$mode->comments, $mode->words, $mode->dotAll]);
    }

    public function testKeyTellsModesApart(): void
    {
        self::assertSame(['000000', '100100'], [(new Mode())->key(), (new Mode(true, false, false, true))->key()]);
    }
}
