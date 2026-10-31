<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\ParserInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParserInput::class)]
#[Small]
final class ParserInputTest extends TestCase
{
    public function testBeforeResolvesOnlyCompletedParserDependencies(): void
    {
        $session = (new Instance())->connect();
        $sql = 'CREATE FULLTEXT INDEX i ON t(a) WITH PARSER zz ALGORITHM=bad';

        self::assertNull((new ParserInput())->before($sql, 20, $session));
        self::assertSame(1128, (new ParserInput())->before($sql, strlen($sql), $session)?->getCode());
        self::assertNull((new ParserInput())->before(str_replace('zz', 'ngram', $sql), 100, $session));
    }
}
