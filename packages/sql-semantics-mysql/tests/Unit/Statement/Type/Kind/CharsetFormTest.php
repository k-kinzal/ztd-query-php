<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;

#[CoversClass(CharsetForm::class)]
#[Small]
final class CharsetFormTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEveryCharacterSetForm(): void
    {
        self::assertSame(['Ascii', 'Unicode', 'Byte', 'Named', 'CharacterSet', 'Binary'], array_column(CharsetForm::cases(), 'name'));
        self::assertSame(['ASCII', 'UNICODE', 'BYTE', 'CHARSET', 'CHARACTER SET', 'BINARY'], array_column(CharsetForm::cases(), 'value'));
    }

    public function testNamedTellsTheFormsThatNameACharacterSet(): void
    {
        self::assertSame([CharsetForm::Named, CharsetForm::CharacterSet], array_values(array_filter(CharsetForm::cases(), static fn (CharsetForm $form): bool => $form->named())));
    }
}
