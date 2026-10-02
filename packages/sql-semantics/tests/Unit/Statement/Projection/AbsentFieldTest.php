<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Projection\AbsentField;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(AbsentField::class)]
#[Small]
final class AbsentFieldTest extends TestCase
{
    public function testEmptyCompleteProjectionReportsAbsence(): void
    {
        $fields = new Fields(new Scope(new Catalog(new SearchPath(new Name('main')))));
        self::assertSame(AbsentField::Value, $fields->lookupField('missing'));
    }
}
