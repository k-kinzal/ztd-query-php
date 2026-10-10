<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\RoutineLookup;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;

#[CoversClass(RoutineLookup::class)]
#[Small]
final class RoutineLookupTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerFilters(): iterable
    {
        yield 'unfiltered' => ['', false];
        yield 'one key part' => ["WHERE Db='sys'", false];
        yield 'complete key' => ["WHERE Db='sys' AND Name='version_major'", true];
        yield 'reversed and grouped' => ["WHERE ('version_major'=Name) AND ('sys'=Db)", true];
        yield 'constant function' => ["WHERE Db=DATABASE() AND Name=CONCAT('version','_major')", true];
        yield 'singleton list' => ["WHERE Db='sys' AND Name IN ('version_major')", true];
        yield 'multiple values' => ["WHERE Db='sys' AND Name IN ('version_major','version_minor')", false];
        yield 'negated list' => ["WHERE Db='sys' AND Name NOT IN ('version_major')", false];
        yield 'pattern comparison' => ["WHERE Db='sys' AND Name LIKE 'version_major'", false];
        yield 'LIKE clause' => ["LIKE 'version_major'", false];
        yield 'disjunction' => ["WHERE Db='sys' OR Name='version_major'", false];
        yield 'variable by row' => ["WHERE Db='sys' AND Name=UUID()", false];
    }

    #[DataProvider('providerFilters')]
    public function testSingleRequiresBothIdentityPartsFixed(string $filter, bool $single): void
    {
        $operation = (new Instance())->connect()->analyze('SHOW FUNCTION STATUS ' . $filter);
        $statement = $operation->statement;
        self::assertInstanceOf(ShowFunctionStatus::class, $statement);

        self::assertSame($single, RoutineLookup::single($statement->filter, $operation->facts));
    }

    public function testColumnDistinguishesAColumnFromItsConstantValue(): void
    {
        $operation = (new Instance())->connect()->analyze("SHOW FUNCTION STATUS WHERE Db='sys'");
        $statement = $operation->statement;
        self::assertInstanceOf(ShowFunctionStatus::class, $statement);
        self::assertInstanceOf(ShowWhere::class, $statement->filter);
        $condition = $statement->filter->condition;
        self::assertInstanceOf(Comparison::class, $condition);

        self::assertSame('db', RoutineLookup::column($condition->left, $condition->right, $operation->facts));
        self::assertSame('', RoutineLookup::column($condition->right, $condition->left, $operation->facts));
    }
}
