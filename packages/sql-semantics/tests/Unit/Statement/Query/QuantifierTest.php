<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\FieldDefinition;
use SqlSemantics\Statement\Construction\Query\Inputs;
use SqlSemantics\Statement\Construction\Query\NamedInput;
use SqlSemantics\Statement\Construction\Query\ProjectionDefinition;
use SqlSemantics\Statement\Construction\Query\SelectDefinition;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query\Quantifier;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(Quantifier::class)]
#[Medium]
final class QuantifierTest extends TestCase
{
    /**
     * @param list<int> $expected
     */
    #[TestWith([Quantifier::Default, [1, 1]])]
    #[TestWith([Quantifier::All, [1, 1]])]
    #[TestWith([Quantifier::Distinct, [1]])]
    public function testDuplicatePolicyMatchesDatabaseResults(Quantifier $quantifier, array $expected): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER NOT NULL)');
        $db->exec('INSERT INTO bar VALUES (1), (1)');
        $result = $db->query((new Select($catalog, new SelectDefinition(new ProjectionDefinition(new FieldDefinition(new ColumnUse(new Name('foo')))), new Inputs(new NamedInput($table->name)), quantifier: $quantifier)))->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame($expected, $result->fetchAll(PDO::FETCH_COLUMN));
    }
}
