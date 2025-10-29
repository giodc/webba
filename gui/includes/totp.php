<?php
/**
 * TOTP (Time-based One-Time Password) Implementation
 * RFC 6238 compliant 2FA authentication
 */

class TOTP {
    private $secretLength = 32;
    private $codeLength = 6;
    private $period = 30;
    private $algorithm = 'sha1';
    
    /**
     * Generate a random secret key
     */
    public function generateSecret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
        $secret = '';
        for ($i = 0; $i < $this->secretLength; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Generate current TOTP code
     */
    public function generateCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / $this->period);
        }
        
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac($this->algorithm, $time, $secretKey, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        $value = unpack('N', $hashPart);
        $value = $value[1] & 0x7FFFFFFF;
        $code = $value % pow(10, $this->codeLength);
        
        return str_pad($code, $this->codeLength, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP code
     */
    public function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / $this->period);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->generateCode($secret, $currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate QR code URL for authenticator apps
     */
    public function getQRCodeUrl($username, $secret, $issuer = 'WharfTales') {
        $label = urlencode($issuer) . ':' . urlencode($username);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->codeLength,
            'period' => $this->period
        ]);
        
        $otpauthUrl = 'otpauth://totp/' . $label . '?' . $params;
        
        // Use Google Charts API for QR code generation
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($otpauthUrl);
    }
    
    /**
     * Get provisioning URI for manual entry
     */
    public function getProvisioningUri($username, $secret, $issuer = 'WharfTales') {
        $label = urlencode($issuer) . ':' . urlencode($username);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->codeLength,
            'period' => $this->period
        ]);
        
        return 'otpauth://totp/' . $label . '?' . $params;
    }
    
    /**
     * Base32 decode
     */
    private function base32Decode($secret) {
        $secret = strtoupper($secret);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = [6, 4, 3, 1, 0];
        
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }
        
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) {
                return false;
            }
        }
        
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], str_split($alphabet))) {
                return false;
            }
            
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@strpos($alphabet, @$secret[$i + $j]), 10, 2), 5, '0', STR_PAD_LEFT);
            }
            
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }
        
        return $binaryString;
    }
    
    /**
     * Timing safe string comparison
     */
    private function timingSafeEquals($safe, $user) {
        if (function_exists('hash_equals')) {
            return hash_equals($safe, $user);
        }
        
        $safeLen = strlen($safe);
        $userLen = strlen($user);
        
        if ($userLen != $safeLen) {
            return false;
        }
        
        $result = 0;
        for ($i = 0; $i < $userLen; $i++) {
            $result |= (ord($safe[$i]) ^ ord($user[$i]));
        }
        
        return $result === 0;
    }
}
