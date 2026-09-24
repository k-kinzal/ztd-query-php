<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Text\UnicodeNormalForm;

#[CoversClass(UnicodeNormalForm::class)]
final class UnicodeNormalFormTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeyword(): void
    {
        self::assertSame(['NFC', 'NFD', 'NFKC', 'NFKD'], array_column(UnicodeNormalForm::cases(), 'value'));
    }
}
