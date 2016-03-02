#!/usr/bin/env php
<?php

$app = require __DIR__ . '/app.php';
$app->register(new Knp\Provider\ConsoleServiceProvider(), [
    'console.name' => 'Wikimedia Relevance Scorer',
    'console.version' => '0.0.1',
    'console.project_directory' => __DIR__,
]);

$console = $app['console'];
$console->add(new WikiMedia\RelevanceScoring\Console\Import());
$console->add(new WikiMedia\RelevanceScoring\Console\PurgeQuery());
$console->add(new WikiMedia\RelevanceScoring\Console\ImportPending());
$console->run();

