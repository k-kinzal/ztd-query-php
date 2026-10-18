<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem\Delete;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RefusedDelete::class)]
#[Small]
final class RefusedDeleteTest extends TestCase
{
    public function testMessageDescribesTheRetainedTargetRole(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $write = new M\Routine\Program\Write\DeferredMultipleDelete(null, [], new M\Dml\Target\DeletionInput([new M\Relation\DerivedTable($query, new Name('q'))]), M\Dml\MultipleDeleteForm::BeforeFrom);
        $problem = new RefusedDelete($write);
        self::assertSame($write, $problem->write);
        self::assertSame('A derived or JSON relation cannot supply a DELETE target.', $problem->message());
    }
}
