<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Procedural\ProcedureName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ProcedureName::class)]
final class ProcedureNameTest extends TestCase
{
    public function testValidateAcceptsADatabaseQualifiedName(): void
    {
        ProcedureName::validate(new QualifiedName(['app', 'refresh']));
        $this->addToAssertionCount(1);
    }

    /**
     * @param list<string> $parts
     */
    #[TestWith([['a', 'b', 'c']])]
    #[TestWith([['']])]
    #[TestWith([['app', 'refresh ']])]
    #[TestWith([['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']])]
    public function testValidateRejectsNamesTheServerRejects(array $parts): void
    {
        $this->expectException(InvalidStructure::class);
        ProcedureName::validate(new QualifiedName($parts));
    }
}
