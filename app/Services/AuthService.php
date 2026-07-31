<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidCredentialsException;
use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Support\AuthenticationResult;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthService
{
    private const TOKEN_ABILITIES = ['*'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Hasher $hasher,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data, string $deviceName): AuthenticationResult
    {
        return DB::transaction(fn (): AuthenticationResult => $this->issueToken(
            $this->users->create([
                'name' => $data['name'],
                'email' => $this->normalizeEmail($data['email']),
                'password' => $this->hasher->make($data['password']),
            ]),
            $deviceName,
        ));
    }

    /**
     * @throws InvalidCredentialsException
     */
    public function login(string $email, string $password, string $deviceName): AuthenticationResult
    {
        $user = $this->users->findByEmail($this->normalizeEmail($email));

        if (! $user instanceof User || ! $this->hasher->check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        return $this->issueToken($user, $deviceName);
    }

    public function logout(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    private function issueToken(User $user, string $deviceName): AuthenticationResult
    {
        return new AuthenticationResult(
            $user,
            $user->createToken($deviceName, self::TOKEN_ABILITIES)->plainTextToken,
        );
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
