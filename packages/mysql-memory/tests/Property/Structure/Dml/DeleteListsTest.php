<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Dml;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Comma-list composition preserves deletion targets and nested table references.
 */
#[CoversNothing]
#[Medium]
final class DeleteListsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, MultipleDeleteForm, bool}>
     */
    public static function providerLists(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                $choices = new Choices('delete-lists:' . $seed);
                yield $release . ':' . $seed => [$release, $choices->one([1, 2, 3, 4]), $choices->one(MultipleDeleteForm::cases()), $choices->one([false, true])];
            }
        }
    }

    /**
     * The generated statement executes directly; SQL is used only to establish and observe fixture state.
     */
    #[DataProvider('providerLists')]
    public function testComposedReferencesDeleteEachTargetRowOnce(string $release, int $rows, MultipleDeleteForm $form, bool $nested): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT); CREATE TABLE b (id INT); CREATE TABLE c (id INT)');
        for ($row = 0; $row < $rows; $row++) {
            $session->query('INSERT INTO a VALUES (' . $row . '); INSERT INTO b VALUES (' . $row . '); INSERT INTO c VALUES (' . $row . ')');
        }
        $list = new TableList([new TableReference(new QualifiedName(new Name('b'))), new TableReference(new QualifiedName(new Name('c')))]);
        $statement = new MultipleDelete(null, [], [new QualifiedName(new Name('a')), new QualifiedName(new Name('b'))], $form, [new TableReference(new QualifiedName(new Name('a'))), $nested ? new NestedRelation($list) : $list]);
        $session->text = 'unrelated invalid input';
        $reply = (new Execution())->runIn($session, $statement);
        $remaining = $session->query('SELECT (SELECT COUNT(*) FROM a), (SELECT COUNT(*) FROM b), (SELECT COUNT(*) FROM c)')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame($rows * 2, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $remaining);
        self::assertSame([['0', '0', (string) $rows]], $remaining->rows);
    }
}
