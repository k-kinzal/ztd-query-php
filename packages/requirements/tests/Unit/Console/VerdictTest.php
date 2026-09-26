<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\Options;
use Requirements\Console\Text;
use Requirements\Console\Verdict;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(Verdict::class)]
#[UsesClass(Options::class)]
#[UsesClass(Text::class)]
#[UsesClass(Fields::class)]
#[Small]
final class VerdictTest extends TestCase
{
    /**
     * @param array<string, mixed> $report
     * @param array<string, string|bool> $values
     */
    #[DataProvider('providerRenderPassed')]
    public function testRenderClosesAPassedReportWithTheCommandMessage(string $command, array $values, array $report, string $expected): void
    {
        $output = new BufferedOutput();
        (new Verdict())->render(new SymfonyStyle(new ArrayInput([]), $output), $report, new Options($command, $values));
        self::assertSame($expected, preg_replace('/ +$/m', '', $output->fetch()));
    }

    /**
     * @return array<string, array{string, array<string, string|bool>, array<string, mixed>, string}>
     */
    public static function providerRenderPassed(): array
    {
        return [
            'lint' => ['lint', [], ['passed' => true, 'message' => '3 items validated.'], "\n [OK] 3 items validated.\n\n"],
            'lint escapes markup' => ['lint', [], ['passed' => true, 'message' => "<info>3</info> items\x07 validated."], "\n [OK] \\<info\\>3\\</info\\> items validated.\n\n"],
            'check' => ['check', [], ['passed' => true, 'errors' => []], "\n [OK] Source evidence is valid.\n\n"],
            'coverage' => ['coverage', [], ['passed' => true, 'errors' => []], "\n [OK] Coverage gates passed.\n\n"],
            'spec' => ['spec', [], ['passed' => true, 'errors' => []], "\n [OK] Specification verification completed.\n\n"],
            'spec without tests' => ['spec', ['no-test' => true], ['passed' => true, 'errors' => []], "\n [OK] Records displayed; tests were not run.\n\n"],
            'format unchanged' => ['format', [], ['passed' => true, 'changed' => []], "\n [OK] No formatting changes.\n\n"],
            'format changed' => ['format', [], ['passed' => true, 'changed' => ['definition.yaml']], "\n [OK] Documents formatted.\n\n"],
            'passed missing' => ['check', [], [], "\n [OK] Source evidence is valid.\n\n"],
            'unknown command' => ['unknown', [], ['passed' => true], ''],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    #[DataProvider('providerRenderFailed')]
    public function testRenderReportsAFailedReport(string $command, array $report, string $expected): void
    {
        $output = new BufferedOutput();
        (new Verdict())->render(new SymfonyStyle(new ArrayInput([]), $output), $report, new Options($command, []));
        self::assertSame($expected, preg_replace('/ +$/m', '', $output->fetch()));
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function providerRenderFailed(): array
    {
        return [
            'format without errors' => ['format', ['passed' => false, 'changed' => ['definition.yaml']], "\n [ERROR] Documents need formatting. Run requirements format.\n\n"],
            'spec without errors' => ['spec', ['passed' => false, 'errors' => []], "\n [ERROR] Specification verification failed.\n\n"],
            'unknown without errors' => ['unknown', ['passed' => false], "\n [ERROR] Specification verification failed.\n\n"],
            'passed not true' => ['spec', ['passed' => 'yes'], "\n [ERROR] Specification verification failed.\n\n"],
            'spec with an error' => ['spec', ['passed' => false, 'errors' => ['No specifications or requirements selected.']], "\n [ERROR] No specifications or requirements selected.\n\n"],
            'format with an error' => ['format', ['passed' => false, 'errors' => ['Broken.'], 'changed' => []], "\n [ERROR] Broken.\n\n"],
            'repeated errors' => ['check', ['passed' => false, 'errors' => ['Same error.', 'Same error.']], "\n [ERROR] Same error.\n\n         Same error.\n\n"],
            'escaped errors' => ['coverage', ['passed' => false, 'errors' => ["<error>bad</error>\x1b[0m"]], "\n [ERROR] \\<error\\>bad\\</error\\>[0m\n\n"],
        ];
    }

    public function testRenderShowsErrorsBeforeTheSuccessOfAPassedReport(): void
    {
        $output = new BufferedOutput();
        (new Verdict())->render(new SymfonyStyle(new ArrayInput([]), $output), ['passed' => true, 'errors' => ['A warning.']], new Options('check', []));
        self::assertSame("\n [ERROR] A warning.\n\n [OK] Source evidence is valid.\n\n", preg_replace('/ +$/m', '', $output->fetch()));
    }

    /**
     * @param array<string, mixed> $report
     */
    #[DataProvider('providerRenderRejected')]
    public function testRenderRejectsErrorsThatAreNotAListOfStrings(array $report, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Verdict())->render(new SymfonyStyle(new ArrayInput([]), new BufferedOutput()), $report, new Options('check', []));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function providerRenderRejected(): array
    {
        return [
            'mapping' => [['passed' => false, 'errors' => ['a' => 'Error.']], 'errors must be a list.'],
            'text' => [['passed' => false, 'errors' => 'Error.'], 'errors must be a list.'],
            'number' => [['passed' => false, 'errors' => [1]], 'errors must contain nonempty strings.'],
            'blank' => [['passed' => false, 'errors' => [' ']], 'errors must contain nonempty strings.'],
        ];
    }
}
