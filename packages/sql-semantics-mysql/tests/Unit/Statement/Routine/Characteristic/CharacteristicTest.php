<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Characteristic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\DataAccess;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineComment;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity;

#[CoversClass(Characteristic::class)]
#[Small]
final class CharacteristicTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function providerImplementersAreTheFiveKindsOfCharacteristic(): iterable
    {
        yield 'COMMENT' => [RoutineComment::class];
        yield 'LANGUAGE' => [RoutineLanguage::class];
        yield 'data access' => [DataAccess::class];
        yield 'SQL SECURITY' => [SqlSecurity::class];
        yield 'DETERMINISTIC' => [Determinism::class];
    }

    #[DataProvider('providerImplementersAreTheFiveKindsOfCharacteristic')]
    public function testImplementersAreTheFiveKindsOfCharacteristic(string $class): void
    {
        self::assertTrue(is_subclass_of($class, Characteristic::class));
    }
}
