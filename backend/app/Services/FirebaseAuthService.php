<?php

namespace App\Services;

use Kreait\Firebase\Factory;

class FirebaseAuthService
{
    public function verify(string $idToken): array
    {
        $path = (string) config(
            'fastsheba.firebase.service_account_path'
        );

        if (! is_file($path)) {
            throw new \RuntimeException(
                'Firebase service account file was not found.'
            );
        }

        $auth = (new Factory())
            ->withServiceAccount($path)
            ->createAuth();

        $verified = $auth->verifyIdToken($idToken);
        $uid = (string) $verified->claims()->get('sub');
        $record = $auth->getUser($uid);
        $claims = $verified->claims()->all();

        return [
            'uid' => $uid,
            'email' => $record->email ?? ($claims['email'] ?? null),
            'name' => $record->displayName
                ?? ($claims['name'] ?? null),
            'phone_number' => $record->phoneNumber
                ?? ($claims['phone_number'] ?? null),
            'email_verified' => (bool) (
                $record->emailVerified
                ?? ($claims['email_verified'] ?? false)
            ),
            'claims' => $claims,
        ];
    }
}