<?php

namespace App\Orm;

use App\Orm\Relations\BelongsTo;
use App\Orm\Relations\BelongsToMany;
use App\Orm\Relations\HasMany;
use App\Orm\Relations\HasOne;
use RuntimeException;

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    private array $attributes = [];
    private array $relations = [];

    public static function create(array $data): static
    {
        $instance = new static();
        $filtered = $instance->filterFillable($data);

        $qb = QueryBuilder::table($instance->table);
        $id = $qb->insertGetId($filtered);

        return static::find($id) ?? throw new RuntimeException('Kayıt oluşturulduktan sonra bulunamadı.');
    }

    public static function find(int|string $id): ?static
    {
        $instance = new static();
        return QueryBuilder::table($instance->table)
            ->setModel(static::class)
            ->where($instance->primaryKey, $id)
            ->first();
    }

    public static function all(): array
    {
        $instance = new static();
        return QueryBuilder::table($instance->table)
            ->setModel(static::class)
            ->get();
    }

    public static function update(int|string $id, array $data): bool
    {
        $instance = new static();
        $filtered = $instance->filterFillable($data);

        return QueryBuilder::table($instance->table)
            ->updateWhere($filtered, $instance->primaryKey, $id);
    }

    public static function delete(int|string $id): bool
    {
        $instance = new static();
        return QueryBuilder::table($instance->table)
            ->deleteWhere($instance->primaryKey, $id);
    }

    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): QueryBuilder
    {
        $instance = new static();
        return QueryBuilder::table($instance->table)
            ->setModel(static::class)
            ->where($column, $operatorOrValue, $value);
    }

    public static function with(string ...$relations): QueryBuilder
    {
        $instance = new static();
        return QueryBuilder::table($instance->table)
            ->setModel(static::class)
            ->setEagerLoads($relations);
    }

    protected function hasOne(string $relatedClass, string $foreignKey, ?string $localKey = null): HasOne
    {
        $localKey ??= $this->primaryKey;
        return new HasOne($this, $relatedClass, $foreignKey, $localKey);
    }

    protected function hasMany(string $relatedClass, string $foreignKey, ?string $localKey = null): HasMany
    {
        $localKey ??= $this->primaryKey;
        return new HasMany($this, $relatedClass, $foreignKey, $localKey);
    }

    protected function belongsTo(string $relatedClass, string $foreignKey, ?string $ownerKey = null): BelongsTo
    {
        /** @var Model $related */
        $related  = new $relatedClass();
        $ownerKey ??= $related->primaryKey;
        return new BelongsTo($this, $relatedClass, $foreignKey, $ownerKey);
    }

    protected function belongsToMany(
        string $relatedClass,
        string $pivotTable,
        string $foreignKey,
        string $relatedKey
    ): BelongsToMany {
        return new BelongsToMany($this, $relatedClass, $pivotTable, $foreignKey, $relatedKey);
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        if (method_exists($this, $name)) {
            $relation = $this->$name();
            if ($relation instanceof \App\Orm\Relations\Relation) {
                $result = $relation->get();
                $this->relations[$name] = $result;
                return $result;
            }
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]) || isset($this->relations[$name]);
    }

    public function fill(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function toArray(): array
    {
        $data = $this->attributes;

        foreach ($this->relations as $name => $value) {
            if (is_array($value)) {
                $data[$name] = array_map(
                    fn($item) => $item instanceof self ? $item->toArray() : $item,
                    $value
                );
            } else {
                $data[$name] = $value instanceof self ? $value->toArray() : $value;
            }
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        $filtered = array_intersect_key($data, array_flip($this->fillable));

        if (empty($filtered)) {
            throw new RuntimeException(
                static::class . ': Gönderilen veriler fillable listesiyle eşleşmiyor. ' .
                'fillable: [' . implode(', ', $this->fillable) . ']'
            );
        }

        return $filtered;
    }
}
