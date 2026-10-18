<?php

namespace Library { function value() { return 7; } }
namespace Demo { use function Library\value as read; function subject() { return read(); } }
