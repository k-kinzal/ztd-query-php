<?php

namespace Library { class Box { public const VALUE = 7; } function value() { return Box::VALUE; } }
namespace Demo { use Library\{Box, function value}; function subject() { return value(); } }
