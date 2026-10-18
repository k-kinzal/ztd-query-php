<?php

declare(strict_types=1);

namespace HarborOps\Command;

use DomainException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class OperationCommand extends Command
{
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return $this->perform($input, $output);
        } catch (DomainException|InvalidArgumentException $error) {
            (new SymfonyStyle($input, $output))->error($error->getMessage());
            return self::INVALID;
        }
    }
    abstract protected function perform(InputInterface $input, OutputInterface $output): int;
    protected function positive(InputInterface $input, string $name, bool $option = false): int
    {
        $value = filter_var($option ? $input->getOption($name) : $input->getArgument($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new InvalidArgumentException($name.' must be a positive integer.');
        }
        return $value;
    }
}
