<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\CertificateAttribute;

#[CoversClass(CertificateAttribute::class)]
#[Medium]
final class CertificateAttributeTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['SUBJECT', 'ISSUER', 'CIPHER'], array_column(CertificateAttribute::cases(), 'value'));
    }
}
