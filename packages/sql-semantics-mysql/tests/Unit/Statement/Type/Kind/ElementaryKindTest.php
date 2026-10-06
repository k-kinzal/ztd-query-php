<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;

#[CoversClass(ElementaryKind::class)]
#[Small]
final class ElementaryKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEveryOneKeywordType(): void
    {
        self::assertSame(['Boolean', 'Serial', 'Json', 'Bit', 'Vector'], array_column(ElementaryKind::cases(), 'name'));
        self::assertSame(['BOOL', 'SERIAL', 'JSON', 'BIT', 'VECTOR'], array_column(ElementaryKind::cases(), 'value'));
    }
}
