<?php
namespace forest\boolean_parser\Parser;

use forest\boolean_parser\AST\EmptyExpression;
use forest\boolean_parser\AST\ExpressionInterface;
use forest\boolean_parser\AST\LogicalAnd;
use forest\boolean_parser\AST\LogicalNot;
use forest\boolean_parser\AST\LogicalOr;
use forest\boolean_parser\AST\Pattern;
use forest\boolean_parser\AST\PatternLiteral;
use forest\boolean_parser\AST\PatternWildcard;
use forest\boolean_parser\AST\Tag;
use forest\boolean_parser\Parser\Exception\ParseException;

class ExpressionParser implements ParserInterface
{
    /**
     * @param string              $wildcardString     The string to use as a wildcard placeholder.
     * @param boolean             $logicalOrByDefault True if the default operator should be OR, rather than AND.
     * @param LexerInterface|null $lexer
     */
    public function __construct(
        $wildcardString = null,
        $logicalOrByDefault = false,
        LexerInterface $lexer = null
    ) {
        if (null === $wildcardString) {
            $wildcardString = Token::WILDCARD_CHARACTER;
        }

        if (null === $lexer) {
            $lexer = new Lexer;
        }

        $this->wildcardString = $wildcardString;
        $this->logicalOrByDefault = $logicalOrByDefault;
        $this->lexer = $lexer;
    }

    /**
     * Parse an expression.
     *
     * @param string $expression The expression to parse.
     *
     * @return ExpressionInterface The parsed expression.
     * @throws ParseException      if the expression is invalid.
     */
    public function parse($expression)
    {
        $tokens = $this->lexer->lex($expression);
//print_r($tokens);
        if (!$tokens) {
            return new EmptyExpression;
        }

        $expression = $this->parseExpression($tokens);

        if (null !== key($tokens)) {
            throw new ParseException(
                'Unexpected ' . Token::typeDescription(current($tokens)->type) . ', expected end of input.'
            );
        }

        return $expression;
    }

    private function parseExpression(array &$tokens)
    {
        $expression = $this->parseUnaryExpression($tokens);
//        print_r(70 . '<br/>');
//        print_r($expression);
        $expression = $this->parseCompoundExpression($tokens, $expression);
        return $expression;
    }

    private function parseUnaryExpression(array &$tokens)
    {
        $token = $this->expect(
            $tokens,
            Token::STRING,
            Token::LOGICAL_NOT,
            Token::OPEN_BRACKET
        );
//print_r(84 . PHP_EOL);
//print_r($token);
        if (Token::LOGICAL_NOT === $token->type) {
//            print_r(1 . PHP_EOL);
            return $this->parseLogicalNot($tokens);
        } elseif (Token::OPEN_BRACKET === $token->type) {
//            print_r(2 . PHP_EOL);
            return $this->parseNestedExpression($tokens);
        } else {
//            print_r(3 . PHP_EOL);
            return $this->parseTag($tokens);
        }
    }

    private function parseTag(array &$tokens)
    {
        $token = current($tokens);

        next($tokens);

        return new Tag($token->value);
    }

