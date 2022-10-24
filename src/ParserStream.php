<?php

//  * eatchr(accept, errmsg)  - accept a char in accept, or report an error, advance cursor
//  * eatstr(substr, errmsg)  - accept a string, or report an error, advance cursor
//  * int testchr(accept)     - 1 if next char in accept, else -1, don't advance
//  * int teststr(substr)     - strlen(substr) if starts with substr, else -1, don't advance

class ParserStream {

    public $curr = "";      // current character
    public $offset = -1;    // -1 because we increment it before reading the first char
    public $charno = 0;     // 1-indexed column of input stream.
    public $lineno = 1;     // 1-indexed line number of input stream.
    public $active = false; // true from init, false when you hit EOF
    public $cursor = 0;     // only set when you call save() or load()

    public function __construct($fp) {
        if (!is_resource($fp)) throw new Exception("Stream must be a resource");
        if ($fp === NULL)      throw new Exception("Invalid input stream");
        $this->fp = $fp;
        $this->rewind();
    }

    public function eatchr(string $accept, string $errmsg) {
        $state = $this->save();
        if (strchr($accept, $this->peek()) === FALSE) {
            $slice = str_replace("\n", "\\n", $this->substr($state, 10));
            $errmsg = $errmsg ? $errmsg : "expected one of '$accept' but found '$slice'";
            die($this->err($errmsg));
        }
        $this->next();
    }

    public function eatstr(string $string, string $errmsg) {
        $state = $this->save();
        for ($i = 0; $i < strlen($string); $i++) {
            if ($this->curr !== $string[$i]) {
                $this->load($state);
                $cursor = $state["cursor"];
                $slice = $this->substr($state, strlen($string)+10);
                $slice = str_replace("\n", "\\n", $slice);
                $errmsg = $errmsg ?: "Expected \"$string\", but found '{$slice}' instead";
                die($this->err($errmsg));
            }
            $this->next();
        }
    }

    public function substr($state, $len) {
        return $this->slice($state["cursor"], $state["cursor"] + $len);
    }

    public function testchr(string $accept) {
        return (strchr($accept, $this->peek()) === FALSE) ? -1 : 1;
    }

    public function teststr(string $substr) {
        $state = $this->save();
        for ($i = 0; $i < strlen($substr); $i++) {
            if ($this->curr !== $substr[$i]) {
                $this->load($state);
                return -1;
            }
            $this->next();
        }
        $this->load($state);
        return strlen($substr);
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
        if ($this->curr === "\n") {
            $this->lineno++;
            $this->charno = 1;
        } else {
            $this->charno++;
        }
        $this->curr = $c;
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
    /// DEPRECATED in favor of test and eat functions
    /// SEE ALSO - eatstr, eatchr, teststr, testchr
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

    public function err(string $msg = ""): string {
        $position = "line {$this->lineno}, char {$this->charno}";
        return "parse error: $msg near $position\n";
    }

    public static function fileRange($fp, int $from, int $to): string
    {
        fseek($fp, $from, SEEK_SET);
        return fread($fp, $to - $from);
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
};
