<?php
// Implémentation TOTP (RFC 6238) en PHP pur — sans dépendance externe

class TOTP {
    private const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(): string {
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= self::CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    public static function verify(string $secret, string $code, int $window = 1): bool {
        $t = (int)floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (self::computeCode($secret, $t + $i) === $code) return true;
        }
        return false;
    }

    public static function computeCode(string $secret, ?int $t = null): string {
        if ($t === null) $t = (int)floor(time() / self::PERIOD);
        $key  = self::base32Decode(strtoupper($secret));
        $time = pack('J', $t); // 64-bit big-endian
        $hash = hash_hmac('sha1', $time, $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $code = ((ord($hash[$off])   & 0x7F) << 24)
              | ((ord($hash[$off+1]) & 0xFF) << 16)
              | ((ord($hash[$off+2]) & 0xFF) << 8)
              |  (ord($hash[$off+3]) & 0xFF);
        return str_pad($code % (10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function getOtpauthUrl(string $secret, string $email, string $issuer = 'FiscalControl'): string {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
             . '?secret=' . $secret
             . '&issuer=' . rawurlencode($issuer)
             . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    private static function base32Decode(string $input): string {
        $map    = array_flip(str_split(self::CHARS));
        $output = '';
        $buf    = 0;
        $bits   = 0;
        foreach (str_split($input) as $char) {
            if (!isset($map[$char])) continue;
            $buf   = ($buf << 5) | $map[$char];
            $bits += 5;
            if ($bits >= 8) {
                $output .= chr(($buf >> ($bits - 8)) & 0xFF);
                $bits   -= 8;
            }
        }
        return $output;
    }
}
