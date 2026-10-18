<?php

class Box { public int $value = 1; }
function subject() { $a = new Box(); $b = $a; $b->value = 7; return $a->value; }
