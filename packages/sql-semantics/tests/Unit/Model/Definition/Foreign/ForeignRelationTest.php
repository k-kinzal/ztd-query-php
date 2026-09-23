<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Foreign\ForeignRelation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ForeignRelation::class)]
#[Medium]
final class ForeignRelationTest extends TestCase
{
    public function testRemoteReferenceRetainsNamesWithoutALocalDeclaration(): void
    {
        $relation = new ForeignRelation(new QualifiedName(['db', 'app', 'users']));
        self::assertSame(['db', 'app', 'users'], $relation->name->parts);
        self::assertTrue($relation->includeDescendants);
    }

    /**
     * @param list<string> $parts
     */
    #[TestWith([['a', 'b', 'c', 'd']])]
    #[TestWith([['']])]
    public function testRemoteReferenceRejectsInvalidQualification(array $parts): void
    {
        $this->expectException(InvalidStructure::class);
        new ForeignRelation(new QualifiedName($parts));
    }

}
