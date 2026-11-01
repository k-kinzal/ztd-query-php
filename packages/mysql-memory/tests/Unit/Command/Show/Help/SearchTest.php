<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Help;

use MySqlMemory\Command\Show\Help\Search;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Search::class)]
#[Small]
final class SearchTest extends TestCase
{
    public function testFindUsesOnlyAnUnambiguousKeyword(): void
    {
        $session = (new Instance())->connect();
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);
        $search = new Search(['topics' => [['1', 'Topic', '1', 'Description', 'Example']], 'keywords' => [['1', 'One'], ['2', 'Other']], 'relations' => [['1', '1'], ['1', '2']]]);

        self::assertSame([['Topic', 'Description', 'Example']], $search->find('One', $context)->rows);
        self::assertSame([], $search->find('O%', $context)->rows);
    }

    public function testMatchingUsesWholeNamesAndEscapedWildcards(): void
    {
        $session = (new Instance())->connect();
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);
        $search = new Search(['categories' => [['1', 'É%_', '']]]);

        self::assertSame([['1', 'É%_', '']], $search->matching('categories', 'e\\%\\_', $context));
        self::assertSame([], $search->matching('categories', 'e', $context));
    }

    public function testListingSortsTopicsBeforeCategories(): void
    {
        $result = (new Search([]))->listing([['2', 'Zed'], ['1', 'Alpha']], [['1', 'A category']]);

        self::assertSame([['Alpha', 'N'], ['Zed', 'N'], ['A category', 'Y']], $result->rows);
        self::assertSame(['name' => 64, 'is_it_category' => 1], $result->columns);
    }

    public function testCategoryListsOnlyImmediateContents(): void
    {
        $search = new Search(['topics' => [['1', 'Topic', '1', '', ''], ['2', 'Nested', '2', '', '']], 'categories' => [['2', 'Child', '1'], ['3', 'Grandchild', '2']]]);
        $result = $search->category(['1', 'Parent', '']);

        self::assertSame([['Parent', 'Topic', 'N'], ['Parent', 'Child', 'Y']], $result->rows);
        self::assertSame(['source_category_name' => 64, 'name' => 64, 'is_it_category' => 1], $result->columns);
    }
}
