<?php

/**
 * Mini ORM — Örnek Kullanım
 *
 * Docker ile çalıştırma:
 *   docker-compose exec app php example.php
 */

require __DIR__ . '/vendor/autoload.php';

// Laravel'in env() helper'ını kullanabilmek için bootstrap
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Orm\QueryBuilder;

echo "========================================\n";
echo " Mini ORM — Örnek Kullanım\n";
echo "========================================\n\n";

// -----------------------------------------------------------------------
// 0. Temizlik (Demo her çalıştığında temiz başlasın)
// -----------------------------------------------------------------------
echo "Veritabanı temizleniyor...\n";
$pdo = \App\Orm\Database::getInstance()->getPdo();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
$pdo->exec("TRUNCATE TABLE post_tag;");
$pdo->exec("TRUNCATE TABLE posts;");
$pdo->exec("TRUNCATE TABLE profiles;");
$pdo->exec("TRUNCATE TABLE products;");
$pdo->exec("TRUNCATE TABLE tags;");
$pdo->exec("TRUNCATE TABLE users;");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
echo "Temizlik tamamlandı.\n\n";

// -----------------------------------------------------------------------
// 1. CRUD
// -----------------------------------------------------------------------
echo "--- 1. CRUD ---\n";

$user = User::create([
    'name'   => 'Ali Veli',
    'email'  => 'ali@example.com',
    'status' => 'active',
    'age'    => 25,
]);
echo "Oluşturuldu → ID: {$user->id}, Ad: {$user->name}\n";

$found = User::find($user->id);
echo "Bulundu → {$found->name} ({$found->email})\n";

User::update($user->id, ['name' => 'Veli Ali']);
$updated = User::find($user->id);
echo "Güncellendi → {$updated->name}\n";

// Silme örneği (örnek akışı bozmamak için geçici kullanıcı)
$temp = User::create(['name' => 'Silinecek', 'email' => 'del@test.com', 'status' => 'inactive', 'age' => 18]);
$deleted = User::delete($temp->id);
echo "Silindi → " . ($deleted ? 'başarılı' : 'başarısız') . "\n\n";

// -----------------------------------------------------------------------
// 2. Fluent Query Builder
// -----------------------------------------------------------------------
echo "--- 2. Fluent Query Builder ---\n";

$users = User::where('status', 'active')
             ->where('age', '>', 18)
             ->orderBy('created_at', 'desc')
             ->limit(10)
             ->get();

echo "Aktif kullanıcı sayısı (filtreli): " . count($users) . "\n";

// -----------------------------------------------------------------------
// 3. Kısa Sorgular
// -----------------------------------------------------------------------
echo "\n--- 3. Kısa Sorgular ---\n";

$count  = User::where('status', 'active')->count();
$first  = User::where('status', 'active')->first();
$exists = User::where('email', 'ali@example.com')->exists();

echo "count()  → {$count}\n";
echo "first()  → " . ($first ? $first->name : 'bulunamadı') . "\n";
echo "exists() → " . ($exists ? 'evet' : 'hayır') . "\n";

// -----------------------------------------------------------------------
// 4. İlişkiler (Relations)
// -----------------------------------------------------------------------
echo "\n--- 4. İlişkiler ---\n";

// Post oluştur
$post = Post::create([
    'title'   => 'İlk Yazım',
    'body'    => 'ORM çalışıyor!',
    'user_id' => $user->id,
    'status'  => 'published',
]);

// Tag oluştur
$tag = Tag::create(['name' => 'php']);
// pivot kayıt (ham PDO ile — çünkü ORM bu tablo için Model yok)
\App\Orm\Database::getInstance()->getPdo()->exec(
    "INSERT INTO post_tag (post_id, tag_id) VALUES ({$post->id}, {$tag->id})"
);

// belongsTo
$postModel = Post::find($post->id);
$author    = $postModel->user()->get();
echo "Post yazarı → " . ($author ? $author->name : 'bulunamadı') . "\n";

// hasMany
$userModel = User::find($user->id);
$posts     = $userModel->posts()->get();
echo "Kullanıcının post sayısı → " . count($posts) . "\n";

// belongsToMany
$postTags = $postModel->tags()->get();
echo "Post etiketleri → " . implode(', ', array_map(fn($t) => $t->name, $postTags)) . "\n";

// -----------------------------------------------------------------------
// 5. Eager Loading (N+1 çözümü)
// -----------------------------------------------------------------------
echo "\n--- 5. Eager Loading ---\n";

$postsWithUser = Post::with('user')->get();
foreach ($postsWithUser as $p) {
    $uName = $p->getRelation('user')?->name ?? 'bilinmiyor';
    echo "  Post: '{$p->title}' → Yazar: {$uName}\n";
}

// -----------------------------------------------------------------------
// 6. toArray() / toJson()
// -----------------------------------------------------------------------
echo "\n--- 6. toArray() / toJson() ---\n";

$u   = User::find($user->id);
$arr = $u->toArray();
echo "toArray() → name: {$arr['name']}, email: {$arr['email']}\n";
echo "toJson()  →\n" . $u->toJson() . "\n";

// -----------------------------------------------------------------------
// 7. Model'den bağımsız QueryBuilder
// -----------------------------------------------------------------------
echo "\n--- 7. Bağımsız QueryBuilder ---\n";

$rows = QueryBuilder::table('users')
    ->select(['id', 'name'])
    ->where('status', 'active')
    ->limit(5)
    ->get();

echo "Ham satırlar: " . count($rows) . " adet\n";
foreach ($rows as $row) {
    echo "  [{$row['id']}] {$row['name']}\n";
}

// -----------------------------------------------------------------------
// 8. Product — genişletilmiş model örneği
// -----------------------------------------------------------------------
echo "\n--- 8. Product Modeli ---\n";

$product = Product::create(['name' => 'Laptop', 'price' => 15000, 'stock' => 5]);
echo "Ürün oluşturuldu → {$product->name} ({$product->price} TL)\n";

$cheapProducts = Product::where('price', '<', 20000)
    ->orderBy('price')
    ->get();
echo "20.000 TL altı ürünler: " . count($cheapProducts) . "\n";

echo "\n========================================\n";
echo " Tüm örnekler başarıyla çalıştı!\n";
echo "========================================\n";
