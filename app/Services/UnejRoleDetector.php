<?php

namespace App\Services;
 
class UnejRoleDetector
{
    const UNEJ_DOMAINS = ['mail.unej.ac.id', 'unej.ac.id'];
 
    /**
     * Deteksi role dari format email UNEJ
     *
     * NIP dosen  = 18 digit → 198410082008121002@mail.unej.ac.id
     * NIM mhs    = 15 digit → 232410102065@mail.unej.ac.id  (tapi ada yg 12 digit lama)
     * NIP tendik = 9 digit  → format lama
     */
    public static function detect(string $email): string
    {
        if (!self::isUnejEmail($email)) {
            return 'umum';
        }
 
        $local = explode('@', strtolower($email))[0];
 
        if (!ctype_digit($local)) {
            return 'dosen';
        }
 
        return match(strlen($local)) {
            18      => 'dosen',
            15, 12  => 'mahasiswa',
            9       => 'tendik',
            default => 'umum',
        };
    }
 
    public static function isUnejEmail(string $email): bool
    {
        $domain = strtolower(explode('@', $email)[1] ?? '');
        return in_array($domain, self::UNEJ_DOMAINS);
    }
 
    public static function isDosen(string $email): bool
    {
        return self::detect($email) === 'dosen';
    }
 
    public static function isMahasiswa(string $email): bool
    {
        return self::detect($email) === 'mahasiswa';
    }
}