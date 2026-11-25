<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateAsRecord;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(CreateAsRecord::class)]
#[Small]
final class CreateAsRecordTest extends TestCase
{
    public function testCheckAdmitsTheExactRequiredRecordBelowTheLimit(): void
    {
        $source = new TableSource(
            new Identifier('app'),
            new Identifier('source'),
            [new Identifier('padding'), new Identifier('number')],
            [new Varchar(64, nullable: false), ColumnKind::RequiredSignedInt],
        );
        $text = array_map(
            static fn (int $position): ResultColumn => new ResultColumn(new TextColumnReference(0), new Identifier('text_' . $position)),
            range(0, 196),
        );
        $ints = array_map(
            static fn (int $position): ResultColumn => new ResultColumn(new ColumnReference(1), new Identifier('int_' . $position)),
            range(0, 5),
        );

        (new CreateAsRecord())->check(new Select([...$text, ...$ints], $source), []);
        $this->addToAssertionCount(1);
    }

    public function testProjectedRetainsTheExactStoredNullability(): void
    {
        $source = new TableSource(
            new Identifier('app'),
            new Identifier('source'),
            [new Identifier('required'), new Identifier('nullable')],
            [ColumnKind::RequiredSignedInt, ColumnKind::SignedInt],
        );
        $required = new ResultColumn(new ColumnReference(0), new Identifier('required'));
        $nullable = new ResultColumn(new ColumnReference(1), new Identifier('nullable'));
        $query = new Select([$required, $nullable], $source);
        $record = new CreateAsRecord();

        self::assertSame([4, 4, false, false], $record->projected($query, $required));
        self::assertSame([4, 4, true, false], $record->projected($query, $nullable));
    }
}
