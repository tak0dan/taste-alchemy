<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repository\AuditRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Support\Nif;

final class AccountService
{
    private UserRepository $users;
    private ProfileRepository $profiles;
    private AuditRepository $audit;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->profiles = new ProfileRepository();
        $this->audit = new AuditRepository();
        $this->notifications = new NotificationService();
    }

    public function registerArtisan(array $data): int
    {
        $this->validateCommon($data);
        if (trim((string) ($data['studio_name'] ?? '')) === '') {
            throw new ValidationException(['studio_name' => 'El nombre del taller es obligatorio.']);
        }
        if (trim((string) ($data['territory'] ?? '')) === '') {
            throw new ValidationException(['territory' => 'Indica el territorio del taller.']);
        }

        $userId = Database::transaction(function () use ($data): int {
            $userId = $this->users->create([
                'email' => $data['email'],
                'password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
                'display_name' => $data['display_name'],
                'birth_date' => $data['birth_date'],
                'nif' => $data['nif'],
                'phone' => $data['phone'] ?? null,
            ]);
            $this->users->addRole($userId, 'artesano', $userId);
            $this->profiles->createArtisan($userId, [
                'studio_name' => $data['studio_name'],
                'slug' => $this->uniqueSlug((string) $data['studio_name'], 'artisan_profiles'),
                'territory' => $data['territory'],
                'bio' => trim((string) ($data['bio'] ?? '')),
                'founded_year' => $data['founded_year'] ?? null,
                'website' => $data['website'] ?? null,
            ]);
            return $userId;
        });

        $this->audit->log('account.registered', 'user', $userId, $userId, ['role' => 'artesano']);
        $this->notifications->notify(
            $userId,
            'welcome',
            'Bienvenido al taller',
            'Publica tu primera receta: cada receta publicada es una petición potencial.',
            '/workshop/recipes/new'
        );
        return $userId;
    }

    public function registerVenue(array $data): int
    {
        $this->validateCommon($data);
        if (trim((string) ($data['venue_name'] ?? '')) === '') {
            throw new ValidationException(['venue_name' => 'El nombre del local es obligatorio.']);
        }
        if (trim((string) ($data['license_number'] ?? '')) === '') {
            throw new ValidationException(['license_number' => 'Indica el número de licencia.']);
        }
        if (trim((string) ($data['territory'] ?? '')) === '') {
            throw new ValidationException(['territory' => 'Indica el territorio del local.']);
        }

        $userId = Database::transaction(function () use ($data): int {
            $userId = $this->users->create([
                'email' => $data['email'],
                'password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
                'display_name' => $data['display_name'],
                'birth_date' => $data['birth_date'],
                'nif' => $data['nif'],
                'phone' => $data['phone'] ?? null,
            ]);
            $this->users->addRole($userId, 'local', $userId);
            $this->profiles->createVenue($userId, [
                'venue_name' => $data['venue_name'],
                'slug' => $this->uniqueSlug((string) $data['venue_name'], 'venue_profiles'),
                'license_number' => $data['license_number'],
                'license_type' => $data['license_type'] ?? 'Licencia de restauración',
                'territory' => $data['territory'],
                'address' => $data['address'] ?? null,
                'license_status' => 'pendiente',
            ]);
            Database::execute(
                'INSERT INTO verification_requests (venue_id, kind, submitted_nif, submitted_license, document_ref, notes)
                 VALUES (?, "licencia", ?, ?, ?, ?)',
                [$userId, $data['nif'], $data['license_number'], $data['document_ref'] ?? null, 'Alta de local']
            );
            return $userId;
        });

        $this->audit->log('account.registered', 'user', $userId, $userId, ['role' => 'local']);
        $this->notifications->notify(
            $userId,
            'verification.pending',
            'Licencia en revisión',
            'Un moderador comprobará tu NIF y tu licencia antes de que puedas abrir peticiones.',
            '/bar/verification'
        );
        return $userId;
    }

    public function updateProfile(int $userId, array $data): void
    {
        $user = $this->users->find($userId);
        if ($user === null) {
            throw HttpException::notFound('La cuenta no existe.');
        }
        $errors = [];
        if (trim((string) ($data['display_name'] ?? '')) === '') {
            $errors['display_name'] = 'El nombre es obligatorio.';
        }
        $nif = Nif::normalize($data['nif'] ?? null);
        if ($nif !== null && !Nif::valid($nif)) {
            $errors['nif'] = 'El NIF/NIE/CIF no es válido.';
        }
        if (!empty($data['birth_date'])) {
            $age = $this->age((string) $data['birth_date']);
            if ($age === null || $age < 18) {
                $errors['birth_date'] = 'Debes tener al menos 18 años.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->users->update($userId, [
            'display_name' => trim((string) $data['display_name']),
            'birth_date' => $data['birth_date'] ?? $user['birth_date'],
            'nif' => $nif,
            'phone' => trim((string) ($data['phone'] ?? '')),
        ]);

        if (AuthService::hasRole('artesano') && trim((string) ($data['studio_name'] ?? '')) !== '') {
            $this->profiles->updateArtisan($userId, [
                'studio_name' => trim((string) $data['studio_name']),
                'territory' => trim((string) ($data['territory'] ?? 'Comunitat Valenciana')),
                'bio' => trim((string) ($data['bio'] ?? '')),
                'founded_year' => $data['founded_year'] ?? null,
                'website' => $data['website'] ?? null,
            ]);
        }
        if (AuthService::hasRole('local') && trim((string) ($data['venue_name'] ?? '')) !== '') {
            $this->profiles->updateVenue($userId, [
                'venue_name' => trim((string) $data['venue_name']),
                'territory' => trim((string) ($data['territory'] ?? 'Comunitat Valenciana')),
                'address' => trim((string) ($data['address'] ?? '')),
                'license_number' => trim((string) ($data['license_number'] ?? '')),
                'license_type' => trim((string) ($data['license_type'] ?? 'Licencia de restauración')),
            ]);
        }

        $this->audit->log('account.updated', 'user', $userId, $userId);
        AuthService::forget();
    }

    public function changePassword(int $userId, string $current, string $new): void
    {
        $user = $this->users->find($userId);
        if ($user === null || !password_verify($current, (string) $user['password_hash'])) {
            throw new ValidationException(['current_password' => 'La contraseña actual no es correcta.']);
        }
        $this->validatePassword($new, 'password');
        Database::execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($new, PASSWORD_BCRYPT), $userId]
        );
        $this->audit->log('account.password', 'user', $userId, $userId);
    }

    private function validateCommon(array $data): void
    {
        $errors = [];
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Introduce un correo válido.';
        } elseif ($this->users->findByEmail($email) !== null) {
            $errors['email'] = 'Ese correo ya está registrado.';
        }
        if (trim((string) ($data['display_name'] ?? '')) === '') {
            $errors['display_name'] = 'Indica tu nombre.';
        }
        try {
            $this->validatePassword((string) ($data['password'] ?? ''), 'password');
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors);
        }
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? null)) {
            $errors['password_confirmation'] = 'Las contraseñas no coinciden.';
        }
        if (empty($data['birth_date'])) {
            $errors['birth_date'] = 'La fecha de nacimiento es necesaria para verificar la mayoría de edad.';
        } else {
            $age = $this->age((string) $data['birth_date']);
            if ($age === null) {
                $errors['birth_date'] = 'Fecha de nacimiento no válida.';
            } elseif ($age < 18) {
                $errors['birth_date'] = 'Debes tener al menos 18 años.';
            }
        }
        $nif = Nif::normalize($data['nif'] ?? null);
        if ($nif === null) {
            $errors['nif'] = 'El NIF/NIE/CIF es obligatorio.';
        } elseif (!Nif::valid($nif)) {
            $errors['nif'] = 'El NIF/NIE/CIF no es válido.';
        } elseif (Database::value('SELECT COUNT(*) FROM users WHERE nif = ?', [$nif])) {
            $errors['nif'] = 'Ese NIF ya está registrado.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function validatePassword(string $password, string $field): void
    {
        if (mb_strlen($password) < 8) {
            throw new ValidationException([$field => 'La contraseña debe tener al menos 8 caracteres.']);
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new ValidationException([$field => 'La contraseña debe combinar letras y números.']);
        }
    }

    private function age(string $birthDate): ?int
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
        if ($date === false) {
            return null;
        }
        return (new \DateTimeImmutable('today'))->diff($date)->y;
    }

    private function uniqueSlug(string $source, string $table): string
    {
        $base = slugify($source) ?: 'perfil';
        $slug = $base;
        $i = 2;
        while ($this->profiles->slugExists($slug, $table)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}
