<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\SyntaxOptions;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\ReadOnlyConflict;

#[CoversClass(SyntaxOptions::class)]
#[Medium]
final class SyntaxOptionsTest extends TestCase
{
    public function testRejectedRetainsTheCompleteEncryptionOperand(): void
    {
        $value = str_repeat('a', 200);
        $sql = "ALTER DATABASE d ENCRYPTION '" . $value . "'";
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $failure = (new SyntaxWarnings())->rejected($tree, new LanguageProfile(GrammarRelease::MySql847));

        self::assertNotNull($failure);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption::class, $failure->problem);
        self::assertSame($value, $failure->problem->value->value);
        self::assertSame(strlen($sql), $failure->end);
    }

    public function testCompletedPreservesTheFirstConflictingOptionBeforeLaterRefusal(): void
    {
        $sql = 'ALTER DATABASE d READ ONLY 1 READ ONLY 0 +';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql) - 1, new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new ReadOnlyConflict(), true)], array_column($notices, 1));
    }
}
