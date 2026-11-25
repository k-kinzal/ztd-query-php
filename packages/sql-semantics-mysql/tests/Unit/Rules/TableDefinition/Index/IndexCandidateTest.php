<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexCandidate;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

#[CoversClass(IndexCandidate::class)]
#[Small]
final class IndexCandidateTest extends TestCase
{
    public function testDeclarationKeepsCanonicalOrderedFieldsAndGeneratingField(): void
    {
        $id = new Name('ID');
        $tag = new Name('tag');
        $candidate = new IndexCandidate(IndexKind::Index, null, $id, true, false, $id, [[$id, false], [$tag, true]]);
        $result = $candidate->declaration(new Name('generated_name'));

        self::assertSame(PhysicalIndexKind::Index, $result->kind);
        self::assertSame([$id,$tag], $result->leadingFields);
        self::assertSame($id, $result->autoIncrementField);
        self::assertSame('generated_name', $result->name->value);
    }
}
