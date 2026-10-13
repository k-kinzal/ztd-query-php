<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Catalogue;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;

/**
 * Constructor roles restrict inputs before SQL derivation or execution.
 */
#[CoversNothing]
#[Medium]
final class StatementRolesTest extends TestCase
{
    /**
     * @throws JsonException When the semantic inventory cannot be loaded
     */
    public function testExplainableInventoryContainsOnlyQueriesAndDataManipulation(): void
    {
        self::assertEqualsCanonicalizing([
            Select::class, ExplicitTable::class, ValuesQuery::class, ParenthesizedQuery::class,
            QueryExpression::class, QueryStatement::class, SetOperation::class,
            InsertQuery::class, InsertRows::class, InsertSet::class, Update::class, Delete::class,
        ], (new Catalogue())->implementations(Explainable::class));
    }

}
