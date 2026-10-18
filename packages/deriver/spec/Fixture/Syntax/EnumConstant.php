<?php

enum State { case Ready; public const LABEL = "ready"; }
function subject() { return State::LABEL; }
