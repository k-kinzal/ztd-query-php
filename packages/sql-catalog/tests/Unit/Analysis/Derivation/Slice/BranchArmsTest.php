<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation\Slice;

use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\Slice\BranchArms;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\SourceParser;

#[CoversClass(BranchArms::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(SourceParser::class)]
final class BranchArmsTest extends TestCase
{
    public function testOfKeepsOnlyTheArmsThatFallThrough(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php if ($a) { return; } elseif ($b) { $x = 1; } $y = 1;');
        $arms = new BranchArms();

        self::assertCount(2, $arms->of($file->statements[0]) ?? []);
        self::assertNull($arms->of($file->statements[1]));
    }

    public function testOfKeepsEveryArmWhenNoneFallsThrough(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php if ($a) { return 1; } else { return 2; }');

        self::assertCount(2, (new BranchArms())->of($file->statements[0]) ?? []);
    }

    public function testIfArmsIncludeTheArmTakenWhenNoConditionHolds(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php if ($a) { $x = 1; } elseif ($b) { $x = 2; }');
        $if = $file->statements[0];
        self::assertInstanceOf(Stmt\If_::class, $if);

        $arms = (new BranchArms())->ifArms($if);

        self::assertSame([1, 1, 0], array_map('count', $arms));
    }

    public function testSwitchArmsDropTheirBreakAndAddTheArmTakenWhenNoCaseMatches(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php switch ($a) { case 1: $x = 1; break; case 2: $x = 2; }');
        $switch = $file->statements[0];
        self::assertInstanceOf(Stmt\Switch_::class, $switch);

        $arms = (new BranchArms())->switchArms($switch);

        self::assertSame([1, 1, 0], array_map('count', $arms));
    }

    public function testSwitchArmsWithADefaultHaveNoEmptyArm(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php switch ($a) { default: $x = 1; }');
        $switch = $file->statements[0];
        self::assertInstanceOf(Stmt\Switch_::class, $switch);

        self::assertCount(1, (new BranchArms())->switchArms($switch));
    }

    public function testTryArmsFollowEachWayOutWithTheFinallyBlock(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php try { $a = 1; } catch (E $e) { $a = 2; } finally { $b = 1; }');
        $try = $file->statements[0];
        self::assertInstanceOf(Stmt\TryCatch::class, $try);

        $arms = (new BranchArms())->tryArms($try);

        self::assertSame([2, 2], array_map('count', $arms));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerCompletes(): array
    {
        return [
            ['$a = 1;', true],
            ['', true],
            ['return 1;', false],
            ['throw new E();', false],
            ['exit;', false],
            ['while (1) { break; }', true],
            ['foreach ($a as $b) { continue; }', true],
        ];
    }

    #[DataProvider('providerCompletes')]
    public function testCompletesSaysWhetherTheNextStatementCanRun(string $code, bool $expected): void
    {
        $statements = (new SourceParser())->parse('t.php', '<?php ' . $code)->statements;

        self::assertSame($expected, (new BranchArms())->completes($statements));
    }

    public function testCompletesIsFalseForARunEndingInABreakOrAContinue(): void
    {
        $arms = new BranchArms();

        self::assertFalse($arms->completes([new Stmt\Break_()]));
        self::assertFalse($arms->completes([new Stmt\Continue_()]));
        self::assertFalse($arms->completes([new Stmt\Goto_('a')]));
    }
}
