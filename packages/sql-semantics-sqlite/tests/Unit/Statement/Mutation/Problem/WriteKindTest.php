<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\WriteKind;

#[CoversClass(WriteKind::class)]
#[Small]
final class WriteKindTest extends TestCase
{
    public function testCasesUseTheWordsOfSqlite(): void
    {
        self::assertSame(['Insert', 'Update'], array_column(WriteKind::cases(), 'name'));
        self::assertSame('cannot INSERT into generated column', WriteKind::Insert->value);
        self::assertSame('cannot UPDATE generated column', WriteKind::Update->value);
    }
}
