<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\SyntaxStorageOptions;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;

#[CoversClass(SyntaxStorageOptions::class)]
#[Medium]
final class SyntaxStorageOptionsTest extends TestCase
{
    public function testCompletedDistinguishesLegacyReadingFromModernStatementChecks(): void
    {
        $sql = 'DROP TABLESPACE t ENGINE InnoDB ENGINE InnoDB broken';
        $legacy = new LanguageProfile(GrammarRelease::MySql5744);
        $modern = new LanguageProfile(GrammarRelease::MySql847);
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($legacy->grammar->value), $sql, strlen($sql), $legacy);

        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertInstanceOf(StorageProblem::class, $notices[0][1]->problem);
        self::assertSame('ENGINE', $notices[0][1]->problem->option);
        self::assertSame([], (new SyntaxWarnings())->completed(new MySqlParser($modern->grammar->value), $sql, strlen($sql), $modern));
    }
}
