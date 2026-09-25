<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\ConditionOrder;
use Requirements\Ears\LiteralMask;
use Requirements\Ears\SystemResponse;
use Requirements\Ears\Validator;
use Requirements\Ears\Wording;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Excerpt;
use Requirements\Model\Item;
use Requirements\Model\ItemValidator;
use Requirements\Model\Source;
use Requirements\Model\TestReference;

#[CoversClass(ItemValidator::class)]
#[UsesClass(Item::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(TestReference::class)]
#[UsesClass(Source::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Validator::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Wording::class)]
#[Small]
final class ItemValidatorTest extends TestCase
{
    #[DataProvider('providerValidateAcceptsItems')]
    public function testValidateAcceptsItems(Item $item): void
    {
        (new ItemValidator())->validate($item);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{Item}>
     */
    public static function providerValidateAcceptsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'The reader shall read.', 'status' => 'supported', 'source' => new Source('manual', 'source.html', 'html', 'main p'), 'evidence' => [new Excerpt('#a', 'Names shall start with a letter.')], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'sourced by evidence' => [new Item(...$item)],
            'sourced by requirements' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'requirements' => ['REQ-1']])],
            'with tests' => [new Item(...[...$item, 'tests' => [new TestReference('unit', 'Sample\PassingTest::testPass')]])],
            'requirement' => [new Item(...[...$item, 'kind' => 'requirement', 'statement' => 'Names start with letters'])],
            'unsupported with reason' => [new Item(...[...$item, 'status' => 'unsupported', 'reason' => 'Owned elsewhere.'])],
            'original' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'origin' => 'original', 'reason' => 'Support editors.'])],
            'undocumented' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'origin' => 'undocumented', 'reason' => 'Observed behavior.'])],
            'with details' => [new Item(...[...$item, 'data' => ['metadata' => ['owner' => 'me'], 'design' => [['url' => 'https://example.org/'], ['text' => 'Notes'], ['url' => 'https://example.org/', 'text' => 'Notes']]]])],
        ];
    }

    #[DataProvider('providerValidateReportsTheFirstBrokenRule')]
    public function testValidateReportsTheFirstBrokenRule(Item $item, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new ItemValidator())->validate($item);
    }

    /**
     * @return array<string, array{Item, string}>
     */
    public static function providerValidateReportsTheFirstBrokenRule(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'The reader shall read.', 'status' => 'supported', 'source' => new Source('manual', 'source.html', 'html', 'main p'), 'evidence' => [new Excerpt('#a', 'Names shall start with a letter.')], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'identity before statement' => [new Item(...[...$item, 'id' => '1', 'status' => 'unsupported']), 'Item IDs must start with a letter'],
            'statement before disposition' => [new Item(...[...$item, 'status' => 'unsupported', 'origin' => 'original']), 'SPEC-1: unsupported items require a reason.'],
            'disposition before details' => [new Item(...[...$item, 'source' => null, 'data' => ['metadata' => 'x']]), 'SPEC-1: evidence requires a source.'],
            'details' => [new Item(...[...$item, 'data' => ['metadata' => 'x']]), 'metadata must be a mapping.'],
        ];
    }

    #[DataProvider('providerIdentityAcceptsItems')]
    public function testIdentityAcceptsItems(Item $item): void
    {
        (new ItemValidator())->identity($item);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{Item}>
     */
    public static function providerIdentityAcceptsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'x', 'status' => 'supported', 'source' => null, 'evidence' => [], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'single letter' => [new Item(...[...$item, 'id' => 'a'])],
            'every allowed character' => [new Item(...[...$item, 'id' => 'Spec_1.2-b'])],
            'requirement' => [new Item(...[...$item, 'kind' => 'requirement'])],
            'unsupported' => [new Item(...[...$item, 'status' => 'unsupported'])],
            'original' => [new Item(...[...$item, 'origin' => 'original'])],
            'undocumented' => [new Item(...[...$item, 'origin' => 'undocumented'])],
        ];
    }

    #[DataProvider('providerIdentityRejectsItems')]
    public function testIdentityRejectsItems(Item $item, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new ItemValidator())->identity($item);
    }

    /**
     * @return array<string, array{Item, string}>
     */
    public static function providerIdentityRejectsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'x', 'status' => 'supported', 'source' => null, 'evidence' => [], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        $format = 'Item IDs must start with a letter and contain letters, digits, dots, underscores or dashes.';
        return [
            'leading digit' => [new Item(...[...$item, 'id' => '1SPEC']), $format],
            'leading dash' => [new Item(...[...$item, 'id' => '-SPEC']), $format],
            'space' => [new Item(...[...$item, 'id' => 'SPEC 1']), $format],
            'trailing newline' => [new Item(...[...$item, 'id' => "SPEC\n"]), $format],
            'trailing symbol' => [new Item(...[...$item, 'id' => 'SPEC#']), $format],
            'unknown kind' => [new Item(...[...$item, 'kind' => 'feature']), 'SPEC-1: invalid kind or status.'],
            'unknown status' => [new Item(...[...$item, 'status' => 'planned']), 'SPEC-1: invalid kind or status.'],
            'unknown origin' => [new Item(...[...$item, 'origin' => 'derived']), 'SPEC-1: invalid origin.'],
        ];
    }

    #[DataProvider('providerStatementAcceptsItems')]
    public function testStatementAcceptsItems(Item $item): void
    {
        (new ItemValidator())->statement($item);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{Item}>
     */
    public static function providerStatementAcceptsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'The reader shall read.', 'status' => 'supported', 'source' => null, 'evidence' => [], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'EARS specification' => [new Item(...$item)],
            'unsupported with reason' => [new Item(...[...$item, 'status' => 'unsupported', 'reason' => 'Owned elsewhere.'])],
            'requirement in free text' => [new Item(...[...$item, 'kind' => 'requirement', 'statement' => 'Names start with letters'])],
        ];
    }

    #[DataProvider('providerStatementRejectsItems')]
    public function testStatementRejectsItems(Item $item, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new ItemValidator())->statement($item);
    }

    /**
     * @return array<string, array{Item, string}>
     */
    public static function providerStatementRejectsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'The reader shall read.', 'status' => 'supported', 'source' => null, 'evidence' => [], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'unsupported without reason' => [new Item(...[...$item, 'status' => 'unsupported']), 'SPEC-1: unsupported items require a reason.'],
            'unsupported requirement without reason' => [new Item(...[...$item, 'kind' => 'requirement', 'status' => 'unsupported']), 'SPEC-1: unsupported items require a reason.'],
            'specification breaking EARS' => [new Item(...[...$item, 'statement' => 'The reader will read.']), 'SPEC-1: EARS: expected The <system name> shall <system response>.'],
        ];
    }

    #[DataProvider('providerDispositionAcceptsItems')]
    public function testDispositionAcceptsItems(Item $item): void
    {
        (new ItemValidator())->disposition($item);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{Item}>
     */
    public static function providerDispositionAcceptsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'x', 'status' => 'supported', 'source' => new Source('manual', 'source.html', 'html', 'main p'), 'evidence' => [new Excerpt('#a', 'Names shall start with a letter.')], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'evidence' => [new Item(...$item)],
            'requirements only' => [new Item(...[...$item, 'evidence' => [], 'requirements' => ['REQ-1']])],
            'requirements without source' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'requirements' => ['REQ-1']])],
            'source without evidence' => [new Item(...[...$item, 'evidence' => [], 'requirements' => ['REQ-1']])],
            'requirement with evidence' => [new Item(...[...$item, 'kind' => 'requirement'])],
            'unsupported specification with tests' => [new Item(...[...$item, 'status' => 'unsupported', 'tests' => [new TestReference('unit', 't')]])],
            'original' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'origin' => 'original', 'reason' => 'Support editors.'])],
            'undocumented with tests' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'origin' => 'undocumented', 'reason' => 'Observed.', 'tests' => [new TestReference('unit', 't')]])],
            'original requirement' => [new Item(...[...$item, 'kind' => 'requirement', 'source' => null, 'evidence' => [], 'origin' => 'original', 'reason' => 'Support editors.'])],
        ];
    }

    #[DataProvider('providerDispositionRejectsItems')]
    public function testDispositionRejectsItems(Item $item, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new ItemValidator())->disposition($item);
    }

    /**
     * @return array<string, array{Item, string}>
     */
    public static function providerDispositionRejectsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'x', 'status' => 'supported', 'source' => new Source('manual', 'source.html', 'html', 'main p'), 'evidence' => [new Excerpt('#a', 'Names shall start with a letter.')], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        $requirement = 'SPEC-1: requirements cannot have tests, requirement parents or unsupported status; record disposition on specifications.';
        $independent = 'SPEC-1: independent items require a reason and cannot claim a source or requirement.';
        return [
            'requirement with tests' => [new Item(...[...$item, 'kind' => 'requirement', 'tests' => [new TestReference('unit', 't')]]), $requirement],
            'requirement with parents' => [new Item(...[...$item, 'kind' => 'requirement', 'requirements' => ['REQ-0']]), $requirement],
            'unsupported requirement' => [new Item(...[...$item, 'kind' => 'requirement', 'status' => 'unsupported', 'reason' => 'Owned elsewhere.']), $requirement],
            'evidence without source' => [new Item(...[...$item, 'source' => null]), 'SPEC-1: evidence requires a source.'],
            'original with source' => [new Item(...[...$item, 'evidence' => [], 'origin' => 'original', 'reason' => 'Support editors.']), $independent],
            'original with requirements' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'requirements' => ['REQ-1'], 'origin' => 'original', 'reason' => 'Support editors.']), $independent],
            'original without reason' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'origin' => 'original']), $independent],
            'undocumented without reason' => [new Item(...[...$item, 'source' => null, 'evidence' => [], 'origin' => 'undocumented']), $independent],
            'sourced without evidence or requirements' => [new Item(...[...$item, 'evidence' => []]), 'SPEC-1: provide evidence or requirements, or explicitly mark an independent origin with a reason.'],
            'sourced without source, evidence or requirements' => [new Item(...[...$item, 'source' => null, 'evidence' => []]), 'SPEC-1: provide evidence or requirements, or explicitly mark an independent origin with a reason.'],
        ];
    }

    #[DataProvider('providerDetailsAcceptsItems')]
    public function testDetailsAcceptsItems(Item $item): void
    {
        (new ItemValidator())->details($item);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{Item}>
     */
    public static function providerDetailsAcceptsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'x', 'status' => 'supported', 'source' => null, 'evidence' => [], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'no details' => [new Item(...$item)],
            'metadata' => [new Item(...[...$item, 'data' => ['metadata' => ['owner' => 'me', 'nested' => ['a' => 1]]]])],
            'empty design list' => [new Item(...[...$item, 'data' => ['design' => []]])],
            'design url' => [new Item(...[...$item, 'data' => ['design' => [['url' => 'https://example.org/']]]])],
            'design text' => [new Item(...[...$item, 'data' => ['design' => [['text' => 'Notes']]]])],
            'design url and text' => [new Item(...[...$item, 'data' => ['design' => [['url' => 'https://example.org/', 'text' => 'Notes'], ['text' => 'More']]]])],
        ];
    }

    #[DataProvider('providerDetailsRejectsItems')]
    public function testDetailsRejectsItems(Item $item, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new ItemValidator())->details($item);
    }

    /**
     * @return array<string, array{Item, string}>
     */
    public static function providerDetailsRejectsItems(): array
    {
        $item = ['id' => 'SPEC-1', 'kind' => 'specification', 'statement' => 'x', 'status' => 'supported', 'source' => null, 'evidence' => [], 'tests' => [], 'requirements' => [], 'related' => [], 'labels' => [], 'category' => '', 'origin' => 'sourced', 'reason' => '', 'file' => 'definition.yaml', 'data' => []];
        return [
            'metadata not a mapping' => [new Item(...[...$item, 'data' => ['metadata' => 'owner']]), 'metadata must be a mapping.'],
            'metadata list' => [new Item(...[...$item, 'data' => ['metadata' => ['owner']]]), 'metadata must have string keys.'],
            'design not a list' => [new Item(...[...$item, 'data' => ['design' => ['url' => 'https://example.org/']]]), 'design must be a list.'],
            'design entry not a mapping' => [new Item(...[...$item, 'data' => ['design' => ['https://example.org/']]]), 'design must be a mapping.'],
            'unknown design field' => [new Item(...[...$item, 'data' => ['design' => [['url' => 'https://example.org/', 'title' => 'x']]]]), "design: unknown field 'title'."],
            'empty design entry' => [new Item(...[...$item, 'data' => ['design' => [['text' => 'Notes'], []]]]), 'SPEC-1: a design reference needs url or text.'],
            'blank url' => [new Item(...[...$item, 'data' => ['design' => [['url' => ' ']]]]), 'url must be a nonempty string.'],
            'blank text after url' => [new Item(...[...$item, 'data' => ['design' => [['url' => 'https://example.org/', 'text' => '']]]]), 'text must be a nonempty string.'],
            'null text in later entry' => [new Item(...[...$item, 'data' => ['design' => [['text' => 'Notes'], ['text' => null]]]]), 'text must be a nonempty string.'],
        ];
    }
}