    private function parsePattern(array &$tokens)
    {
        $token = current($tokens);

        next($tokens);

        $parts = preg_split(
            '/(' . preg_quote($this->wildcardString, '/') . ')/',
            $token->value,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $expression = new Pattern;

        foreach ($parts as $value) {
            if ($this->wildcardString === $value) {
                $expression->add(new PatternWildcard);
            } else {
                $expression->add(new PatternLiteral($value));
            }
        }

        return $expression;
    }

    private function parseNestedExpression(array &$tokens)
    {
        next($tokens);

        $expression = $this->parseExpression($tokens);

        $this->expect(
            $tokens,
            Token::CLOSE_BRACKET
        );

        next($tokens);

        return $expression;
    }

    private function parseLogicalNot(array &$tokens)
    {
        next($tokens);

        return new LogicalNot(
            $this->parseUnaryExpression($tokens)
        );
    }

    private function parseCompoundExpression(array &$tokens, ExpressionInterface $leftExpression, $minimumPrecedence = 0)
    {
        $allowCollapse = false;

        while (true) {

            // Parse the operator and determine whether or not it's explicit ...
            list($operator, $isExplicit) = $this->parseOperator($tokens);

            $precedence = self::$operatorPrecedence[$operator];

            // Abort if the operator's precedence is less than what we're looking for ...
            if ($precedence < $minimumPrecedence) {
                break;
            }

            // Advance the token pointer if an explicit operator token was found ...
            if ($isExplicit) {
                next($tokens);
            }

            // Parse the expression to the right of the operator ...
            $rightExpression = $this->parseUnaryExpression($tokens);

            // Only parse additional compound expressions if their precedence is greater than the
            // expression already being parsed ...
            list($nextOperator) = $this->parseOperator($tokens);

            if ($precedence < self::$operatorPrecedence[$nextOperator]) {
                $rightExpression = $this->parseCompoundExpression(
                    $tokens,
                    $rightExpression,
                    $precedence + 1
                );
            }

            // Combine the parsed expression with the existing expression ...
            $operatorClass = self::$operatorClasses[$operator];

            // Collapse the expression into an existing expression of the same type ...
            if ($allowCollapse && $leftExpression instanceof $operatorClass) {
                $leftExpression->add($rightExpression);
            } else {
                $leftExpression = new $operatorClass(
                    $leftExpression,
                    $rightExpression
                );
                $allowCollapse = true;
            }
        }

        return $leftExpression;
    }

    private function parseOperator(array &$tokens)
    {
        $token = current($tokens);

        // End of input ...
        if (false === $token) {
            return array(null, false);

        // Closing bracket ...
        } elseif (Token::CLOSE_BRACKET === $token->type) {
            return array(null, false);

        // Explicit logical OR ...
        } elseif (Token::LOGICAL_OR === $token->type) {
            return array(Token::LOGICAL_OR, true);

        // Explicit logical AND ...
        } elseif (Token::LOGICAL_AND === $token->type) {
            return array(Token::LOGICAL_AND, true);

        // Implicit logical OR ...
        } elseif ($this->logicalOrByDefault) {
            return array(Token::LOGICAL_OR, false);

        // Implicit logical AND ...
        } else {
            return array(Token::LOGICAL_AND, false);
        }

        return array(null, false);
    }

    private function expect(array &$tokens)
    {
//        print_r($tokens);
//        print_r(func_get_args());
        $types = array_slice(func_get_args(), 1);
        $token = current($tokens);
//        print_r($token);

        if (!$token) {
            throw new ParseException(
                'Unexpected end of input, expected ' . $this->formatExpectedTokenNames($types) . '.'
            );
        } elseif (!in_array($token->type, $types)) {
            throw new ParseException(
                'Unexpected ' . Token::typeDescription($token->type) . ', expected ' . $this->formatExpectedTokenNames($types) . '.'
            );
        }

        return $token;
    }

    private function formatExpectedTokenNames(array $types)
    {
        $types = array_map(
            'forest\boolean_parser\Parser\Token::typeDescription',
            $types
        );

        if (count($types) === 1) {
            return $types[0];
        }

        $lastType = array_pop($types);

        return implode(', ', $types) . ' or ' . $lastType;
    }

    private static $operatorClasses = array(
        Token::LOGICAL_AND => 'forest\boolean_parser\AST\LogicalAnd',
        Token::LOGICAL_OR  => 'forest\boolean_parser\AST\LogicalOr',
    );

    private static $operatorPrecedence = array(
        Token::LOGICAL_AND => 1,
        Token::LOGICAL_OR  => 0,
        null               => -1,
    );

    private $wildcardString;
    private $lexer;
    private $logicalOrByDefault;
}
