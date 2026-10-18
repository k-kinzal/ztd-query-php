<?php

function subject() { $sum = 0; foreach ([2, 3] as $value): $sum += $value; endforeach; return $sum; }
