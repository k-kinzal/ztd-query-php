<?php

class Box { public int $value = 7 { get => $this->value * 2; } }
function subject() { return (new Box())->value; }
