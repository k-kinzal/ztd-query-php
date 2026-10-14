<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * A view query reports the absent catalog entry from its structure, regardless of session text.
 */
#[CoversNothing]
#[Medium]
final class ViewQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerNames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 12; $seed++) {
                foreach (['table', 'column', 'function'] as $kind) {
                    yield $release . ':' . $seed . ':' . $kind => [$release, 'absent_' . (new Choices('view-query:' . $seed))->integer(0, 100000), $kind];
                }
            }
        }
    }

    /**
     * Resolves a generated absent relation, column or routine without consulting source text.
     */
    #[DataProvider('providerNames')]
    public function testQueryDiagnosticsNeedNoSourceText(string $release, string $name, string $kind): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $execution = new Execution();
        if ($kind !== 'table') {
            $initial = new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
            $execution->runIn($session, new \SqlSemantics\Platform\MySql\Statement\View\CreateView(new ViewDefinition(new QualifiedName(new Name('missing_view')), $initial)));
        }
        $session->text = 'unrelated invalid input';
        $query = match ($kind) {
            'table' => new Select([], [new Star()], new TableReference(new QualifiedName(new Name($name)))),
            'column' => new Select([], [new SelectExpression(new ColumnUse(new Name($name)))]),
            default => new Select([], [new SelectExpression(new FunctionCall(new Name($name)))]),
        };
        $statement = new AlterView(new ViewDefinition(new QualifiedName(new Name('missing_view'), new Name($kind === 'table' ? 'absent_schema' : 'd')), $query));
        $this->expectExceptionCode(match ($kind) {
            'table' => 1146, 'column' => 1054, default => 1305
        });
        $this->expectExceptionMessage(match ($kind) {
            'table' => "Table 'd.{$name}' doesn't exist", 'column' => "Unknown column '{$name}' in 'field list'", default => "FUNCTION d.{$name} does not exist"
        });

        $execution->runIn($session, $statement);
    }

    /**
     * Executes a generated literal through a directly constructed view and checks its exposed type.
     */
    #[DataProvider('providerNames')]
    public function testMaterializedViewExposesItsResolvedIntegerType(string $release, string $name, string $kind): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $value = (string) (new Choices($name . ':' . $kind))->integer(0, 100000);
        $query = new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral($value), new Name('a'))]);
        $execution->runIn($session, new \SqlSemantics\Platform\MySql\Statement\View\CreateView(new ViewDefinition(new QualifiedName(new Name('v')), $query)));
        $result = $execution->runIn($session, new Select([], [new Star()], new TableReference(new QualifiedName(new Name('v')))));

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([[$value]], $result->rows);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, $result->columns[0]->type);
    }

    /**
     * A generated view INTO depends only on typed clause order, with no original input.
     */
    #[DataProvider('providerNames')]
    public function testLegacyIntoReadsOnlyPrecedingClauses(string $release, string $name, string $kind): void
    {
        $release = str_starts_with($release, '5.6') ? '5.6.51' : '5.7.44';
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $before = $kind === 'column';
        $read = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name($name));
        $item = new SelectExpression($before ? $read : new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        $into = new \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables([new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name('target_' . $name))]);
        $query = new Select([], [$item], from: new \SqlSemantics\Platform\MySql\Statement\Relation\Dual(), where: $before ? null : $read, into: $into, intoPosition: \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterItems);
        $view = new \SqlSemantics\Platform\MySql\Statement\View\CreateView(new ViewDefinition(new QualifiedName(new Name('v')), $query));
        $this->expectExceptionCode($before ? 1351 : 1350);
        $this->expectExceptionMessage($before ? "View's SELECT contains a variable or parameter" : "View's SELECT contains a 'INTO' clause");

        (new Execution())->runIn($session, $view);
    }

}
