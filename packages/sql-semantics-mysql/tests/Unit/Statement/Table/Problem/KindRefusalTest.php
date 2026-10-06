<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;

#[CoversClass(KindRefusal::class)]
#[Small]
final class KindRefusalTest extends TestCase
{
    public function testCasesHoldTheWordsOfTheServer(): void
    {
        self::assertSame('is not VIEW', KindRefusal::NotView->value);
        self::assertSame('is not BASE TABLE', KindRefusal::NotBaseTable->value);
    }
}
