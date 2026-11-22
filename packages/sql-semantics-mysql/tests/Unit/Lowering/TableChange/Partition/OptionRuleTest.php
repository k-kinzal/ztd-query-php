<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\OptionRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(OptionRule::class)]
#[Medium]
final class OptionRuleTest extends TestCase
{
    public function testOptionsLowersTheList(): void
    {
        self::assertSame("ALTER TABLE t ADD PARTITION (PARTITION a ENGINE = InnoDB COMMENT = 'x')", (new Inspection())->inspect("ALTER TABLE t ADD PARTITION (PARTITION a ENGINE = InnoDB COMMENT = 'x')")->toString());
    }

    public function testOptionLowersEveryOption(): void
    {
        self::assertSame("ALTER TABLE t ADD PARTITION (PARTITION a TABLESPACE = ts NODEGROUP = 1 MIN_ROWS = 2 INDEX DIRECTORY = '/i')", (new Inspection('mysql-5.6.51'))->inspect("ALTER TABLE t ADD PARTITION (PARTITION a TABLESPACE 'ts' NODEGROUP 1 MIN_ROWS 2 INDEX DIRECTORY '/i')")->toString());
    }
}
