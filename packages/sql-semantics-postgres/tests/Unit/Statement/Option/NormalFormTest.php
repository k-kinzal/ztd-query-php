<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;

#[CoversClass(NormalForm::class)]
#[Small]
final class NormalFormTest extends TestCase
{
    public function testCasesSpellTheForms(): void
    {
        self::assertSame(['NFC', 'NFD', 'NFKC', 'NFKD'], array_map(static fn (NormalForm $form): string => $form->value, NormalForm::cases()));
    }
}
