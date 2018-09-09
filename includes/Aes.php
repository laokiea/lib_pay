<?php

/** 
  * @param simple aes encrypt 
  * @date 2017/10/23
  * @author ssp
  */

namespace lib\includes;

class Aes 
{
    private $key;

    private $iv = null;

    private $size;

    private $map = [
        '128' => MCRYPT_RIJNDAEL_128,
        '192' => MCRYPT_RIJNDAEL_192,
        '256' => MCRYPT_RIJNDAEL_256,
        'cbc' => MCRYPT_MODE_CBC,
        'ecb' => MCRYPT_MODE_ECB,
    ];

    private $autoPadding = false;

    /**
     * init
     *
     */
    public function __construct($key = '',$name = '128', $mode = 'cbc',$autoPadding = false) 
    {
        $this->name = $name;
        $this->mcrypt = extension_loaded("mcrypt");
        $this->cipher = $this->map[$name];
        $this->mode   = $this->map[$mode];

        $this->size   = $this->mcrypt ? 
                        mcrypt_get_block_size($this->cipher, $this->mode) : 
                        $this->mcrypt_get_block_size($this->cipher);

        $this->key    = substr(hash('sha256', $key), 0, $this->size);
        $this->iv     = $this->key;
        $this->autoPadding = $autoPadding;
    }

    /**
     * encrypt
     *
     */
    public function encrypt($data) 
    {
        $data = trim($data);
        if(!$this->autoPadding && $this->mcrypt) {
            $data = $this->addPKCS7Padding($data);
        }

        $data = $this->mcrypt ? 
                mcrypt_encrypt($this->cipher, $this->key, $data, $this->mode, $this->iv) : 
                openssl_encrypt($data, "AES-".$this->name."-CBC", $this->key, OPENSSL_RAW_DATA, $this->iv);

        return base64_encode($data);
    }

    /**
     * decrypt
     *
     */
    public function decrypt($data)
    {
        $data = base64_decode(trim($data));

        $data = $this->mcrypt ? 
                mcrypt_decrypt($this->cipher, $this->key, $data, $this->mode, $this->iv) : 
                openssl_decrypt($data, "AES-".$this->name."-CBC", $this->key, OPENSSL_RAW_DATA, $this->iv);

        return $this->mcrypt ? $this->stripPKCS7Padding($data) : $data;
    }

    /**
     * padding PKCS7
     *
     */
    public function addPKCS7Padding($data) 
    {
        $sub = $this->size - strlen($data) % $this->size;
        $paddingStr = chr($sub);
        return $data.str_repeat($paddingStr, $sub);
    }

    /**
     * strip padding
     *
     */
    public function stripPKCS7Padding($data) 
    {
        $paddingStr = substr($data, -1);
        return substr($data, 0, -ord($paddingStr));
    }

    public function mcrypt_get_block_size($cipher)
    {
        switch($cipher) {
            case MCRYPT_RIJNDAEL_128:
            return 16;
            break;
            case MCRYPT_RIJNDAEL_192:
            return 24;
            break;
            case MCRYPT_RIJNDAEL_256:
            return 32;
            break;
            default:
            return 16;
            break;
        }
    }
}