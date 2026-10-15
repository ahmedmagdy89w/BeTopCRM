<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OneTimePasswordService
{
    public function issue(string $identifier, string $purpose, int $expiresInMinutes = 10): string
    {
        $code = (string) random_int(100000, 999999);
        $now = now();

        DB::table('one_time_passwords')
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->delete();

        DB::table('one_time_passwords')->insert([
            'purpose' => $purpose,
            'identifier' => $identifier,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => $now->copy()->addMinutes($expiresInMinutes),
            'consumed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $code;
    }

    public function consume(string $identifier, string $purpose, string $code, int $maxAttempts = 5): bool
    {
        return DB::transaction(function () use ($identifier, $purpose, $code, $maxAttempts): bool {
            $otp = DB::table('one_time_passwords')
                ->where('identifier', $identifier)
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->consumed_at !== null || now()->greaterThan($otp->expires_at)) {
                return false;
            }

            if ($otp->attempts >= $maxAttempts) {
                DB::table('one_time_passwords')->where('id', $otp->id)->delete();

                return false;
            }

            if (! Hash::check($code, $otp->code_hash)) {
                DB::table('one_time_passwords')->where('id', $otp->id)->increment('attempts');

                return false;
            }

            DB::table('one_time_passwords')->where('id', $otp->id)->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }
}
