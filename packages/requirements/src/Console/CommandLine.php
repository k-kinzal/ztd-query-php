<?php

declare(strict_types=1);

namespace Requirements\Console;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Declares the commands, their options and the global options of the command line.
 */
final class CommandLine
{
    /**
     * Creates the console application; it neither exits nor catches exceptions itself.
     *
     * @return ConsoleApplication The application with every command registered
     */
    public function create(): ConsoleApplication
    {
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
            'spec' => 'Browse specifications and requirements, and verify linked tests unless --no-test is set.',
            'lint' => 'Validate schemas, EARS syntax and cross-file traceability.',
            'format' => 'Format YAML and Markdown definition documents.',
        ] as $name => $description) {
            $console->add($this->command($name, $description));
        }
        return $console;
    }

    /**
     * Declares one command with its options and handler.
     *
     * @param string $name The command name
     * @param string $description The one-line description shown in the overview
     *
     * @return Command The command
     */
    public function command(string $name, string $description): Command
    {
        $command = new Command($name);
        $command->setDescription($description);
        $command->setHelp('Run <info>requirements ' . $name . ' --config requirements.yaml</info>.' . "\n\n" . 'Exit codes: 0 success, 1 failed check/gate/spec/format, 2 invalid configuration or execution error.');
        foreach (Options::definitions($name) as $option) {
            $command->addOption($option[0], null, $option[1] ? InputOption::VALUE_NONE : InputOption::VALUE_REQUIRED, $option[2]);
        }
        $command->setCode(new CommandHandler($name));
        return $command;
    }
}
