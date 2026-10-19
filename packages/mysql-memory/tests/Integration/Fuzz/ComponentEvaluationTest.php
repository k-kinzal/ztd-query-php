<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class ComponentEvaluationTest extends TestCase
{
    public function testUnseededComponentInputPreservesEveryWarning(): void
    {
        [$target] = Servers::shared();
        $sql = (string) file_get_contents(__DIR__ . '/fixtures/install-component-repeated-evaluation.sql');
        $comparison = $target->compare($sql);
        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
