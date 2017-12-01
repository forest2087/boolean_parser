<?php
namespace forest\boolean_parser\Parser;

use forest\boolean_parser\AST\ExpressionInterface;
use forest\boolean_parser\Parser\Exception\ParseException;

interface ParserInterface
{
    /**
     * Parse an expression.
     *
     * @param string $expression The expression to parse.
     *
     * @return ExpressionInterface The parsed expression.
     * @throws ParseException      if the expression is invalid.
     */
    public function parse($expression);
}
