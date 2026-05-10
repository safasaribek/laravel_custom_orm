<?php

namespace App\Orm\Relations;

use App\Orm\Model;
use App\Orm\QueryBuilder;

abstract class Relation
{
    protected Model  $parentModel;
    protected string $relatedClass;
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(
        Model  $parentModel,
        string $relatedClass,
        string $foreignKey,
        string $localKey
    ) {
        $this->parentModel  = $parentModel;
        $this->relatedClass = $relatedClass;
        $this->foreignKey   = $foreignKey;
        $this->localKey     = $localKey;
    }

    abstract public function get(): mixed;

    abstract public function eagerLoadFor(array $models, string $relationName): void;

    protected function newRelatedQuery(): QueryBuilder
    {
        /** @var Model $related */
        $related = new $this->relatedClass();
        return QueryBuilder::table($related->getTable())->setModel($this->relatedClass);
    }
}
