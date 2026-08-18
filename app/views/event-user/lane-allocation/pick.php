<?php
/** Lane allocation — choose a round. */
$title     = 'Lane Allocation';
$blurb     = 'Choose a round to draw its lanes. Progress shows how much of each draw is done.';
$target    = 'lane-allocation';
$emptyHint = 'Rounds are created under Rounds & Heats — a race needs at least one before its lanes can be drawn.';
require APP_ROOT . '/views/event-user/_round-picker.php';
