<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;

#[CoversClass(CharacterKind::class)]
#[Small]
final class CharacterKindTest extends TestCase
{
    public function testCasesSpellTheKeywordsOfEveryCharacterStringType(): void
    {
        self::assertSame(['Char', 'VarChar', 'CharVarying', 'TinyText', 'Text', 'MediumText', 'LongText', 'Long', 'LongVarChar', 'LongCharVarying'], array_column(CharacterKind::cases(), 'name'));
        self::assertSame(['CHAR', 'VARCHAR', 'CHAR VARYING', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'LONG', 'LONG VARCHAR', 'LONG CHAR VARYING'], array_column(CharacterKind::cases(), 'value'));
    }
}
