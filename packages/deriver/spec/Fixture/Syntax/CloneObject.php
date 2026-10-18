<?php

class Box { public int $value = 7; }
function subject() { $a = new Box(); $b = clone $a; $b->value = 9; return [$a->value, $b->value]; }
