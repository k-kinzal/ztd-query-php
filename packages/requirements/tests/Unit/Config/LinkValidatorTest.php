<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\LinkValidator;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;
use Requirements\Model\TestReference;
use Requirements\Test\RunnerConfig;

#[CoversClass(LinkValidator::class)]
#[UsesClass(Item::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(TestReference::class)]
#[Small]
final class LinkValidatorTest extends TestCase
{
    public function testValidateAcceptsResolvedLinks(): void
    {
        $items = [
            'REQ-001' => new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', null, [], [], [], [], [], '', 'sourced', '', 'a.yaml', []),
            'SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [new TestReference('unit', 'Test::testPass')], ['REQ-001'], ['SPEC-002'], [], '', 'sourced', '', 'b.yaml', []),
            'SPEC-002' => new Item('SPEC-002', 'specification', 'The parser shall accept digits.', 'supported', null, [], [], [], ['SPEC-001', 'REQ-001'], [], '', 'original', 'Design choice.', 'b.yaml', []),
        ];
        (new LinkValidator())->validate($items, ['unit' => new RunnerConfig('phpunit', ['phpunit'], '/project')]);
        $this->addToAssertionCount(1);
    }

    public function testValidateAcceptsNoItems(): void
    {
        (new LinkValidator())->validate([], []);
        $this->addToAssertionCount(1);
    }

    public function testValidateRejectsMissingRequirement(): void
    {
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [], ['MISSING'], [], [], '', 'sourced', '', 'b.yaml', [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: missing or self reference 'MISSING'.");
        (new LinkValidator())->validate($items, []);
    }

    public function testValidateRejectsMissingRelatedItem(): void
    {
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [], [], ['SPEC-404'], [], '', 'sourced', '', 'b.yaml', [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: missing or self reference 'SPEC-404'.");
        (new LinkValidator())->validate($items, []);
    }

    public function testValidateRejectsSelfRelation(): void
    {
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [], [], ['SPEC-001'], [], '', 'sourced', '', 'b.yaml', [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: missing or self reference 'SPEC-001'.");
        (new LinkValidator())->validate($items, []);
    }

    public function testValidateRejectsSelfRequirement(): void
    {
        $items = ['REQ-001' => new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', null, [], [], ['REQ-001'], [], [], '', 'sourced', '', 'a.yaml', [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("REQ-001: missing or self reference 'REQ-001'.");
        (new LinkValidator())->validate($items, []);
    }

    public function testValidateRejectsRequirementThatIsSpecification(): void
    {
        $items = [
            'SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [], ['SPEC-002'], [], [], '', 'sourced', '', 'b.yaml', []),
            'SPEC-002' => new Item('SPEC-002', 'specification', 'The parser shall accept digits.', 'supported', null, [], [], [], [], [], '', 'sourced', '', 'b.yaml', []),
        ];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: 'SPEC-002' must be a sourced requirement.");
        (new LinkValidator())->validate($items, []);
    }

    public function testValidateRejectsRequirementThatIsNotSourced(): void
    {
        $items = [
            'SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [], ['REQ-001'], [], [], '', 'sourced', '', 'b.yaml', []),
            'REQ-001' => new Item('REQ-001', 'requirement', 'Names start with a letter.', 'supported', null, [], [], [], [], [], '', 'original', 'Local rule.', 'a.yaml', []),
        ];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: 'REQ-001' must be a sourced requirement.");
        (new LinkValidator())->validate($items, []);
    }

    public function testValidateRejectsUnknownRunner(): void
    {
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [new TestReference('unit', 'Test::testPass'), new TestReference('missing', 'Test::testPass')], [], [], [], '', 'sourced', '', 'b.yaml', [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: unknown runner 'missing'.");
        (new LinkValidator())->validate($items, ['unit' => new RunnerConfig('phpunit', ['phpunit'], '/project')]);
    }

    public function testValidateRejectsRunnerNamedByExtension(): void
    {
        $items = ['SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [new TestReference('phpunit', 'Test::testPass')], [], [], [], '', 'sourced', '', 'b.yaml', [])];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: unknown runner 'phpunit'.");
        (new LinkValidator())->validate($items, ['unit' => new RunnerConfig('phpunit', ['phpunit'], '/project')]);
    }

    public function testValidateChecksEveryItem(): void
    {
        $items = [
            'SPEC-001' => new Item('SPEC-001', 'specification', 'The parser shall require a leading letter.', 'supported', null, [], [], [], [], [], '', 'sourced', '', 'b.yaml', []),
            'SPEC-002' => new Item('SPEC-002', 'specification', 'The parser shall accept digits.', 'supported', null, [], [], [], ['SPEC-003'], [], '', 'sourced', '', 'b.yaml', []),
        ];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-002: missing or self reference 'SPEC-003'.");
        (new LinkValidator())->validate($items, []);
    }
}
