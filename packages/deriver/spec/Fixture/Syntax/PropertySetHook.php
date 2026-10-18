<?php

class Box { public int $value = 1 { set { $this->value = $value * 2; } } }
function subject() { $box = new Box(); $box->value = 7; return $box->value; }
