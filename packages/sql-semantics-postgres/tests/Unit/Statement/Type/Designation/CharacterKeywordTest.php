<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterKeyword;

#[CoversClass(CharacterKeyword::class)]
#[Small]
final class CharacterKeywordTest extends TestCase
{
    public function testCasesSpellTheCharacterTypes(): void
    {
        self::assertSame(['CHARACTER', 'CHAR', 'VARCHAR', 'NATIONAL CHARACTER', 'NATIONAL CHAR', 'NCHAR'], array_map(static fn (CharacterKeyword $keyword): string => $keyword->value, CharacterKeyword::cases()));
    }
}
