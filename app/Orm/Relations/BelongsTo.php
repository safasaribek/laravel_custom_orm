<?php

namespace App\Orm\Relations;


class BelongsTo extends Relation
{
    public function get(): mixed
    {
        $foreignValue = $this->parentModel->getAttribute($this->foreignKey);

        if ($foreignValue === null) {
            return null;
        }

        return $this->newRelatedQuery()
            ->where($this->localKey, $foreignValue)
            ->first();
    }

    public function eagerLoadFor(array $models, string $relationName): void
    {
        $keys = array_map(
            fn($m) => $m->getAttribute($this->foreignKey),
            $models
        );
        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            return;
        }

        /** @var \App\Orm\Model $related */
        $related      = new $this->relatedClass();
        $relatedTable = $related->getTable();

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT * FROM `{$relatedTable}` WHERE `{$this->localKey}` IN ({$placeholders})";

        $pdo  = \App\Orm\Database::getInstance()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($keys));
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $rel = new $this->relatedClass();
            $rel->fill($row);
            $map[$row[$this->localKey]] = $rel;
        }

        foreach ($models as $model) {
            $fk  = $model->getAttribute($this->foreignKey);
            $model->setRelation($relationName, $map[$fk] ?? null);
        }
    }
}
