<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\ProjectionLabel;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ProjectionLabel::class)]
#[Medium]
final class ProjectionLabelTest extends TestCase
{
    public function testLowerDistinguishesTheWrittenAliasFromThePhysicalLabel(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (' . chr(96) . ' amount' . chr(96) . ' INT)');
        $sql = 'SELECT ' . chr(96) . ' amount' . chr(96) . ' AS ' . chr(96) . ' amount' . chr(96) . ', * FROM app.events';
        $operation = $inspection->inspect(str_replace(', *', ', events.*', $sql), [$table]);
        $binding = $operation->binding();
        $select = $binding->statement;
        self::assertInstanceOf(Select::class, $select);
        $fields = $binding->facts->query($select)->fields();
        self::assertNotNull($fields);

        $labels = new ProjectionLabel();
        self::assertSame('amount', $labels->lower($select, $fields->at(0), ' amount')->value);
        self::assertSame(' amount', $labels->lower($select, $fields->at(1), ' amount')->value);
    }
}
