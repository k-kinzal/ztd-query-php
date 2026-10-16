<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedLoadOptions::class)]
#[Small]
final class RefusedLoadOptionsTest extends TestCase
{
    public function testMessageRetainsTheProblemAndQualifiedTarget(): void
    {
        $table = new QualifiedName(new Name('t'), new Name('d'));
        $problem = new RefusedLoadOptions($table, LoadFormat::Data, new LoadConflict(LoadRule::ColumnsBulk));
        self::assertSame('Incorrect usage of LOAD DATA with BULK Algorithm and column list specification', $problem->message());
        self::assertSame($table, $problem->table);
        self::assertSame([], $problem->assignments);
    }
}
