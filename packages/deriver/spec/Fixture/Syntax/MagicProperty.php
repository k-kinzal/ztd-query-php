<?php

class Box { public string $value { get => __PROPERTY__; } }
function subject() { return (new Box())->value; }
