<?php

namespace App\Orm;

use PDO;
use PDOStatement;
use RuntimeException;

class QueryBuilder
{
    private string $table        = '';
    private array  $selects      = ['*'];
    private array  $wheres       = [];
    private array  $bindings     = [];
    private array  $orderBys     = [];
    private ?int   $limitValue   = null;
    private ?int   $offsetValue  = null;
    private array  $joins        = [];
    private array $eagerLoads = [];
    private ?string $modelClass = null;

    public static function table(string $table): self
    {
        $qb        = new self();
        $qb->table = $table;
        return $qb;
    }

    public function setModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    public function setEagerLoads(array $relations): self
    {
        $this->eagerLoads = $relations;
        return $this;
    }

    public function select(array $columns): self
    {
        $this->selects = $columns;
        return $this;
    }
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        [$operator, $val] = $this->parseOperatorAndValue($operatorOrValue, $value);
        $this->wheres[]   = ['type' => 'AND', 'column' => $column, 'operator' => $operator];
        $this->bindings[] = $val;
        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        [$operator, $val] = $this->parseOperatorAndValue($operatorOrValue, $value);
        $this->wheres[]   = ['type' => 'OR', 'column' => $column, 'operator' => $operator];
        $this->bindings[] = $val;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction        = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBys[] = "`{$column}` {$direction}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = "JOIN `{$table}` ON `{$first}` {$operator} `{$second}`";
        return $this;
    }

    public function get(): array
    {
        $stmt = $this->runSelect($this->buildSelectSql());
        $rows = $stmt->fetchAll();

        if ($this->modelClass !== null) {
            $models = array_map(fn($row) => $this->hydrateModel($row), $rows);
            return $this->loadEagerRelations($models);
        }

        return $rows;
    }

    public function first(): mixed
    {
        $this->limitValue = 1;
        $stmt = $this->runSelect($this->buildSelectSql());
        $row  = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->modelClass ? $this->hydrateModel($row) : $row;
    }

    public function count(): int
    {
        $sql  = "SELECT COUNT(*) as aggregate FROM `{$this->table}`" . $this->buildWhereSql();
        $stmt = $this->runSelect($sql);
        return (int) $stmt->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function buildSelectSql(): string
    {
        $columns = implode(', ', array_map(
            fn($col) => $col === '*' ? '*' : "`{$col}`",
            $this->selects
        ));

        $sql = "SELECT {$columns} FROM `{$this->table}`";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhereSql();

        if (!empty($this->orderBys)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    public function buildWhereSql(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $i => $where) {
            $prefix = ($i === 0) ? 'WHERE' : $where['type'];
            $parts[] = "{$prefix} `{$where['column']}` {$where['operator']} ?";
        }

        return ' ' . implode(' ', $parts);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function insertGetId(array $data): int|string
    {
        $this->assertFillable($data);
        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql  = "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})";
        $stmt = $this->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->pdo()->lastInsertId();
    }

    public function updateWhere(array $data, string $primaryKey, mixed $id): bool
    {
        $this->assertFillable($data);
        $sets = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $sql  = "UPDATE `{$this->table}` SET {$sets} WHERE `{$primaryKey}` = ?";
        $stmt = $this->prepare($sql);
        $stmt->execute([...array_values($data), $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteWhere(string $primaryKey, mixed $id): bool
    {
        $sql  = "DELETE FROM `{$this->table}` WHERE `{$primaryKey}` = ?";
        $stmt = $this->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    private function runSelect(string $sql): PDOStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt;
    }

    private function prepare(string $sql): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException("SQL hazırlanamadı: {$sql}");
        }
        return $stmt;
    }

    private function pdo(): PDO
    {
        return Database::getInstance()->getPdo();
    }

    private function parseOperatorAndValue(mixed $operatorOrValue, mixed $value): array
    {
        $allowedOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

        if ($value === null) {
            return ['=', $operatorOrValue];
        }

        $op = strtoupper((string) $operatorOrValue);
        if (!in_array($op, $allowedOperators, true)) {
            throw new RuntimeException("Geçersiz SQL operatörü: {$operatorOrValue}");
        }

        return [$op, $value];
    }

    private function assertFillable(array $data): void
    {
        if (empty($data)) {
            throw new RuntimeException('Veri dizisi boş olamaz.');
        }
    }

    private function hydrateModel(array $row): object
    {
        /** @var \App\Orm\Model $model */
        $model = new $this->modelClass();
        $model->fill($row);
        return $model;
    }

    private function loadEagerRelations(array $models): array
    {
        if (empty($models) || empty($this->eagerLoads)) {
            return $models;
        }

        foreach ($this->eagerLoads as $relationName) {
            $firstModel = $models[0];

            if (!method_exists($firstModel, $relationName)) {
                throw new RuntimeException(
                    get_class($firstModel) . " sınıfında '{$relationName}' ilişkisi tanımlı değil."
                );
            }

            /** @var \App\Orm\Relations\Relation $relation */
            $relation = $firstModel->$relationName();
            $relation->eagerLoadFor($models, $relationName);
        }

        return $models;
    }
}
