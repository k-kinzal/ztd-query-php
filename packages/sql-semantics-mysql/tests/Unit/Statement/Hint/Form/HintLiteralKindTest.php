<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;

#[CoversClass(HintLiteralKind::class)]
#[Small]
final class HintLiteralKindTest extends TestCase
{
    public function testCasesNameTheKinds(): void
    {
        self::assertSame(['Integer', 'Decimal', 'Word', 'Text'], array_column(HintLiteralKind::cases(), 'name'));
    }
}
