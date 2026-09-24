<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\OperatorSet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorClassStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OperatorSet\OperatorSetMember::class)]
#[Medium]
final class OperatorSetMemberTest extends TestCase
{
    public function testEveryMemberFormIsAnOperatorSetMember(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING gist AS OPERATOR 1 <, FUNCTION 1 f, STORAGE text');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertContainsOnlyInstancesOf(OperatorSet\OperatorSetMember::class, $statement->members);
        self::assertInstanceOf(OperatorSet\StorageMember::class, $statement->members[2]);
    }
}
