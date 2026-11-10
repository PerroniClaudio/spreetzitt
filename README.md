# ⚙️ Spreetzitt Backend API

Backend Laravel dell'applicazione Spreetzitt con API REST, autenticazione, e integrazione con servizi moderni.

## 🚀 Panoramica

Il backend è un'API Laravel completa che include:

-   **Framework**: Laravel 10+ (PHP 8.1+)
-   **Database**: MySQL/PostgreSQL (configurabile)
-   **Cache**: Redis per sessioni e cache
-   **Search**: Meilisearch per ricerca avanzata
-   **Queue**: Laravel Horizon per job in background
-   **Authentication**: Laravel Sanctum per API
-   **Testing**: PHPUnit e Pest per testing

## 🏗️ Struttura del Progetto

```
support-api/
├── app/
│   ├── Actions/           # Action classes
│   ├── Console/           # Artisan commands
│   ├── Exceptions/        # Exception handlers
│   ├── Exports/           # Export classes
│   ├── Http/
│   │   ├── Controllers/   # API Controllers
│   │   ├── Middleware/    # Custom middleware
│   │   └── Requests/      # Form requests
│   ├── Imports/           # Import classes
│   ├── Jobs/              # Queue jobs
│   ├── Mail/              # Mail classes
│   ├── Models/            # Eloquent models
│   └── Providers/         # Service providers
├── config/                # Configurazioni Laravel
├── database/
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/           # Database seeders
├── routes/
│   ├── api.php           # API routes
│   ├── web.php           # Web routes
│   ├── auth.php          # Auth routes
│   └── webhook.php       # Webhook routes
├── tests/                 # Test suite
├── storage/               # File storage
└── vendor/                # Composer dependencies
```

## 🚀 Quick Start

### Con Docker (Consigliato)

```bash
# Dalla directory server/
make up

# L'API sarà disponibile su:
# https://preprodapi.ifortech.com/api (via Nginx)
```

Il frontend locale è disponibile su `https://preprod.ifortech.com/support/admin`.
Entrambi i domini devono puntare a `127.0.0.1` nel file `/etc/hosts` del PC.
Il login Microsoft ritorna al browser che ha aperto il popup; il telefono può
essere usato per approvare la richiesta con Authenticator.

`make up` controlla le porte TCP pubblicate da Compose, inclusa Redis sulla 6379,
e arresta i processi o i container di altri progetti che le occupano. I container
del progetto connessi alla rete vengono conservati; quelli accesi senza rete
vengono ricreati conservando i volumi. Servono Python 3, `lsof` e `ss` (iproute2).
Per processi appartenenti ad altri utenti può essere richiesta la password sudo.

Per HTTPS servono `mkcert`, `openssl` e `libnss3-tools` (su Ubuntu:
`sudo apt install mkcert libnss3-tools openssl lsof iproute2`). L'avvio genera
certificati locali quando mancano o scadono entro 30 giorni, conserva una copia
dei precedenti in `docker/ssl/backups/` e ricarica Nginx. La CA locale viene
registrata nei profili NSS dei browser, incluso Firefox Snap: dopo la prima
generazione occorre riavviare il browser. Certificati e chiavi restano esclusi
da Git. Per rendere attendibile la CA anche agli strumenti che usano il trust
store di sistema, eseguire `mkcert -install` con accesso sudo.

### Comandi Backend

```bash
# Accedi al container backend
make shell-backend

# Comandi Artisan
make artisan CMD="migrate"
make artisan CMD="db:seed"
make artisan CMD="key:generate"

# Log del backend
make backend-logs
```

## 🛠️ Comandi Artisan Utili

### Database

```bash
# Migrazioni
make artisan CMD="migrate"
make artisan CMD="migrate:fresh"
make artisan CMD="migrate:rollback"

# Seeder
make artisan CMD="db:seed"
make artisan CMD="db:seed --class=UserSeeder"

# Tinker (REPL)
make artisan CMD="tinker"
```

### Cache e Performance

```bash
# Cache delle configurazioni
make artisan CMD="config:cache"
make artisan CMD="route:cache"
make artisan CMD="view:cache"

# Pulizia cache
make artisan CMD="cache:clear"
make artisan CMD="config:clear"
make artisan CMD="route:clear"
make artisan CMD="view:clear"
```

### Code Generation

```bash
# Creare componenti
make artisan CMD="make:controller UserController --api"
make artisan CMD="make:model Post -mfsr"
make artisan CMD="make:request StoreUserRequest"
make artisan CMD="make:job ProcessPayment"
make artisan CMD="make:mail WelcomeEmail"
```

## 🔧 Configurazione

### Variabili d'Ambiente

Il file `.env` principale è nella directory `server/`. Variabili importanti:

```env
# App
APP_NAME=Spreetzitt
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=spreetzitt
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=your-master-key

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

# Queue
QUEUE_CONNECTION=redis
```

### Prima Configurazione

