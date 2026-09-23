<?php

namespace Cesurapp\ApiBundle\Doctrine;

use Doctrine\ORM\QueryBuilder;

class DoctrineHelper
{
    /**
     * Clear Same Alias Join: when several filters join the same alias, keep the first join.
     * Joins of every root alias are kept (a multi-root query does not lose the others').
     */
    public static function setUniqueJoin(QueryBuilder $builder): void
    {
        $allJoins = $builder->getDQLPart('join');
        if (!$allJoins) {
            return;
        }

        $aliases = [];
        foreach ($allJoins as $rootAlias => $joins) {
            foreach ($joins as $key => $join) {
                if (in_array($join->getAlias(), $aliases, true)) {
                    unset($allJoins[$rootAlias][$key]);
                    continue;
                }

                $aliases[] = $join->getAlias();
            }

            $allJoins[$rootAlias] = array_values($allJoins[$rootAlias]);
        }

        // Without $append, a join part replaces all joins, keyed by root alias.
        $builder->add('join', $allJoins);
    }
}
