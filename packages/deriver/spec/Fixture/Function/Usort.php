<?php

function subject() { $values = [3,1,2]; usort($values, fn ($a, $b) => $a <=> $b); return $values; }