```bash
# Setup iniziale
make artisan CMD="key:generate"
make artisan CMD="migrate"
make artisan CMD="db:seed"

# Reset completo
make artisan CMD="migrate:fresh --seed"
```

## 📡 API Documentation

### Struttura API

Le API seguono le convenzioni REST:

```
GET    /api/users           # Lista utenti
POST   /api/users           # Crea utente
GET    /api/users/{id}      # Dettaglio utente
PUT    /api/users/{id}      # Aggiorna utente
DELETE /api/users/{id}      # Elimina utente
```

### Autenticazione

Laravel Sanctum per autenticazione API:

```bash
# Login
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "password"
}

# Risposta
{
  "token": "1|abcdef...",
  "user": { ... }
}

# Uso del token
Authorization: Bearer 1|abcdef...
```

### Response Format

Tutte le API seguono un formato standard:

```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful",
  "meta": {
    "current_page": 1,
    "total": 100
  }
}
```

## 🔍 Integrazione Meilisearch

### Setup nei Model

```php
<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Searchable;

    protected $fillable = ['title', 'content', 'author_id'];

    // Definisci cosa indicizzare
    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'author' => $this->author->name,
        ];
    }
}
```

### Comandi Search

```bash
# Importa tutti i record esistenti
make artisan CMD="scout:import 'App\Models\Post'"

# Reindicizza tutto
make artisan CMD="scout:fresh 'App\Models\Post'"

# Apri Meilisearch UI
make meilisearch-ui
```

## 💾 Redis per Cache e Queue

### Cache

```php
<?php

use Illuminate\Support\Facades\Cache;

// Salva in cache
Cache::put('users.all', $users, 3600); // 1 ora

// Recupera dalla cache
$users = Cache::remember('users.all', 3600, function () {
    return User::all();
});
```

### Queue Jobs

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Logica del job
    }
}

// Dispatch del job
ProcessEmail::dispatch();
```

## 🧪 Testing

### Eseguire Test

```bash
# Tutti i test
make shell-backend
./vendor/bin/pest

# Test specifici
./vendor/bin/pest tests/Feature/UserTest.php
./vendor/bin/pest --filter test_user_can_login

# Con coverage
./vendor/bin/pest --coverage
```

### Esempio Test

```php
<?php

use App\Models\User;

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password')
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'email']
        ]);
});
```

## 🔧 Sviluppo e Debug

### Tinker (REPL Laravel)

```bash
make artisan CMD="tinker"

# Esempi in Tinker
User::count()
User::factory()->create()
Cache::put('test', 'value')
```

### Logging

```php
<?php

use Illuminate\Support\Facades\Log;

// Diversi livelli di log
Log::info('User logged in', ['user_id' => $user->id]);
Log::error('Database connection failed');
Log::debug('Debug information');
```

### Debug in Real Time

```bash
# Log generale
make backend-logs

# Log specifici
make shell-backend
tail -f storage/logs/laravel.log
```

## 🚀 Deployment e Ottimizzazioni

### Ottimizzazioni Produzione

```bash
# Cache tutto per produzione
make artisan CMD="config:cache"
make artisan CMD="route:cache"
make artisan CMD="view:cache"

# Ottimizza autoloader
make shell-backend
composer install --optimize-autoloader --no-dev

# Link storage pubblico
make artisan CMD="storage:link"
```

## 🐛 Troubleshooting

### Problemi Comuni

**Errori di cache:**

```bash
make artisan CMD="cache:clear"
make artisan CMD="config:clear"
make artisan CMD="route:clear"
```

**Problemi database:**

```bash
make artisan CMD="migrate:status"
make artisan CMD="migrate --force"
```

**Problemi Meilisearch:**

```bash
# Controlla connessione
make meilisearch-logs

# Reindicizza
make artisan CMD="scout:fresh 'App\Models\Post'"
```

**Problemi queue:**

```bash
make artisan CMD="queue:restart"
make redis-cli  # Accedi a Redis per debug
```

### Log e Debug

```bash
# Log backend
make backend-logs

# Accedi al container
make shell-backend

# Controlla configurazione
php artisan config:show database
php artisan route:list
```

## 📚 Risorse Utili

### Documentazione

-   [Laravel Documentation](https://laravel.com/docs)
-   [Laravel Scout (Meilisearch)](https://laravel.com/docs/scout)
-   [Laravel Sanctum (API Auth)](https://laravel.com/docs/sanctum)
-   [Laravel Horizon (Queue)](https://laravel.com/docs/horizon)
-   [Pest Testing](https://pestphp.com/)

### Package Consigliati

-   **Laravel Telescope**: Debug e profiling avanzato
-   **Spatie Laravel Permission**: Gestione ruoli e permessi
-   **Laravel Excel**: Import/Export Excel
-   **Laravel Backup**: Backup automatici

---

**Happy coding! ⚙️**

_Backend completamente dockerizzato per Spreetzitt. Usa i comandi Make per un'esperienza di sviluppo fluida!_
