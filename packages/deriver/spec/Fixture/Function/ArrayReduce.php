<?php

function subject() { return array_reduce([1,2], fn ($carry, $value) => $carry + $value, 0); }
