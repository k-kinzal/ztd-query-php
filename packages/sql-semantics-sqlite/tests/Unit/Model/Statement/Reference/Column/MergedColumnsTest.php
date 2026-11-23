<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MergedColumns;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

#[CoversClass(MergedColumns::class)]
#[Small]
final class MergedColumnsTest extends TestCase
{
    public function testTheLeftAndRightOperandsRemainOrdered(): void
    {
        $slot = new OutputSlot(new Name('key'), new Known(Storage::Integer), Nullability::NotNull);
        $left = new ResolvedColumn(new TableInput(new QualifiedName(new Name('a'))), $slot);
        $right = new ResolvedColumn(new TableInput(new QualifiedName(new Name('b'))), $slot);
        $merge = new MergedColumns($left, $right);

        self::assertSame($left, $merge->left);
        self::assertSame($right, $merge->right);
    }
}
