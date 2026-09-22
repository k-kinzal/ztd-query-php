<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Config\Loader;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class Application
{
    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $input = new ArgvInput(['requirements', ...$arguments]);
        $output = new ConsoleOutput();
        try {
            $console = new ConsoleApplication('Requirements');
            $console->setAutoExit(false);
            $console->setCatchExceptions(false);
            $console->getDefinition()->addOptions([
                new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display command help, or the application overview when no command is given.'),
                new InputOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to the YAML project configuration.', 'requirements.yaml'),
                new InputOption('json', null, InputOption::VALUE_NONE, 'Write a JSON report to stdout instead of terminal tables.'),
            ]);
            foreach ([
                'check' => 'Verify quotations against the declared source scopes.',
                'coverage' => 'Show source coverage and enforce total and differential gates.',
                'spec' => 'Execute the tests linked to selected specifications.',
                'list' => 'Browse specifications and requirements using filters.',
                'lint' => 'Validate schemas, EARS syntax and cross-file traceability.',
                'format' => 'Format YAML and Markdown definition documents.',
            ] as $name => $description) {
                $console->add($this->command($name, $description));
            }
            $overview = false;
            try {
                $helpInput = clone $input;
                $helpInput->bind($console->getDefinition());
                $overview = in_array($helpInput->getFirstArgument(), [null, 'help'], true)
                    && !$helpInput->hasParameterOption(['--version', '-V']);
            } catch (ExceptionInterface) {
                $overview = false;
            }
            if ($overview) {
                if (in_array('--no-ansi', $arguments, true)) {
                    $output->setDecorated(false);
                }
                if (in_array('--ansi', $arguments, true)) {
                    $output->setDecorated(true);
                }
                if (in_array('--quiet', $arguments, true) || in_array('-q', $arguments, true)) {
                    $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
                }
                (new DescriptorHelper())->describe($output, $console);
                return 0;
            }
            return $console->run($input, $output);
        } catch (Throwable $error) {
            if (in_array('--json', $arguments, true)) {
                $output->writeln(json_encode(['passed' => false, 'errors' => [$error->getMessage()]], JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
            } else {
                (new SymfonyStyle($input, $output->getErrorOutput()))->error(OutputFormatter::escape($error->getMessage()));
            }
            return 2;
        }
    }

    private function command(string $name, string $description): Command
    {
        $command = new Command($name);
        $command->setDescription($description);
        $command->setHelp('Run <info>requirements ' . $name . ' --config requirements.yaml</info>.' . "\n\n" . 'Exit codes: 0 success, 1 failed check/gate/spec/format, 2 invalid configuration or execution error.');
        foreach (Options::definitions($name) as $option) {
            $command->addOption($option[0], null, $option[1] ? InputOption::VALUE_NONE : InputOption::VALUE_REQUIRED, $option[2]);
        }
        $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($name): int {
            $options = Options::fromInput($name, $input);
            $project = (new Loader())->load($options->text('config', 'requirements.yaml') ?? 'requirements.yaml');
            $report = (new Executor())->execute($project, $options);
            if ($options->flag('json')) {
                $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
            } else {
                (new Reporter())->render($report, $options, $input, $output);
            }
            return ($report['passed'] ?? true) === true ? 0 : 1;
        });
        return $command;
    }
}
