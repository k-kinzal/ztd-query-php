<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\ContextualValueDefinitions;
use SqlFaker\Sqlite\Generation\Lexeme\ValueDefinitions;

#[CoversClass(ContextualValueDefinitions::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(ValueDefinitions::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class ContextualValueDefinitionsTest extends TestCase
{
    public function testCreateRestrictsTheContextualDomain(): void
    {
        $generator = (new ContextualValueDefinitions())->create('mysql-8.4.7');
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ROTATE_KEY_ENGINE']), 0, new ResolvedOutput(), null));
        self::assertNotNull($result);
        self::assertSame(['INNODB', 'BINLOG'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ROTATE_KEY_ENGINE']), 0, new ResolvedOutput(), 'unknown'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testDomainPreservesCompatibleExplicitCase(): void
    {
        $generator = (new ContextualValueDefinitions())->domain('MODE', ['VALID'], 'checked_rule');
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['MODE']), 0, new ResolvedOutput(), 'valid'));
        self::assertNotNull($result);
        self::assertSame('valid', [...$result->sequences()][0]->lexemes[0]->text);
    }
    public function testCreateEnumeratesOnlySourceAcceptedTernaryValues(): void
    {
        $generator = (new ContextualValueDefinitions())->create('mysql-8.4.7');
        $input = new LexemeInput(TerminalSequence::fromNames(['TERNARY_OPTION_NUMBER']), 0, new ResolvedOutput());
        $result = $generator->generate($input);
        self::assertNotNull($result);
        self::assertSame(['0', '1'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput($input->terminals, 0, new ResolvedOutput(), '2'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }
}
