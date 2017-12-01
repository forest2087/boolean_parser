<?php
namespace forest\boolean_parser\Renderer;

use forest\boolean_parser\AST\ExpressionInterface;

interface RendererInterface
{
    /**
     * Render an expression to a string.
     *
     * @param ExpressionInterface $expression The expression to render.
     *
     * @return string The rendered expression.
     */
    public function render(ExpressionInterface $expression);
}
