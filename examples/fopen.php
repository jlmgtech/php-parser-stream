<?php

require_once __DIR__ . "/" . "../src/ParserStream.php";

// test the parser stream with a memory file resource
echo "reading input.txt\n";

// read stdin to memory file because stdin is not seekable.
$fp = fopen(__DIR__ . "/" . "input.txt", "r");

$stream = new ParserStream($fp);
// note - constructor rewinds the file pointer automatically.

$success = false;
while ($stream->active) {
    if ($stream->eatchr("q", "")) {
        echo "\nquit - QUITTING\n";
        $success = true;
        break;
    } else {
        echo $stream->curr;
    }
    $stream->next();
}

if ($success) {
    echo "exit successful!\n";
} else {
    // error reporting example:
    $range = $stream->slice($stream->offset, $stream->offset + 10);
    $range = str_replace("\n", "\\n", $range);
    echo $stream->err("expected 'quit' but found '$range'...");
}

echo "\n";
