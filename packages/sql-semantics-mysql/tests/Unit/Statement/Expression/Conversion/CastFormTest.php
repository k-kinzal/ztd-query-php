<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm;

#[CoversClass(CastForm::class)]
#[Small]
final class CastFormTest extends TestCase
{
    public function testCasesKeepTheThreeDistinctRequestForms(): void
    {
        self::assertSame(['Cast', 'Convert', 'AtLocal'], array_map(static fn (CastForm $form): string => $form->name, CastForm::cases()));
    }
}
