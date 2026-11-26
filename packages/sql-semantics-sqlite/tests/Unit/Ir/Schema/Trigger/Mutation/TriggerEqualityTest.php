<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerEquality;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;

#[CoversClass(TriggerEquality::class)]
#[Small]
final class TriggerEqualityTest extends TestCase
{
    public function testKeepsDistinctLocalAndWatchedSlotsInWrittenOrder(): void
    {
        $local = new TriggerTargetField(0);
        $image = new TriggerField(RowImage::New, 1);
        $comparison = new TriggerEquality($local, $image, false);

        self::assertSame($local, $comparison->target);
        self::assertSame($image, $comparison->image);
        self::assertFalse($comparison->targetFirst);
    }
}
