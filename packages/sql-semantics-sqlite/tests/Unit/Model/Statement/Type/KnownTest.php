<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeDescriptor;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Known::class)]
#[Medium]
final class KnownTest extends TestCase
{
    public function testDescriptorIsTheTypeTheRulesDetermine(): void
    {
        $fields = (new Inspection())->inspect("SELECT 'a', 1, 1.5, x'ff'")->fields();

        self::assertNotNull($fields);
        self::assertSame([Storage::Text, Storage::Integer, Storage::Real, Storage::Blob], array_map(static fn (Field $field): ?TypeDescriptor => $field->type instanceof Known ? $field->type->descriptor : null, $fields->items));
    }

    public function testDescriptorOfADeclaredColumnIsItsDeclaredType(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)')->declarations()[0];

        $type = $semantics->inspect('SELECT a FROM t', [$table])->field('a')->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertSame($table->columns[0]->type, $type->descriptor);
    }
}
