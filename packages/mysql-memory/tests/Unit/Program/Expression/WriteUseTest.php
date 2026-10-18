<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\WriteUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WriteUse::class)]
#[Medium]
final class WriteUseTest extends TestCase
{
    public function testPrepareDelegatesTheReachedDeletion(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $write = new M\Routine\Program\Write\DeferredMultipleDelete(null, [], new M\Dml\Target\DeletionInput([new M\Relation\DerivedTable($query, new Name('q'))]), M\Dml\MultipleDeleteForm::BeforeFrom);
        $session = (new Instance())->connect();
        $this->expectExceptionMessage('No database selected');
        (new WriteUse())->prepare($write, $session);
    }
}
