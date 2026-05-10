<?php

namespace Tests\Unit;

use App\Orm\QueryBuilder;
use App\Orm\Database;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Mini ORM Unit Testleri
 *
 * Gerçek DB bağlantısı gerekmeden QueryBuilder'ın SQL üretimini ve
 * parametrik bağlamayı test eder. PDO mock'u kullanılır.
 */
class OrmTest extends TestCase
{
    // -------------------------------------------------------------------------
    // QueryBuilder — SQL üretim testleri
    // -------------------------------------------------------------------------

    public function test_simple_select_sql(): void
    {
        $qb  = QueryBuilder::table('users');
        $sql = $qb->buildSelectSql();

        $this->assertSame('SELECT * FROM `users`', $sql);
    }

    public function test_select_with_columns(): void
    {
        $qb  = QueryBuilder::table('users')->select(['id', 'name', 'email']);
        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('`id`', $sql);
        $this->assertStringContainsString('`name`', $sql);
        $this->assertStringContainsString('`email`', $sql);
    }

    public function test_where_appends_condition(): void
    {
        $qb  = QueryBuilder::table('users')->where('status', 'active');
        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
    }

    public function test_where_with_explicit_operator(): void
    {
        $qb  = QueryBuilder::table('users')->where('age', '>', 18);
        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('WHERE `age` > ?', $sql);
    }

    public function test_multiple_wheres_use_and(): void
    {
        $qb  = QueryBuilder::table('users')
            ->where('status', 'active')
            ->where('age', '>', 18);
        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('AND `age` > ?', $sql);
    }

    public function test_or_where(): void
    {
        $qb  = QueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhere('role', 'admin');
        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('OR `role` = ?', $sql);
    }

    public function test_order_by(): void
    {
        $qb  = QueryBuilder::table('users')->orderBy('created_at', 'desc');
        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('ORDER BY `created_at` DESC', $sql);
    }

    public function test_limit_and_offset(): void
    {
        $qb  = QueryBuilder::table('users')->limit(10)->offset(20);
        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function test_bindings_are_collected(): void
    {
        $qb = QueryBuilder::table('users')
            ->where('status', 'active')
            ->where('age', '>', 18);

        $this->assertSame(['active', 18], $qb->getBindings());
    }

    // -------------------------------------------------------------------------
    // SQL Injection güvenliği
    // -------------------------------------------------------------------------

    public function test_where_value_is_not_interpolated_in_sql(): void
    {
        $malicious = "1; DROP TABLE users; --";
        $qb        = QueryBuilder::table('users')->where('email', $malicious);
        $sql       = $qb->buildSelectSql();

        // SQL içinde kötü amaçlı değer string olarak OLMAMALI
        $this->assertStringNotContainsString('DROP TABLE', $sql);

        // Değer binding listesinde olmalı
        $this->assertContains($malicious, $qb->getBindings());
    }

    public function test_invalid_operator_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);

        QueryBuilder::table('users')->where('id', 'INVALID_OP', 1);
    }

    // -------------------------------------------------------------------------
    // Fluent zincir testi
    // -------------------------------------------------------------------------

    public function test_full_fluent_chain_produces_valid_sql(): void
    {
        $qb = QueryBuilder::table('users')
            ->select(['id', 'name'])
            ->where('status', 'active')
            ->where('age', '>', 18)
            ->orWhere('role', 'admin')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->offset(5);

        $sql = $qb->buildSelectSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM `users`', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 5', $sql);
        $this->assertCount(3, $qb->getBindings());
    }

    // -------------------------------------------------------------------------
    // Model — attribute & toArray / toJson
    // -------------------------------------------------------------------------

    public function test_model_fill_and_to_array(): void
    {
        $user = new \App\Models\User();
        $user->fill(['id' => 1, 'name' => 'Ali', 'email' => 'ali@test.com']);

        $arr = $user->toArray();

        $this->assertSame(1, $arr['id']);
        $this->assertSame('Ali', $arr['name']);
    }

    public function test_model_to_json_is_valid_json(): void
    {
        $user = new \App\Models\User();
        $user->fill(['id' => 1, 'name' => 'Test']);

        $json = $user->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Test', $decoded['name']);
    }

    public function test_model_get_attribute(): void
    {
        $user = new \App\Models\User();
        $user->fill(['id' => 42, 'name' => 'Veli']);

        $this->assertSame(42, $user->getAttribute('id'));
        $this->assertSame('Veli', $user->getAttribute('name'));
        $this->assertNull($user->getAttribute('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // İlişki tanımı testleri (DB bağlantısı olmadan sadece nesne tipi kontrolü)
    // -------------------------------------------------------------------------

    public function test_belongs_to_returns_correct_relation_instance(): void
    {
        $post = new \App\Models\Post();
        $post->fill(['id' => 1, 'user_id' => 5, 'title' => 'Test']);

        $relation = $post->user();

        $this->assertInstanceOf(\App\Orm\Relations\BelongsTo::class, $relation);
    }

    public function test_has_many_returns_correct_relation_instance(): void
    {
        $user = new \App\Models\User();
        $user->fill(['id' => 1, 'name' => 'Ali']);

        $relation = $user->posts();

        $this->assertInstanceOf(\App\Orm\Relations\HasMany::class, $relation);
    }

    public function test_belongs_to_many_returns_correct_relation_instance(): void
    {
        $post = new \App\Models\Post();
        $post->fill(['id' => 1, 'title' => 'Test']);

        $relation = $post->tags();

        $this->assertInstanceOf(\App\Orm\Relations\BelongsToMany::class, $relation);
    }

    // -------------------------------------------------------------------------
    // Model — relation set/get
    // -------------------------------------------------------------------------

    public function test_set_and_get_relation(): void
    {
        $user = new \App\Models\User();
        $user->fill(['id' => 1, 'name' => 'Ali']);

        $post = new \App\Models\Post();
        $post->fill(['id' => 10, 'title' => 'Hello']);

        $user->setRelation('posts', [$post]);

        $loaded = $user->getRelation('posts');
        $this->assertCount(1, $loaded);
        $this->assertSame('Hello', $loaded[0]->getAttribute('title'));
    }

    public function test_to_array_includes_loaded_relations(): void
    {
        $user = new \App\Models\User();
        $user->fill(['id' => 1, 'name' => 'Ali']);

        $post = new \App\Models\Post();
        $post->fill(['id' => 10, 'title' => 'Hello World']);
        $user->setRelation('posts', [$post]);

        $arr = $user->toArray();

        $this->assertArrayHasKey('posts', $arr);
        $this->assertSame('Hello World', $arr['posts'][0]['title']);
    }
}
