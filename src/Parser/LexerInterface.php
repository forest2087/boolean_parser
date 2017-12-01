<?php
namespace forest\boolean_parser\Parser;

use forest\boolean_parser\Parser\Exception\ParseException;

interface LexerInterface
{
    /**
     * Tokenize an expression.
     *
     * @param string $expression The expression to parse.
     *
     * @return array<Token>   The tokens of the expression.
     * @throws ParseException if the expression is invalid.
     */
    public function lex($expression);
}
