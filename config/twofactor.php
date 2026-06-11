<?php
// /config/twofactor_fallback.php
// Fallback-Implementierung für TOTP (RFC6238) ohne externe Bibliothek

if (!function_exists('base32_decode')) {
    function base32_decode($b32) {
        $b32 = strtoupper($b32);
        // Remove any non-base32 characters (spaces, hyphens, etc.)
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);
        // Add padding if needed
        $b32 = str_pad($b32, ceil(strlen($b32) / 8) * 8, '=', STR_PAD_RIGHT);
        
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $l = strlen($b32);
        $n = 0; $j = 0; $binary = '';
        for ($i = 0; $i < $l; $i++) {
            $char = $b32[$i];
            if ($char === '=') break;
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            $n = ($n << 5) | $pos;
            $j += 5;
            if ($j >= 8) {
                $j -= 8;
                $binary .= chr(($n & (0xFF << $j)) >> $j);
            }
        }
        return $binary;
    }
}

if (!function_exists('base32_encode')) {
    function base32_encode($data) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = 0; $bitbuffer = 0; $output = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $bitbuffer = ($bitbuffer << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= $alphabet[($bitbuffer >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $output .= $alphabet[($bitbuffer << (5 - $bits)) & 0x1F];
        }
        return $output;
    }
}

if (!function_exists('tf_generate_secret')) {
    function tf_generate_secret($length = 20) {
        // generate random bytes and base32 encode
        try {
            $bytes = openssl_random_pseudo_bytes($length);
            if ($bytes === false) $bytes = random_bytes($length);
        } catch (Exception $e) {
            // fallback to less secure
            $bytes = '';
            for ($i = 0; $i < $length; $i++) $bytes .= chr(mt_rand(0, 255));
        }
        return base32_encode($bytes);
    }
}

if (!function_exists('tf_get_provisioning_uri')) {
    function tf_get_provisioning_uri($secret, $user_label, $issuer = 'Datei Wolke') {
        if (empty($secret) || empty($user_label)) return null;
        // Use a human-friendly label like "Datei Wolke: Username"
        $label = rawurlencode($issuer . ': ' . $user_label);
        $params = http_build_query(['secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30]);
        return 'otpauth://totp/' . $label . '?' . $params;
    }
}

if (!function_exists('tf_totp_at')) {
    function tf_totp_at($secret, $time_counter) {
        $key = base32_decode($secret);
        $counter = pack('N*', 0) . pack('N*', $time_counter); // 64-bit BE
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = (ord($hash[$offset]) & 0x7F) << 24 |
                  (ord($hash[$offset + 1]) & 0xFF) << 16 |
                  (ord($hash[$offset + 2]) & 0xFF) << 8 |
                  (ord($hash[$offset + 3]) & 0xFF);
        $otp = $binary % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('tf_verify_code')) {
    function tf_verify_code($secret, $code, $window = 1) {
        if (empty($secret) || empty($code)) return false;
        $time = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(tf_totp_at($secret, $time + $i), $code)) return true;
        }
        return false;
    }
}

if (!function_exists('tf_generate_backup_codes')) {
    function tf_generate_backup_codes($count = 8) {
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = bin2hex(openssl_random_pseudo_bytes(4));
            $plain[] = $code;
            $hashes[] = password_hash($code, PASSWORD_DEFAULT);
        }
        return ['plain' => $plain, 'hashes' => json_encode($hashes), 'json' => json_encode(['hashes' => $hashes, 'plain' => $plain])];
    }
}

if (!function_exists('tf_get_totps_for_secret')) {
    function tf_get_totps_for_secret($secret, $window = 1) {
        $server_time = time();
        $time_counter = floor($server_time / 30);
        $codes = [];
        for ($i = -$window; $i <= $window; $i++) {
            $counter = $time_counter + $i;
            $otp = tf_totp_at($secret, $counter);
            $time_start = $counter * 30;
            $time_end = ($counter + 1) * 30;
            $codes[] = ['counter' => $counter, 'otp' => $otp, 'time_start' => $time_start, 'time_end' => $time_end];
        }
        return ['server_time' => $server_time, 'time_counter' => $time_counter, 'window' => $window, 'codes' => $codes];
    }
}

if (!function_exists('tf_verify_backup_code')) {
    function tf_verify_backup_code($user_id, $provided_code) {
        if (empty($provided_code)) return false;
        $stmt = $conn->prepare("SELECT two_factor_backup_codes FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $user_id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row || empty($row['two_factor_backup_codes'])) return false;
        $data = json_decode($row['two_factor_backup_codes'], true);
        if (!is_array($data) || !isset($data['hashes'])) return false;
        $hashes = $data['hashes'];
        foreach ($hashes as $idx => $h) {
            if (password_verify($provided_code, $h)) {
                array_splice($hashes, $idx, 1);
                $new_data = ['hashes' => $hashes, 'plain' => isset($data['plain']) ? $data['plain'] : []];
                if (isset($new_data['plain'][$idx])) array_splice($new_data['plain'], $idx, 1);
                $new_json = json_encode($new_data);
                $ustmt = $conn->prepare("UPDATE users SET two_factor_backup_codes = ? WHERE id = ?");
                if ($ustmt) { $ustmt->bind_param('si', $new_json, $user_id); $ustmt->execute(); $ustmt->close(); }
                return true;
            }
        }
        return false;
    }
}
