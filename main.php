<?php require_once __DIR__ . "/" . "ParserStream.php";


/* doc     -> binop
 * binop   -> unop ([-+/*] unop)*
 * unop    -> [+-] unop | primary
 * primary -> number | (binop)
 */


// thoughts:
// functions that automate lexical error reporting
//  * eatchr(accept, errmsg)  - accept a char in accept, or report an error, advance cursor
//  * eatstr(substr, errmsg)  - accept a string, or report an error, advance cursor
//  * eatregex(regex, errmsg) - accept a regex, or report an error, advance cursor
//  * int testchr(accept)     - 1 if next char in accept, else -1, don't advance
//  * int teststr(substr)     - strlen(substr) if starts with substr, else -1, don't advance
//  * int testregex(regex)    - strlen(match) if cursor matches regex, else -1, don't advance

class Parser {

    private $stream;

    public function __construct($input) {
        $fp = NULL;
        if (is_string($input)) {
            $fp = fopen("php://memory", "rw");
            fwrite($fp, $input);
        } else if (is_resource($input)) {
            $fp = $input;
        } else {
            throw new Exception("invalid input for Parser");
        }
        $this->stream = new ParserStream($fp);
    }

    public function parse() {
        return $this->parse_binop();
    }

    public function parse_binop() {
        $output = [
            "type" => "binop",
            "operators" => [],
            "values" => [$this->parse_unop()],
        ];
        $op = $this->stream->curr;
        while (in_array($op, ["+", "-", "/", "*"])) {
            echo "op = $op\n";
            $this->stream->next();
            $value = $this->parse_unop();
            $output["operators"][] = $op;
            $output["values"][] = $value;
            $this->stream->next();
            $op = $this->stream->curr;
        }
        return $output;
    }

    public function parse_unop() {
        $op = $this->stream->curr;
        echo "unop: op = '$op'\n";
        if (in_array($op, ["-", "!"])) {
            $this->stream->next();
            $right = $this->parse_unop();
            return [
                "type" => "unop",
                "operator" => $op,
                "value" => $right,
            ];
        } else {
            $retval = $this->parse_number();
            return $retval;
        }
    }

    public function parse_number() {
        $lexval = "";
        switch ($this->stream->curr) {
            case "0": case "1": case "2": case "3": case "4":
            case "5": case "6": case "7": case "8": case "9":
                $lexval .= $this->stream->curr;
                $this->stream->next();
                while (in_array($this->stream->curr, ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"])) {
                    $lexval .= $this->stream->curr;
                    $this->stream->next();
                }
                return [
                    "type" => "number",
                    "value" => intval($lexval),
                ];
            case "(":
                $this->stream->next();
                $retval = $this->parse_binop();
                if ($this->stream->curr != ")") {
                    throw new Exception("expected ')'");
                }
                $this->stream->next();
                return $retval;
            default:
                throw new Exception("invalid number, found '" . $this->stream->curr . "'");
        }
    }

    public function parse_paren() {
        $this->stream->eat("(");
        $output = $this->parse_binop();
        $this->stream->eat(")");
        return $output;
    }

};

$parser = new Parser("1+2+3");
echo json_encode($parser->parse(), JSON_PRETTY_PRINT);
echo "\n";
