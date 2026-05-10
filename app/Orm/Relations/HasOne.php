<?php

namespace App\Orm\Relations;

class HasOne extends Relation
{
    public function get(): mixed
    {
        $localValue = $this->parentModel->getAttribute($this->localKey);

        return $this->newRelatedQuery()
            ->where($this->foreignKey, $localValue)
            ->first();
    }

    public function eagerLoadFor(array $models, string $relationName): void
    {
        $keys = array_map(
            fn($m) => $m->getAttribute($this->localKey),
            $models
        );
        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            return;
        }

        /** @var \App\Orm\Model $related */
        $related     = new $this->relatedClass();
        $relatedTable = $related->getTable();

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT * FROM `{$relatedTable}` WHERE `{$this->foreignKey}` IN ({$placeholders})";

        $pdo  = \App\Orm\Database::getInstance()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($keys));
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[$row[$this->foreignKey]] = $row;
        }
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $rel = null;
            if (isset($map[$key])) {
                $rel = new $this->relatedClass();
                $rel->fill($map[$key]);
            }
            $model->setRelation($relationName, $rel);
        }
    }
}
