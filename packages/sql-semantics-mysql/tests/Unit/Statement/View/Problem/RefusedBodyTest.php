<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedBody::class)]
#[Small]
final class RefusedBodyTest extends TestCase
{
    public function testMessagePreservesTheBodyWhileKeepingItOutsideTheStatementDomain(): void
    {
        $query = new Select([], [new SelectExpression(new UserVariable(new Name('x')))]);
        $problem = new RefusedBody(new QualifiedName(new Name('v')), true, $query);

        self::assertSame($query, $problem->body);
        self::assertTrue($problem->alter);
        self::assertSame("View's SELECT contains a variable or parameter", $problem->message());
        self::assertNotContains(\SqlSemantics\Statement\Statement::class, class_implements($problem));
    }
}
