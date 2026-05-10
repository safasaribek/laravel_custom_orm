<?php

namespace App\Orm\Relations;

use App\Orm\Database;

class BelongsToMany extends Relation
{
    private string $pivotTable;
    private string $relatedKey;

    public function __construct(
        \App\Orm\Model $parentModel,
        string $relatedClass,
        string $pivotTable,
        string $foreignKey,
        string $relatedKey
    ) {
        /** @var \App\Orm\Model $related */
        $related = new $relatedClass();

        parent::__construct(
            $parentModel,
            $relatedClass,
            $foreignKey,
            $related->getPrimaryKey()
        );

        $this->pivotTable = $pivotTable;
        $this->relatedKey = $relatedKey;
    }

    public function get(): array
    {
        $parentId     = $this->parentModel->getAttribute($this->parentModel->getPrimaryKey());
        $relatedTable = (new $this->relatedClass())->getTable();

        $sql = "
            SELECT r.*
            FROM `{$relatedTable}` r
            INNER JOIN `{$this->pivotTable}` p ON p.`{$this->relatedKey}` = r.`{$this->localKey}`
            WHERE p.`{$this->foreignKey}` = ?
        ";

        $pdo  = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$parentId]);
        $rows = $stmt->fetchAll();

        return array_map(function ($row) {
            $model = new $this->relatedClass();
            $model->fill($row);
            return $model;
        }, $rows);
    }

    public function eagerLoadFor(array $models, string $relationName): void
    {
        $parentPk     = $this->parentModel->getPrimaryKey();
        $relatedTable = (new $this->relatedClass())->getTable();

        $parentIds = array_unique(array_filter(
            array_map(fn($m) => $m->getAttribute($parentPk), $models)
        ));

        if (empty($parentIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));

        $sql = "
            SELECT r.*, p.`{$this->foreignKey}` as __pivot_fk
            FROM `{$relatedTable}` r
            INNER JOIN `{$this->pivotTable}` p ON p.`{$this->relatedKey}` = r.`{$this->localKey}`
            WHERE p.`{$this->foreignKey}` IN ({$placeholders})
        ";

        $pdo  = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($parentIds));
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $fk = $row['__pivot_fk'];
            unset($row['__pivot_fk']);
            $rel = new $this->relatedClass();
            $rel->fill($row);
            $map[$fk][] = $rel;
        }

        foreach ($models as $model) {
            $pk = $model->getAttribute($parentPk);
            $model->setRelation($relationName, $map[$pk] ?? []);
        }
    }
}
