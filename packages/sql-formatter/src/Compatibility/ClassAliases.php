<?php

declare(strict_types=1);

namespace SqlFormatter\Compatibility;

class_alias(\SqlFormatter\Facade\Formatter::class, 'SqlFormatter\\Formatter');
class_alias(\SqlFormatter\Core\FormatOptions::class, 'SqlFormatter\\FormatOptions');
class_alias(\SqlFormatter\Core\Style::class, 'SqlFormatter\\Style');
class_alias(\SqlFormatter\Core\FormattingException::class, 'SqlFormatter\\FormattingException');
