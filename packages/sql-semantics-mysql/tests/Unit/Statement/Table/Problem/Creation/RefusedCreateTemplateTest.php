<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTemplate;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedCreateTemplate::class)]
#[Small]
final class RefusedCreateTemplateTest extends TestCase
{
    public function testMessageRetainsTheCompleteTemplateWithoutAStatementRole(): void
    {
        $query = new M\Table\TableQuery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), options: [new M\Table\Option\UnionOption([])], query: $query);
        $refusal = new RefusedCreateTemplate($definition);
        self::assertSame($definition, $refusal->definition);
        self::assertSame('A MERGE source list and a creation query do not define a base table.', $refusal->message());
    }
}
