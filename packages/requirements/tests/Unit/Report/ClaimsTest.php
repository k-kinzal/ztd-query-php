<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Item;
use Requirements\Model\Source;
use Requirements\Report\Claims;
use Requirements\Report\SourceUnit;
use Requirements\Source\Unit;

#[CoversClass(Claims::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(Item::class)]
#[UsesClass(Source::class)]
#[UsesClass(Unit::class)]
#[Small]
final class ClaimsTest extends TestCase
{
    public function testAssignRecordsTheEvidenceOfASpecification(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $units = (new Claims())->assign(['a' => new SourceUnit($source, new Unit('p:1', 'First.')), 'b' => new SourceUnit($source, new Unit('p:2', 'Second.'))], ['SPEC-001' => $item], ['SPEC-001' => ['a']], []);
        self::assertSame(['SPEC-001' => $item], $units['a']->claims);
        self::assertSame([], $units['b']->claims);
    }

    public function testAssignLetsNoRequirementClaimItsOwnEvidence(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $units = (new Claims())->assign(['a' => new SourceUnit($source, new Unit('p:1', 'First.'))], ['REQ-001' => $item], ['REQ-001' => ['a']], []);
        self::assertSame([], $units['a']->claims);
    }

    public function testAssignLetsASpecificationClaimTheEvidenceOfTheRequirementsItRefines(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $requirement = new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $specification = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', null, [], [], ['REQ-001'], [], [], '', 'sourced', '', 'other.yaml', []);
        $units = (new Claims())->assign(['a' => new SourceUnit($source, new Unit('p:1', 'First.')), 'b' => new SourceUnit($source, new Unit('p:2', 'Second.'))], ['REQ-001' => $requirement, 'SPEC-001' => $specification], ['REQ-001' => ['b'], 'SPEC-001' => []], []);
        self::assertSame([], $units['a']->claims);
        self::assertSame(['SPEC-001' => $specification], $units['b']->claims);
    }

    public function testAssignSkipsASpecificationWithInvalidEvidence(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $units = (new Claims())->assign(['a' => new SourceUnit($source, new Unit('p:1', 'First.'))], ['SPEC-001' => $item], ['SPEC-001' => ['a']], ['SPEC-001' => true]);
        self::assertSame([], $units['a']->claims);
    }

    public function testAssignSkipsASpecificationRefiningARequirementWithInvalidEvidence(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $requirement = new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $specification = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], ['REQ-001'], [], [], '', 'sourced', '', 'definition.yaml', []);
        $units = (new Claims())->assign(['a' => new SourceUnit($source, new Unit('p:1', 'First.')), 'b' => new SourceUnit($source, new Unit('p:2', 'Second.'))], ['REQ-001' => $requirement, 'SPEC-001' => $specification], ['REQ-001' => ['b'], 'SPEC-001' => ['a']], ['REQ-001' => true]);
        self::assertSame([], $units['a']->claims);
        self::assertSame([], $units['b']->claims);
    }

    public function testAssignKeepsOtherSpecificationsWhenOneIsInvalid(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $invalid = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $valid = new Item('SPEC-002', 'specification', 'The parser shall read digits.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $units = (new Claims())->assign(['a' => new SourceUnit($source, new Unit('p:1', 'First.'))], ['SPEC-001' => $invalid, 'SPEC-002' => $valid], ['SPEC-001' => ['a'], 'SPEC-002' => ['a']], ['SPEC-001' => true]);
        self::assertSame(['SPEC-002' => $valid], $units['a']->claims);
    }

    public function testAssignOrdersTheUnitsByKey(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $units = (new Claims())->assign(['c' => new SourceUnit($source, new Unit('p:3', 'Third.')), 'a' => new SourceUnit($source, new Unit('p:1', 'First.')), 'b' => new SourceUnit($source, new Unit('p:2', 'Second.'))], [], [], []);
        self::assertSame(['a', 'b', 'c'], array_keys($units));
    }
}
