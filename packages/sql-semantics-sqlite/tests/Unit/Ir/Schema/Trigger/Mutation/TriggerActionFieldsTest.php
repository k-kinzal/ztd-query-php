<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerActionFields;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TriggerActionFields::class)]
#[Small]
final class TriggerActionFieldsTest extends TestCase
{
    public function testCheckAcceptsTheExpectedOldImage(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('x')]);
        $filter = new TriggerCondition(new TriggerField(RowImage::Old, 0), true);
        $delete = new TriggerDelete(new TriggerDestination($table, [0]), $filter);

        (new TriggerActionFields())->check($delete, TriggerEvent::Delete, $table);

        self::assertSame($filter, $delete->where);
    }

    public function testImageRejectsAnExplicitColumnThatWouldBeCapturedByTheLocalTarget(): void
    {
        $schema = new Identifier('main');
        $watched = new TableSource($schema, new Identifier('watched'), [new Identifier('id')]);
        $target = new TableSource($schema, new Identifier('nEw'), [new Identifier('ID')]);
        $this->expectExceptionMessage('shadows this row-image field');

        (new TriggerActionFields())->image(new TriggerField(RowImage::New, 0), TriggerEvent::Insert, $watched, $target);
    }
}
