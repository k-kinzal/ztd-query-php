<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\Collection;
use MySqlMemory\Evaluation\FullText\Document;
use MySqlMemory\Evaluation\FullText\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Collection::class)]
#[Small]
final class CollectionTest extends TestCase
{
    public function testFrequenciesCountsDocumentsInsteadOfWordOccurrences(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $collection = new Collection([new Document(['apple', 'apple', 'pear'], $words), new Document(['apple'], $words), new Document([], $words)]);
        self::assertSame([$words->key('apple') => 2, $words->key('pear') => 1], $collection->frequencies());
    }
}
