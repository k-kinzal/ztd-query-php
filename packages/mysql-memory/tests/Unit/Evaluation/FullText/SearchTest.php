<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\Search;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Search::class)]
#[Small]
final class SearchTest extends TestCase
{
    public function testEvaluateReadsPopulatedDocumentsAndProducesRelevance(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("CREATE TABLE t(id INT PRIMARY KEY,a TEXT,FULLTEXT k(a)); INSERT INTO t VALUES(1,'apple apple'),(2,'apple'),(3,'missing'),(4,'missing'),(5,NULL),(6,'the')");
        $result = $session->query("SELECT MATCH(a) AGAINST('apple') AS score FROM t ORDER BY id")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0.45528939366340637'], ['0.22764469683170319'], ['0'], ['0'], ['0'], ['0']], $result->rows);
    }

    public function testCollectionRecomputesFrequenciesAfterCommittedWrites(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("CREATE TABLE t(id INT PRIMARY KEY,a TEXT,FULLTEXT k(a)); INSERT INTO t VALUES(1,'apple'),(2,'missing')");
        $before = $session->query("SELECT MATCH(a) AGAINST('apple') AS score FROM t WHERE id=1")[0];
        $session->query("INSERT INTO t VALUES(3,'missing')");
        $after = $session->query("SELECT MATCH(a) AGAINST('apple') AS score FROM t WHERE id=1")[0];
        self::assertInstanceOf(ResultSet::class, $before);
        self::assertInstanceOf(ResultSet::class, $after);
        self::assertSame([['0.0906190574169159']], $before->rows);
        self::assertSame([['0.22764469683170319']], $after->rows);
    }

    public function testDomainPreservesTheSemanticDoubleMetadata(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a TEXT,FULLTEXT k(a))');
        $result = $session->query("SELECT MATCH(a) AGAINST('missing') AS score FROM t")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Double, $result->columns[0]->type);
    }
}
