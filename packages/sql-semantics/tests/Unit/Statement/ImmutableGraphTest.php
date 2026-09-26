<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\ImmutableGraph;
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149 as Commit;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324 as Transaction;
use SqlSemantics\Statement\Writer;

#[CoversClass(ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[Medium]
final class ImmutableGraphTest extends TestCase
{
    public function testContainsOnlyImmutableValuesAcceptsGeneratedDescendants(): void
    {
        $value = new Commit('COMMIT', new Transaction());
        self::assertTrue((new ImmutableGraph())->containsOnlyImmutableValues($value));
    }

    public function testContainsOnlyImmutableValuesRejectsMutableState(): void
    {
        $value = new class () implements Element {
            public string $sql = 'SELECT 1';

            public function write(Writer $writer): void
            {
                $writer->append($this->sql);
            }
        };
        self::assertFalse((new ImmutableGraph())->containsOnlyImmutableValues($value));
    }

    public function testContainsOnlyImmutableValuesAcceptsCommentsAndRejectsOtherObjects(): void
    {
        $commented = new Commit('COMMIT', new Transaction(), new \SqlSemantics\Statement\Comments([0 => ['-- c']]));
        self::assertTrue((new ImmutableGraph())->containsOnlyImmutableValues($commented));
        $value = new class (new DateTime()) implements Element {
            public function __construct(public readonly DateTime $state)
            {
            }

            public function write(Writer $writer): void
            {
                $writer->append('SELECT');
            }
        };
        self::assertFalse((new ImmutableGraph())->containsOnlyImmutableValues($value));
    }
}
