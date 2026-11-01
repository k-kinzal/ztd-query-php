<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Check;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Check::class)]
#[Small]
final class CheckTest extends TestCase
{
    public function testNameTextAndEnforcementDescribeTheConstraint(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, CHECK (a > b) NOT ENFORCED)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        $check = $table->definition->checks[0];
        self::assertSame(['t_chk_1', '(`a` > `b`)', false, true, [0, 1]], [$check->name, $check->text, $check->enforced, $check->generatedName, $check->columns]);
    }
}
