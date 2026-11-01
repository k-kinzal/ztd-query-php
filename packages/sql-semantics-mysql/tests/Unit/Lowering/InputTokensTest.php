<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Platform\MySql\Lowering\InputTokens;

#[CoversClass(InputTokens::class)]
#[Small]
final class InputTokensTest extends TestCase
{
    public function testPrefixEndsBeforeTheFirstOfSeveralLexicalFailures(): void
    {
        $tokens = (new InputTokens())->prefix(new MySqlParser('mysql-8.4.7'), "SELECT ? ]\x1e");

        self::assertSame(['SELECT_SYM', 'PARAM_MARKER', 'END_OF_INPUT', '$end'], array_column($tokens, 'name'));
        self::assertSame([0, 7, 9, 9], array_column($tokens, 'offset'));
    }
}
