<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\DictionaryOption;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DictionaryOption::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DictionaryOptionTest extends TestCase
{
    public function testKeepsTheNameAndOptionalText(): void
    {
        self::assertSame('english', (new DictionaryOption('stopwords', 'english'))->value);
        self::assertNull((new DictionaryOption('accept', null))->value);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DictionaryOption('', null);
    }
}
