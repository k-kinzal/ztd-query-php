<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableChange\IndexOptions;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

#[CoversClass(IndexOptions::class)]
#[Small]
final class IndexOptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<AlterOption>}>
     */
    public static function providerPairs(): iterable
    {
        yield 'none' => [[]];
        yield 'algorithm' => [[new AlgorithmOption(null)]];
        yield 'lock' => [[new LockOption(null)]];
        yield 'algorithm then lock' => [[new AlgorithmOption(null), new LockOption(null)]];
        yield 'lock then algorithm' => [[new LockOption(null), new AlgorithmOption(null)]];
    }

    /**
     * @param list<AlterOption> $options
     */
    #[DataProvider('providerPairs')]
    public function testCheckedPreservesTheSuppliedOrder(array $options): void
    {
        self::assertSame($options, (new IndexOptions())->checked($options));
    }

    /**
     * @return iterable<string, array{list<AlterOption>}>
     */
    public static function providerDuplicates(): iterable
    {
        yield 'algorithm twice' => [[new AlgorithmOption(null), new AlgorithmOption(null)]];
        yield 'lock twice' => [[new LockOption(null), new LockOption(null)]];
        yield 'separated duplicate' => [[new LockOption(null), new AlgorithmOption(null), new LockOption(null)]];
    }

    /**
     * @param list<AlterOption> $options
     */
    #[DataProvider('providerDuplicates')]
    public function testCheckedRefusesDuplicateRoles(array $options): void
    {
        $this->expectExceptionMessage('An index statement has at most one ALGORITHM and one LOCK clause.');

        (new IndexOptions())->checked($options);
    }
}
