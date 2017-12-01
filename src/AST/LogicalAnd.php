<?php
namespace forest\boolean_parser\AST;

/**
 * An AST node that represents the logical AND operator.
 */
class LogicalAnd extends AbstractPolyadicOperator
{
    /**
     * Pass this node to the appropriate method on the given visitor.
     *
     * @param VisitorInterface $visitor The visitor to dispatch to.
     *
     * @return mixed The visitation result.
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitLogicalAnd($this);
    }
}
