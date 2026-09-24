<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;

#[CoversClass(AnnotationForm::class)]
#[Medium]
final class AnnotationFormTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['COMMENT', 'ATTRIBUTE'], array_column(AnnotationForm::cases(), 'value'));
    }
}
