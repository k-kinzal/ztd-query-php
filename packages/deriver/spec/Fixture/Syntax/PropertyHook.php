<?php

class Box { public int $value { get => 7; } }
function subject() { return (new Box())->value; }
