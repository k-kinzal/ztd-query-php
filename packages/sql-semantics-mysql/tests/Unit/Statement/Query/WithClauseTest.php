<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversNothing]
#[Medium]
final class WithClauseTest extends TestCase
{
    public function testAWithClauseIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(WithClause::class));
        self::assertContains(Node::class, class_implements(WithClause::class));
    }

    public function testBindAnswersTheScopeWithTheCommonTables(): void
    {
        $semantics = new Inspection();
        $operation = $semantics->inspect('WITH c AS (SELECT 1 AS x) SELECT x FROM c');
        $with = $operation->statement instanceof QueryExpression ? $operation->statement->with : null;
        $derivation = new Derivation($semantics->context());

        self::assertNotNull($with);
        self::assertNotNull($with->bind($derivation, $derivation->environment())->commonTable(new Name('c')));
    }
}
