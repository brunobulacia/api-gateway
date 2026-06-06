#!/bin/sh
set -e

DB=/var/www/database/database.sqlite
SENTINEL=/var/www/database/.seeded

# First boot: run migrations and seed users (sentinel prevents repeat)
if [ ! -f "$SENTINEL" ]; then
  touch "$DB"
  php artisan migrate --force --no-interaction

  php artisan tinker --execute="
use App\Models\User;
\$users = [
  ['name'=>'Admin EduPay',       'email'=>'admin@edupay.bo',               'password'=>bcrypt('admin1234'), 'role'=>'ADMIN',   'family_id'=>null],
  ['name'=>'Juan Carlos Perez',  'email'=>'juan.perez@example.com',        'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-001'],
  ['name'=>'Maria Lopez',        'email'=>'maria.lopez@example.com',       'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-002'],
  ['name'=>'Carlos Gutierrez',   'email'=>'carlos.gutierrez@example.com',  'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-003'],
  ['name'=>'Ana Flores',         'email'=>'ana.flores@example.com',        'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-004'],
  ['name'=>'Roberto Mamani',     'email'=>'roberto.mamani@example.com',    'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-005'],
  ['name'=>'Lucia Vargas',       'email'=>'lucia.vargas@example.com',      'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-006'],
  ['name'=>'Diego Quispe',       'email'=>'diego.quispe@example.com',      'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-007'],
  ['name'=>'Sofia Rojas',        'email'=>'sofia.rojas@example.com',       'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-008'],
  ['name'=>'Miguel Torres',      'email'=>'miguel.torres@example.com',     'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-009'],
  ['name'=>'Elena Morales',      'email'=>'elena.morales@example.com',     'password'=>bcrypt('padre1234'), 'role'=>'PARENT',  'family_id'=>'FAM-010'],
];
foreach(\$users as \$u) { User::create(\$u); }
echo 'Seeded: '.User::count().' users';
"
  touch "$SENTINEL"
fi

exec php artisan serve --host=0.0.0.0 --port=80
