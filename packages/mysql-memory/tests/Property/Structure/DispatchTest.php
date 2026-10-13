<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Catalogue;
use JsonException;
use MySqlMemory\Command\Dispatcher;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Query;

/**
 * Every inventoried statement variant has an explicit execution route.
 * This checks dispatch completeness, independently of generated SQL or state.
 */
#[CoversNothing]
#[Medium]
final class DispatchTest extends TestCase
{
    /**
     * @return iterable<class-string, array{class-string}>
     * @throws JsonException When a semantic inventory is invalid
     */
    public static function providerStatements(): iterable
    {
        foreach ((new Catalogue())->implementations(Statement::class) as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @param class-string $class The concrete statement type
     */
    #[DataProvider('providerStatements')]
    public function testEveryStatementHasAnExplicitCommandRoute(string $class): void
    {
        $specialized = is_a($class, Query::class, true) || in_array($class, [InsertRows::class, InsertSet::class, InsertQuery::class, Update::class, Delete::class, Resignal::class], true);
        $command = Dispatcher::COMMANDS[$class] ?? null;

        self::assertTrue($specialized || $command !== null, 'No command consumes ' . $class);
    }
}
