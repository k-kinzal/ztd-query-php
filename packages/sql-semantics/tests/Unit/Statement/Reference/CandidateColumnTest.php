<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(CandidateColumn::class)]
#[Small]
final class CandidateColumnTest extends TestCase
{
    public function testCandidateRetainsTheActualRelationOccurrence(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $relation = new TableReference($catalog, new QualifiedName(new Name('users')));
        self::assertSame([$relation], (new CandidateColumn($relation))->possibilities);
    }

}
