<?php

class Box { public int $value = 7; public function identity(): self { return $this; } }
function subject() { return (new Box())->identity()->value; }
