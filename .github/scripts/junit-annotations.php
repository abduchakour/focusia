<?php

// Turns the failed tests of a JUnit report into GitHub annotations (shown on the run page).
// Usage: php .github/scripts/junit-annotations.php junit.xml

$report = $argv[1] ?? 'junit.xml';
if (! is_file($report)) {
    exit(0);
}

$xml = simplexml_load_file($report);
foreach ($xml->xpath('//testcase[failure or error]') as $case) {
    $problem = $case->failure ?: $case->error;
    $message = substr(trim((string) $problem), 0, 1500);
    $message = str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $message);
    $file = str_replace(getcwd().DIRECTORY_SEPARATOR, '', (string) $case['file']);

    echo "::error file={$file},line={$case['line']},title={$case['class']}::{$case['name']}%0A{$message}\n";
}
