<?php

class ParserStream {

    public $curr = "";
    public $offset = -1;
    public $charno = 0;
    public $lineno = 1;
    public $active = false;
    public $cursor = 0;

    public function __construct($fp) {
        if (!is_resource($fp)) throw new Exception("Stream must be a resource");
        if ($fp === NULL)      throw new Exception("Invalid input stream");
        $this->fp = $fp;
        $this->rewind();
    }

    public function ftell() {
        return ftell($this->fp);
    }

    public function next(): bool {
        $c = fgetc($this->fp);
        if ($c === false) {
            $this->curr = NULL;
            $this->active = false;
            return false;
        }
        $this->curr = $c;
        if ($this->curr === "\n") {
            $this->lineno++;
            $this->charno = 0;
        } else {
            $this->charno++;
        }

        $this->offset++;
        return true;
    }

    public function save(): array {
        return [
            "cursor" => ftell($this->fp),
            "offset" => $this->offset,
            "charno" => $this->charno,
            "lineno" => $this->lineno,
            "active" => $this->active,
            "curr"   => $this->curr,
        ];
    }

    public function load(array $state) {
        $this->offset = $state["offset"];
        $this->charno = $state["charno"];
        $this->lineno = $state["lineno"];
        $this->active = $state["active"];
        $this->curr = $state["curr"];
        if (fseek($this->fp, $state["cursor"], SEEK_SET) === -1) {
            throw new Exception("Failed to seek to offset $this->offset");
        }
    }

    public function peek(): string {
        $state = $this->save();
        $this->next();
        $c = $this->curr;
        $this->load($state);
        return $c;
    }

    /// returns a string between two offsets, left inclusive
    public function region(array $state_start, array $state_end): string {
        $from = max($state_start["cursor"] - 1, 0);
        $to = $state_end["cursor"];
        return $this->slice($from, $to);
    }

    /// returns a string between a start and end state, left inclusive
    public function slice(int $from, int $to): string {
        $saved = ftell($this->fp);
        fseek($this->fp, $from, SEEK_SET);
        $str = fread($this->fp, $to - $from);
        fseek($this->fp, $saved, SEEK_SET);
        return $str;
    }

    /// consume the following string from the input stream, advancing the cursor.
    /// if the string is not found, the cursor is not advanced and false is returned.
    public function eat(string $literal): bool {
        $state = $this->save();
        for ($i = 0; $i < strlen($literal); $i++) {
            if ($this->curr !== $literal[$i]) {
                $this->load($state);
                return false;
            }
            $this->next();
        }
        return true;
    }

    public function err(string $msg = "") {
        $position = "line {$this->lineno}, char {$this->charno}";
        return "parse error: $msg near $position\n";
    }

    public function __destruct() {
        printf("\n%s\n", "closing stream");
        fclose($this->fp);
    }

    public function rewind() {
        $this->load([
            "curr" => "",
            "offset" => -1,
            "charno" => 0,
            "lineno" => 1,
            "active" => true,
            "cursor" => 0,
        ]);
        $this->next();
    }
}

if (getenv("TEST")) {
    // now test it with a memory stream

    $fp = fopen("php://memory", "rw");
    fprintf($fp, "something, basically anything else...\n");
    rewind($fp);

    $stream = new ParserStream($fp);
    $state = $stream->save();



    // print the entire parser stream:
    echo "received: ";
    while ($stream->curr !== NULL) {
        echo $stream->curr;
        $stream->next();
    }
    echo "\n";

    // basically rewind it:
    $stream->load($state);

    // you could also do: $stream->rewind();

    if ($stream->eat("quit")) {
        echo "quitting\n";
    } else {
        $range = $stream->slice($stream->offset, $stream->offset + 10);
        $range = str_replace("\n", "\\n", $range);
        echo $stream->err("expected 'quit' but found '$range'...");
    }

    echo "\n";
}
