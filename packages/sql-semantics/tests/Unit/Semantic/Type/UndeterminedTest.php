<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Type;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Type\Undetermined::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class UndeterminedTest extends TestCase
{
    public function testCatalogAndParameterUncertaintyRemainDistinguishable(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo, ? AS p FROM bar', false);
        self::assertInstanceOf(\SqlSemantics\Semantic\Type\Undetermined::class, $statement->field('foo')->type);
        self::assertInstanceOf(\SqlSemantics\Semantic\Type\Undetermined::class, $statement->field('p')->type);
        self::assertNotSame($statement->field('foo')->type->reason, $statement->field('p')->type->reason);
    }
}
