<?php

namespace Eoko\ODM\Driver\DynamoDB;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;

class QueryBuilder extends ExpressionVisitor {
    /**
     * Converts a comparison expression into the target query language output.
     *
     * @param Comparison $comparison
     *
     * @return mixed
     */
    public function walkComparison(Comparison $comparison)
    {
        $tokenName = uniqid(':');
        $token = [$tokenName => ['name' => $tokenName, 'field' => $comparison->getField(), 'value' => $comparison->getValue()->getValue()]];
        return ['expression' => $comparison->getField() . ' ' . $comparison->getOperator() . ' ' . $tokenName . '', 'tokens' => $token];
    }

    /**
     * Converts a value expression into the target query language part.
     *
     * @param Value $value
     *
     * @return mixed
     */
    public function walkValue(Value $value)
    {
        die('_');
        // TODO: Implement walkValue() method.
    }

    /**
     * Converts a composite expression into the target query language output.
     *
     * @param CompositeExpression $expr
     *
     * @return mixed
     */
    public function walkCompositeExpression(CompositeExpression $expr)
    {
        $result = [];
        $tokens = [];
        foreach($expr->getExpressionList() as $exp) {
            if($exp instanceof CompositeExpression) {
                $w = $this->walkCompositeExpression($exp);
            } else {
                $w = $this->walkComparison($exp);
            }
            $result[] = $w['expression'];
            $tokens = array_merge($tokens, $w['tokens']);
        };
        return ['expression' => '( ' . implode(' ' . $expr->getType() . ' ', $result) .' )', 'tokens' => $tokens];
    }


}