<?php

class Box { public int $value = 7; public function read() { return $this->value; } }
function subject() { $box = new Box(); $call = $box->read(...); return $call(); }
