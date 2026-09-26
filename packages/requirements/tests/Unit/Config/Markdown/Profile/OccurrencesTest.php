<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Profile\Occurrences;
use Requirements\Input\InvalidInputException;

#[CoversClass(Occurrences::class)]
#[Small]
final class OccurrencesTest extends TestCase
{
    /**
     * @param array<string, mixed> $schema
     */
    #[DataProvider('providerAccepted')]
    public function testCheckAcceptsCountWithinBounds(int $count, array $schema): void
    {
        Occurrences::check($count, $schema, 'section');
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{int, array<string, mixed>}>
     */
    public static function providerAccepted(): array
    {
        return [
            'default minimum of one' => [1, []],
            'unbounded default maximum' => [PHP_INT_MAX, []],
            'explicit zero minimum' => [0, ['minContains' => 0]],
            'count at maximum' => [2, ['maxContains' => 2]],
            'count at both bounds' => [2, ['minContains' => 2, 'maxContains' => 2]],
            'unrelated keys ignored' => [1, ['type' => 'paragraph']],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     */
    #[DataProvider('providerRejected')]
    public function testCheckRejectsCountOrBounds(int $count, array $schema): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('doc.md: section block 2: document-schema occurrence constraint failed.');
        Occurrences::check($count, $schema, 'doc.md: section block 2');
    }

    /**
     * @return array<string, array{int, array<string, mixed>}>
     */
    public static function providerRejected(): array
    {
        return [
            'below default minimum' => [0, []],
            'below explicit minimum' => [1, ['minContains' => 2]],
            'above maximum' => [3, ['maxContains' => 2]],
            'above zero maximum' => [1, ['minContains' => 0, 'maxContains' => 0]],
            'string minimum' => [1, ['minContains' => '1']],
            'float maximum' => [1, ['maxContains' => 1.5]],
            'null minimum treated as default' => [0, ['minContains' => null]],
        ];
    }
}
