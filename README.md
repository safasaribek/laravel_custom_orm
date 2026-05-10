# Laravel 12 + Mini ORM

> **Mülakat görevi:** Laravel 12 iskelet üzerinde, Eloquent kullanmadan, sıfırdan PHP/PDO ile yazılmış fluent mini ORM kütüphanesi.

---

## Mimari Özet

```
app/Orm/
├── Database.php          # PDO bağlantı yöneticisi (Singleton)
├── QueryBuilder.php      # Fluent SQL builder (Model'den bağımsız çalışır)
├── Model.php             # Abstract base model (CRUD + ilişki API'si)
└── Relations/
    ├── Relation.php      # Tüm ilişki türleri için abstract base
    ├── HasOne.php        # 1-1 ilişki (parent → child)
    ├── HasMany.php       # 1-N ilişki
    ├── BelongsTo.php     # Ters 1-N ilişkisi
    └── BelongsToMany.php # N-N ilişkisi (pivot tablo)
```

### Sınıfların Rolleri

| Sınıf | Sorumluluk |
|---|---|
| `Database` | Singleton PDO bağlantısı, `.env` okur |
| `QueryBuilder` | `WHERE`, `ORDER BY`, `LIMIT` vb. zincirleme SQL üretimi; `prepare/bindValue` ile SQL injection koruması |
| `Model` | `create/find/update/delete/all`, statik `where/with` başlatıcıları, `toArray/toJson`, magic `__get` |
| `Relations/*` | Her ilişki türü için `get()` (lazy) ve `eagerLoadFor()` (eager, N+1 çözümü) |

---

## Kurulum

### Gereksinimler

- Docker & Docker Compose

### 1. Repoyu klonla

```bash
git clone <git@github.com:safasaribek/laravel_custom_orm.git>
cd laravel_custom_orm
```

### 2. Ortam Dosyasını Hazırla

```bash
cp .env.example .env
```

### 3. Konteynerleri Başlat

```bash
docker-compose up -d --build
```

Bu komut:
- PHP 8.4 + Laravel 12 konteynerini (`orm_app`) oluşturur.
- Bağımlılıkları (`composer install`) otomatik yükler.
- `bootstrap/cache` ve `storage` izinlerini ayarlar.
- `APP_KEY`'i otomatik oluşturur.
- MySQL 8 konteynerini (`orm_db`) başlatır.
- `database/init.sql` ile tabloları otomatik oluşturur.

### 4. Uygulamayı Aç

Tarayıcıdan şu adrese gidebilirsiniz:
[http://localhost:8000](http://localhost:8000)

Veya örnek scripti terminalden çalıştırarak ORM'i test edin:

```bash
docker-compose exec app php example.php
```

---

## Örnek Kullanım

### Temel CRUD

```php
use App\Models\User;

// Oluştur
$user = User::create(['name' => 'Ali Veli', 'email' => 'ali@test.com', 'status' => 'active', 'age' => 25]);

// Bul
$user = User::find(1);

// Güncelle
User::update(1, ['name' => 'Veli Ali']);

// Sil
User::delete(1);

// Tümünü getir
$users = User::all();
```

### Fluent Query Builder

```php
$users = User::where('status', 'active')
             ->where('age', '>', 18)
             ->orWhere('role', 'admin')
             ->orderBy('created_at', 'desc')
             ->limit(10)
             ->offset(20)
             ->get();
```

### Kısa Sorgular

```php
$count  = User::where('status', 'active')->count();
$first  = User::where('email', 'like', '%@gmail.com')->first();
$exists = User::where('email', 'ali@test.com')->exists();
```

### İlişkiler

```php
// belongsTo
$post   = Post::find(1);
$author = $post->user()->get();   // User nesnesi

// hasMany
$user  = User::find(1);
$posts = $user->posts()->get();   // Post[]

// belongsToMany (pivot)
$tags = $post->tags()->get();     // Tag[]
```

### Eager Loading (N+1 çözümü)

```php
// Tek sorguda tüm post'ları user'larıyla getir
$posts = Post::with('user')->get();

foreach ($posts as $post) {
    echo $post->getRelation('user')->name;
}
```

### Çıktı Dönüştürücüler

```php
$user = User::find(1);
$arr  = $user->toArray();   // PHP dizisi
$json = $user->toJson();    // JSON string
```

### Model'den Bağımsız QueryBuilder

```php
use App\Orm\QueryBuilder;

$rows = QueryBuilder::table('users')
    ->select(['id', 'name'])
    ->where('status', 'active')
    ->limit(5)
    ->get();   // ham dizi döner, model değil
```

### Yeni Model Tanımı

```php
class Product extends Model
{
    protected string $table    = 'products';
    protected array  $fillable = ['name', 'price', 'stock'];
}

// Hemen çalışır:
Product::create(['name' => 'Laptop', 'price' => 15000, 'stock' => 5]);
Product::where('price', '<', 20000)->orderBy('price')->get();
```

---

## Test Komutları

```bash
# Tüm testleri çalıştır
docker-compose exec app ./vendor/bin/phpunit

# Belirli suite
docker-compose exec app ./vendor/bin/phpunit tests/Unit/OrmTest.php

# Renkli + detaylı çıktı
docker-compose exec app ./vendor/bin/phpunit --testdox
```

### Test kapsamı

- ✅ QueryBuilder SQL üretimi (SELECT, WHERE, ORDER BY, LIMIT, OFFSET)
- ✅ Çoklu WHERE zinciri (AND / OR)
- ✅ SQL injection koruması (değerler asla SQL'e interpolate edilmez)
- ✅ Geçersiz operatör exception'ı
- ✅ Model `fill`, `getAttribute`, `toArray`, `toJson`
- ✅ İlişki nesnesi tipleri (HasMany, BelongsTo, BelongsToMany)
- ✅ İlişki eager set/get round-trip

---

## Güvenlik

Tüm sorgular `PDO::prepare()` + `bindValue()` kullanır. Kullanıcı girdisi asla SQL stringine doğrudan eklenmez:

```php
// ✅ Her zaman böyle — değer binding listesinde
->where('email', $userInput)
// → "WHERE `email` = ?" + bindings: [$userInput]

// ❌ Asla böyle değil
"WHERE email = '{$userInput}'"
```

---

## SOLID Uyumu

| Prensip | Uygulama |
|---|---|
| **S**ingle Responsibility | Her sınıf tek iş yapar (DB bağlantısı, SQL üretimi, model mantığı ayrı) |
| **O**pen/Closed | Yeni model → sadece `extends Model`; yeni ilişki → `extends Relation` |
| **L**iskov Substitution | Tüm `Relation` alt sınıfları `get()` / `eagerLoadFor()` kontraktını karşılar |
| **I**nterface Segregation | `QueryBuilder` Model'e bağımlı değil, bağımsız kullanılabilir |
| **D**ependency Inversion | `Model` doğrudan PDO'ya değil, `Database` singleton'ına bağımlı |

---

## Süre

Toplam: **11:47~15:26**

# laravel_custom_orm