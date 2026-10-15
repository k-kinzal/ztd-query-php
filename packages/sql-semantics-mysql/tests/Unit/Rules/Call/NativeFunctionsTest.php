<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;

#[CoversClass(NativeFunctions::class)]
#[Small]
final class NativeFunctionsTest extends TestCase
{
    public function testExistsFollowsTheRelease(): void
    {
        $natives = new NativeFunctions();

        self::assertTrue($natives->exists(GrammarRelease::MySql5744, 'json_array'));
        self::assertFalse($natives->exists(GrammarRelease::MySql5651, 'json_array'));
        self::assertTrue($natives->exists(GrammarRelease::MySql5744, 'GLength'));
        self::assertFalse($natives->exists(GrammarRelease::MySql847, 'GLength'));
        self::assertFalse($natives->exists(GrammarRelease::Sqlite3472, 'concat'));
    }

    public function testRowAnswersTheRowThatAcceptsTheArgumentCount(): void
    {
        $natives = new NativeFunctions();

        self::assertSame([1, -1, 'SY', false], $natives->row(GrammarRelease::MySql847, 'Concat', 3));
        self::assertNull($natives->row(GrammarRelease::MySql847, 'ABS', 2));
        self::assertSame([1, 1, 'EY', false], $natives->row(GrammarRelease::MySql847, 'from_unixtime', 1));
        self::assertSame([2, 2, 'TY', false], $natives->row(GrammarRelease::MySql847, 'from_unixtime', 2));
    }

    public function testRowsMarksTheFunctionsReservedForTheDataDictionary(): void
    {
        self::assertSame([[8, 9, 'IY', true]], (new NativeFunctions())->rows(GrammarRelease::MySql847, 'internal_table_rows'));
        self::assertSame([], (new NativeFunctions())->rows(GrammarRelease::MySql847, 'score'));
    }
    public function testPossibleKeepsOnlySignaturesWithALanguageInterpretation(): void
    {
        $natives = new NativeFunctions();
        $abs = new \SqlSemantics\Statement\Identifier\Name('abs');

        self::assertTrue($natives->possible($abs, 1, false));
        self::assertFalse($natives->possible($abs, 0, false));
        self::assertFalse($natives->possible($abs, 2, false));
        self::assertFalse($natives->possible($abs, 1, true));
        self::assertTrue($natives->possible(new \SqlSemantics\Statement\Identifier\Name('regexp_like'), 0, false));
        self::assertTrue($natives->possible(new \SqlSemantics\Statement\Identifier\Name('x'), 0, false));
        self::assertTrue($natives->possible(new \SqlSemantics\Statement\Identifier\Name('stored_function'), 0, true));
    }

    public function testProblemPreservesReleaseDependentNativeSignatures(): void
    {
        $natives = new NativeFunctions();
        $name = new \SqlSemantics\Statement\Identifier\Name('AES_ENCRYPT');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount::class, $natives->problem(GrammarRelease::MySql5651, $name, 4, false));
        self::assertNull($natives->problem(GrammarRelease::MySql847, $name, 4, false));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument::class, $natives->problem(GrammarRelease::MySql847, $name, 4, true));
        self::assertNull($natives->problem(GrammarRelease::MySql5651, new \SqlSemantics\Statement\Identifier\Name('regexp_like'), 0, false));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction::class, $natives->problem(GrammarRelease::MySql847, new \SqlSemantics\Statement\Identifier\Name('internal_table_rows'), 0, true));
    }

}
