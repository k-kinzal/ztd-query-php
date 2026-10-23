<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Directly constructed options retain diagnostic order independently of SQL text.
 */
#[CoversNothing]
#[Medium]
final class TableOptionsProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool, int}>
     */
    public static function providerOptions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([false, true] as $selected) {
                foreach ([false, true] as $qualified) {
                    for ($seed = 0; $seed < 8; $seed++) {
                        yield $release . ':' . (int) $selected . ':' . (int) $qualified . ':' . $seed => [$release, $selected, $qualified, $seed];
                    }
                }
            }
        }
    }

    /**
     * Varies the target database and competing option failures before a query warning.
     */
    #[DataProvider('providerOptions')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesTypedOptionOrderSurvivesDirectSubmission(string $release, bool $selected, bool $qualified, int $seed): void
    {
        $choices = new Choices('table-options:' . $seed);
        $name = new Name('missing_' . $choices->integer(0, 10000));
        $options = [new CharsetOption(new CharsetName($name)), new CollationOption(new CollationName($name))];
        $reverse = $seed % 2 === 1;
        $query = new TableQuery(new Select([], [new SelectExpression(new BinaryCast(new NumberLiteral('1')))]));
        $create = new CreateTable(new QualifiedName(new Name('t'), $qualified ? new Name('d') : null), options: $reverse ? array_reverse($options) : $options, query: $query);
        $session = (new Instance($release, [], ['d']))->connect();
        $session->variables->database = $selected ? 'd' : '';
        $session->text = 'unrelated invalid SQL';
        $expected = !$selected && !$qualified && $session->settings()->legacy() ? 1046 : ($reverse ? 1273 : 1115);
        try {
            (new Execution())->runIn($session, $create);
            self::fail('The first unknown option must retain its error.');
        } catch (SqlError $error) {
            self::assertSame($expected, $error->getCode());
            self::assertSame($expected === 1046 ? [] : [$expected], array_column($session->diagnostics->conditions, 1), 'The following query must not emit its BINARY warning.');
        }
    }
}
