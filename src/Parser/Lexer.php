<?php

namespace forest\boolean_parser\Parser;

use forest\boolean_parser\Parser\Exception\ParseException;

class Lexer implements LexerInterface
{
    const STATE_BEGIN = 1;
    const STATE_SIMPLE_STRING = 2;
    const STATE_QUOTED_STRING = 3;
    const STATE_QUOTED_STRING_ESCAPE = 4;
    private $state;
    private $tokens;
    private $buffer;
    private $nextChar;

    /**
     * Tokenize an expression.
     *
     * @param string $expression The expression to parse.
     *
     * @return array<Token>   The tokens of the expression.
     * @throws ParseException if the expression is invalid.
     */
    public function lex($expression)
    {
        $this->state = self::STATE_BEGIN;
        $this->tokens = [];
        $this->buffer = '';

        $length = strlen($expression);

        for ($index = 0; $index < $length; ++$index) {
            $char = $expression[$index];
            $this->nextChar = '';
            $i = $index;
            do {
                $i++;
                if (isset($expression[$i])) {
                    $this->nextChar = $expression[$i];
                } else {
                    $this->nextChar = false;
                }
            } while ($this->nextChar === '' || $this->nextChar === ' ');
            if (self::STATE_SIMPLE_STRING === $this->state) {
                $this->handleSimpleStringState($char);
            } elseif (self::STATE_QUOTED_STRING === $this->state) {
                $this->handleQuotedStringState($char);
            } elseif (self::STATE_QUOTED_STRING_ESCAPE === $this->state) {
                $this->handleQuotedStringEscapeState($char);
            } else {
                $this->handleBeginState($char);
            }
        }

        if (self::STATE_SIMPLE_STRING === $this->state) {
            $this->finalizeSimpleString();
        } elseif (self::STATE_QUOTED_STRING === $this->state) {
            throw new ParseException('Expected closing quote.');
        } elseif (self::STATE_QUOTED_STRING_ESCAPE === $this->state) {
            throw new ParseException('Expected character after backslash.');
        }

        return $this->tokens;
    }

    private function handleBeginState($char)
    {
        if ($char === '[') {
            throw new ParseException('Expected value before field tag.');
        } elseif (ctype_space($char)) {
            // ignore
        } elseif ($char === '(') {
            $this->tokens[] = new Token(Token::OPEN_BRACKET, $char);
        } elseif ($char === ')') {
            $this->tokens[] = new Token(Token::CLOSE_BRACKET, $char);
        } elseif ($char === '"') {
            $this->state = self::STATE_QUOTED_STRING;
            $this->buffer = $char;
        } else {
            $this->state = self::STATE_SIMPLE_STRING;
            $this->buffer = $char;
        }
    }

    private function handleSimpleStringState($char)
    {
        if ($char === ']') {
            $this->buffer .= $char;
            $this->finalizeSimpleString();
        } elseif (ctype_space($char) && strpos($this->buffer, '[') === false) {
            $this->finalizeSimpleString();
        } elseif ($char === '(') {
            $this->finalizeSimpleString();
            $this->tokens[] = new Token(Token::OPEN_BRACKET, $char);
        } elseif ($char === ')') {
            $this->finalizeSimpleString();
            $this->tokens[] = new Token(Token::CLOSE_BRACKET, $char);
        } else {
            $this->buffer .= $char;
        }
    }

    private function handleQuotedStringState($char)
    {
        if ($char === '\\') {
            $this->state = self::STATE_QUOTED_STRING_ESCAPE;
        } elseif ($char === '"' && $this->nextChar !== '[') {
            $this->tokens[] = new Token(Token::STRING, $this->buffer);
            $this->state = self::STATE_BEGIN;
            $this->buffer = '';
        } elseif ($char === ']' && $this->nextChar!=='"') {
            if (stripos(substr($this->buffer, 1), '"') === false ) {
                throw new ParseException('Expected closing quote.');
            }
            $this->tokens[] = new Token(Token::STRING, $this->buffer . $char);
            $this->state = self::STATE_BEGIN;
            $this->buffer = '';
        } else {
            $this->buffer .= $char;
        }
    }

    private function handleQuotedStringEscapeState($char)
    {
        $this->state = self::STATE_QUOTED_STRING;
        $this->buffer .= $char;
    }

    private function finalizeSimpleString()
    {
        if (strcasecmp('and', $this->buffer) === 0) {
            $tokenType = Token::LOGICAL_AND;
        } elseif (strcasecmp('or', $this->buffer) === 0) {
            $tokenType = Token::LOGICAL_OR;
        } elseif (strcasecmp('not', $this->buffer) === 0) {
            $tokenType = Token::LOGICAL_NOT;
        } else {
            $tokenType = Token::STRING;
        }

        $this->tokens[] = new Token($tokenType, $this->buffer);
        $this->state = self::STATE_BEGIN;
        $this->buffer = '';
    }
}
