# php-parser-stream
A minimal, error reporting parser stream used for creating parsers.


### motivation ###

I wanted to make a somewhat efficient parser that would be able to work on a file stream directly.  
In a language like C, this is definitely more efficient because you don't have to copy buffers in and out of your program.
In PHP, it makes less sense, but I want a consistent design so that I can port codebases over to C easily.

----------------------------

What's also nice is the fact that it provides the tools necessary to generate
slices of the input such that error reporting can be visual and descriptive.


### run tests ###

```php
$> TEST=1 php ParserStream.php
```


### example ###

```php

$stream = new ParserStream(stdin);

if ($stream->eat("quit")) {
    echo "quitting\n";
} else {
    $range = $stream->slice($this->ftell, $this->ftell + 5);
    echo $this->err("expected 'quit' but found '$range'");
}

$stream->rewind();
while ($stream->active) {
    $stream->next();
    echo $stream->curr;
}

```
