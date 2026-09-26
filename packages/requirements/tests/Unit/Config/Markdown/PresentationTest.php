<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Presentation;
use Requirements\Config\Markdown\Reference;

#[CoversClass(Presentation::class)]
#[UsesClass(Reference::class)]
#[Small]
final class PresentationTest extends TestCase
{
    public function testPresentationStartsWithNothingRemembered(): void
    {
        $presentation = new Presentation();
        self::assertSame([], $presentation->links);
        self::assertSame([], $presentation->badges);
        self::assertSame([], $presentation->citations);
        self::assertSame([], $presentation->references);
    }

    public function testPresentationKeepsWhatAReadRecords(): void
    {
        $presentation = new Presentation();
        $reference = new Reference('REQ-001', 'reference.yaml#req-001', 'definition.md');
        $presentation->links['SPEC-001']['requirements'] = ['REQ-001' => 'reference.yaml#req-001'];
        $presentation->badges['SPEC-001']['label'] = ['grammar' => ['url' => 'assets/grammar.svg', 'title' => null]];
        $presentation->citations['SPEC-001'] = [null, ['url' => 'source.html#a', 'label' => 'Names']];
        $presentation->references[] = $reference;
        self::assertSame(['SPEC-001' => ['requirements' => ['REQ-001' => 'reference.yaml#req-001']]], $presentation->links);
        self::assertSame(['SPEC-001' => ['label' => ['grammar' => ['url' => 'assets/grammar.svg', 'title' => null]]]], $presentation->badges);
        self::assertSame(['SPEC-001' => [null, ['url' => 'source.html#a', 'label' => 'Names']]], $presentation->citations);
        self::assertSame([$reference], $presentation->references);
    }
}
