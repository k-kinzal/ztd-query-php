<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewFacts;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;

#[CoversClass(ViewFacts::class)]
#[Medium]
final class ViewFactsTest extends TestCase
{
    public function testDeriveAnswersTheDeclarationOfTheView(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE VIEW v AS SELECT 1 AS a');
        $statement = $create->statement;
        self::assertInstanceOf(CreateView::class, $statement);
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $table = (new ViewFacts())->derive($statement->definition, $derivation);

        self::assertSame('a', $table->columns[0]->name->value);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerViewDomains(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ':short' => [$release, 'SELECT 1 AS a', 3];
            yield $release . ':wide' => [$release, 'SELECT 12345678901 AS a', 8];
            yield $release . ':null' => [$release, 'SELECT NULL AS a', in_array($release, ['5.6.51', '5.7.44', '8.0.44'], true) ? 254 : 253];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerViewDomains')]
    public function testMaterializedKeepsTheExposedColumnDomain(string $release, string $query, int $field): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-' . $release))->analyze('CREATE VIEW v AS ' . $query);
        $statement = $create->statement;
        self::assertInstanceOf(CreateView::class, $statement);
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-' . $release, null, ParameterStyle::Native), null, [], true));
        $facts = new ViewFacts();
        $table = $facts->derive($statement->definition, $derivation);
        $table = $facts->materialized($table, $statement->definition, $derivation);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $table->columns[0]->type);
        self::assertSame($field, $table->columns[0]->type->field->value);
    }

}
