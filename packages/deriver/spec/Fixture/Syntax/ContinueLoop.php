<?php

function subject() { $sum = 0; foreach ([1, 2, 3] as $value) { if ($value === 2) { continue; } $sum += $value; } return $sum; }
