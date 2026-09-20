<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Extension\WordPressExtension;
use SqlCatalog\Sql\StatementKind;

#[CoversClass(WordPressExtension::class)]
#[UsesClass(SinkSpec::class)]
final class WordPressExtensionTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('wordpress', (new WordPressExtension())->name());
    }

    public function testDescriptionMentionsWhatItCovers(): void
    {
        self::assertStringContainsString('wpdb', (new WordPressExtension())->description());
    }

    public function testPrepareHandsTheStatementBackRatherThanSendingIt(): void
    {
        $prepare = array_values(array_filter(
            (new WordPressExtension())->sinks(),
            static fn (SinkSpec $sink): bool => $sink->id === 'wordpress.prepare',
        ));

        self::assertSame(SinkRole::Compose, $prepare[0]->role);
        self::assertSame(0, $prepare[0]->sqlParameter);
        self::assertSame(1, $prepare[0]->valuesFrom);
    }

    public function testMethodsNameTheKindTheReadingCallsImply(): void
    {
        $methods = (new WordPressExtension())->methods();

        self::assertSame(StatementKind::Select, $methods['get_results']);
        self::assertSame(StatementKind::Select, $methods['get_var']);
        self::assertNull($methods['query']);
    }

    public function testSinksCoverInterpolatingAndIssuingAStatement(): void
    {
        $roles = array_map(static fn (SinkSpec $sink): SinkRole => $sink->role, (new WordPressExtension())->sinks());

        self::assertContains(SinkRole::Compose, $roles);
        self::assertContains(SinkRole::Query, $roles);
    }

    public function testEverySinkIsDescribedExactly(): void
    {
        $described = array_map(
            static fn (SinkSpec $sink): string => implode('|', [
                $sink->id,
                $sink->callKind->value,
                $sink->receiverType ?? '-',
                $sink->name,
                $sink->role->value,
                $sink->sqlParameter ?? '-',
                $sink->valuesParameter ?? '-',
                $sink->valuesFrom ?? '-',
                $sink->nameParameter ?? '-',
                $sink->valueParameter ?? '-',
                ($sink->kind === null ? '-' : $sink->kind->value) . '/' . ($sink->handleType ?? '-'),
            ]),
            (new WordPressExtension())->sinks(),
        );

        self::assertSame(
            [
            'wordpress.prepare|method|wpdb|prepare|compose|0|-|1|-|-|-/-',
            'wordpress.query|method|wpdb|query|query|0|-|-|-|-|-/-',
            'wordpress.get_results|method|wpdb|get_results|query|0|-|-|-|-|select/-',
            'wordpress.get_row|method|wpdb|get_row|query|0|-|-|-|-|select/-',
            'wordpress.get_col|method|wpdb|get_col|query|0|-|-|-|-|select/-',
            'wordpress.get_var|method|wpdb|get_var|query|0|-|-|-|-|select/-',
            'wordpress.get_col_info|method|wpdb|get_col_info|query|0|-|-|-|-|select/-',
            ],
            $described,
        );
    }

    public function testGlobalsDeclareTheHandleWordPressHandsOut(): void
    {
        self::assertSame(['wpdb' => 'wpdb'], (new WordPressExtension())->globals());
    }
}
