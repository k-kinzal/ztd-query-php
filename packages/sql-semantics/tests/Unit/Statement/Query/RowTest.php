<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(Row::class)]
#[Small]
final class RowTest extends TestCase
{
    public function testToStringKeepsOperandOrderAndIdentity(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $first = new NullConstant('NULL');
        $second = new NullConstant('null');
        $row = new Row($scope, $first, $second);
        self::assertSame([$first, $second], $row->expressions);
        self::assertSame('(NULL, null)', $row->toString());
    }
}
