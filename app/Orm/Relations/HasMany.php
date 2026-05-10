<?php

namespace App\Orm\Relations;

class HasMany extends Relation
{
    public function get(): array
    {
        $localValue = $this->parentModel->getAttribute($this->localKey);

        return $this->newRelatedQuery()
            ->where($this->foreignKey, $localValue)
            ->get();
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
        $related      = new $this->relatedClass();
        $relatedTable = $related->getTable();

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT * FROM `{$relatedTable}` WHERE `{$this->foreignKey}` IN ({$placeholders})";

        $pdo  = \App\Orm\Database::getInstance()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($keys));
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $fk = $row[$this->foreignKey];
            if (!isset($map[$fk])) {
                $map[$fk] = [];
            }
            $rel = new $this->relatedClass();
            $rel->fill($row);
            $map[$fk][] = $rel;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, $map[$key] ?? []);
        }
    }
}
