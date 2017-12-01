<?php
namespace forest\boolean_parser\Renderer;

use forest\boolean_parser\AST\EmptyExpression;
use forest\boolean_parser\AST\ExpressionInterface;
use forest\boolean_parser\AST\LogicalAnd;
use forest\boolean_parser\AST\LogicalNot;
use forest\boolean_parser\AST\LogicalOr;
use forest\boolean_parser\AST\NodeInterface;
use forest\boolean_parser\AST\Pattern;
use forest\boolean_parser\AST\PatternLiteral;
use forest\boolean_parser\AST\PatternWildcard;
use forest\boolean_parser\AST\Tag;
use forest\boolean_parser\AST\VisitorInterface;

/**
 * Render an AST expression to a string representing the tree structure.
 */
class TreeRenderer implements RendererInterface, VisitorInterface
{
    /**
     * Render an expression to a string.
     *
     * @param ExpressionInterface $expression The expression to render.
     *
     * @return string The rendered expression.
     */
    public function render(ExpressionInterface $expression)
    {
        return $expression->accept($this);
    }

    /**
     * Visit a LogicalAnd node.
     *
     * @internal
     *
     * @param LogicalAnd $node The node to visit.
     *
     * @return mixed
     */
    public function visitLogicalAnd(LogicalAnd $node)
    {
        return 'AND' . PHP_EOL . $this->renderChildren($node);
    }

    /**
     * Visit a LogicalOr node.
     *
     * @internal
     *
     * @param LogicalOr $node The node to visit.
     *
     * @return mixed
     */
    public function visitLogicalOr(LogicalOr $node)
    {
        return 'OR' . PHP_EOL . $this->renderChildren($node);
    }

    /**
     * Visit a LogicalNot node.
     *
     * @internal
     *
     * @param LogicalNot $node The node to visit.
     *
     * @return mixed
     */
    public function visitLogicalNot(LogicalNot $node)
    {
        $child = $node->child()->accept($this);

        return 'NOT' . PHP_EOL . $this->indent('- ' . $child);
    }

    /**
     * Visit a Tag node.
     *
     * @internal
     *
     * @param Tag $node The node to visit.
     *
     * @return mixed
     */
    public function visitTag(Tag $node)
    {
        return 'TAG ' . json_encode($node->name());
    }

    /**
     * Visit a Pattern node.
     *
     * @internal
     *
     * @param Pattern $node The node to visit.
     *
     * @return mixed
     */
    public function visitPattern(Pattern $node)
    {
        return 'PATTERN' . PHP_EOL . $this->renderChildren($node);
    }

    /**
     * Visit a PatternLiteral node.
     *
     * @internal
     *
     * @param PatternLiteral $node The node to visit.
     *
     * @return mixed
     */
    public function visitPatternLiteral(PatternLiteral $node)
    {
        return 'LITERAL ' . json_encode($node->string());
    }

    /**
     * Visit a PatternWildcard node.
     *
     * @internal
     *
     * @param PatternWildcard $node The node to visit.
     *
     * @return mixed
     */
    public function visitPatternWildcard(PatternWildcard $node)
    {
        return 'WILDCARD';
    }

    /**
     * Visit a EmptyExpression node.
     *
     * @internal
     *
     * @param EmptyExpression $node The node to visit.
     *
     * @return mixed
     */
    public function visitEmptyExpression(EmptyExpression $node)
    {
        return 'EMPTY';
    }

    private function renderChildren(NodeInterface $node)
    {
        $output = '';

        foreach ($node->children() as $n) {
            $output .= $this->indent(
                '- ' . $n->accept($this)
            ) . PHP_EOL;
        }

        return rtrim($output);
    }

    private function indent($string)
    {
        return '  ' . str_replace(PHP_EOL, PHP_EOL . '  ', $string);
    }

    private $indentLevel = 0;
}
