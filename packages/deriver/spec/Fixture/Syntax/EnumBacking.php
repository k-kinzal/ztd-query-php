<?php

enum State: string { case Ready = "ready"; }
function subject() { return State::Ready->value; }
