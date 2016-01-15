<?php
error_reporting(E_ALL);
ini_set("display_errors", "Off");

class CheckCouponClient {
//    const URL = "http://localhost/shop/shopconnector/site/web/app_dev.php/checkCoupon";
     const URL = "https://dev2.shopconnector.pl/checkCoupon";
    private $errors = array();
    private $link;
    private $response;
    public $coupon;
    private $cart_value = 0;
    private $email = "";
    private $couponType;
    private $emailTemplate;
	private $showPopup;
	private $showBanner;
	private $sendEmail;
    private $scShopId;
    private $minCart;

    public function __construct($coupon = null) {
        $this->coupon = $coupon;
        if(empty($this->coupon)) {
            $this->errors[] = "Bad coupon or new coupon";
        }
    }

    public function setCartValue($val) {
        $this->cart_value = $val;
    }

    public function getMinCart() {
        return $this->minCart;
    }

    public function getDiscountType() {
        return $this->couponType;
    }

	public function getShowBanner() {
		return $this->showBanner;
	}
	
	public function getSendEmail() {
		return $this->sendEmail;
	}
	
	public function getShowPopup() {
		return $this->showPopup;
	}
	
	public function getScShopId() {
		return $this->scShopId;
	}

    public function getEmailTemplate() {
        return $this->emailTemplate;
    }

    public function setUserEmail($val) {
        $this->email = $val;
    }

    /**
     * Decrypt data with key(shop hash)
     * @param $data
     * @param $hash
     * @internal param $val
     * @return null|string
     */
    private function _decryptString($data,$hash)
    {
        $key = $hash;
        /* Open module, and create IV */
        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $key = substr($key, 0, mcrypt_enc_get_key_size($td));
        $iv_size = mcrypt_enc_get_iv_size($td);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        /* Initialize encryption handle */
        if (mcrypt_generic_init($td, $key, $iv) != -1) {
            /* Encrypt data */
            $p_t = mdecrypt_generic($td, $data);
            /* Clean up */
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
            return $p_t;
        }
        return null;
    }

    /**
     * Encrypt data with key(shop hash)
     * @param $data
     * @param $hash
     * @return null|string
     */
    private function _encryptString($data,$hash)
    {
        $key = $hash;
        /* Open module, and create IV */
        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $key = substr($key, 0, mcrypt_enc_get_key_size($td));
        $iv_size = mcrypt_enc_get_iv_size($td);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        /* Initialize encryption handle */
        if (mcrypt_generic_init($td, $key, $iv) != -1) {
            /* Encrypt data */
            $c_t = mcrypt_generic($td, $data);
            /* Clean up */
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
            return $c_t;
        }
        return null;
    }

    /**
     * Create proper coupon parameters
     * @param $coupon
     * @param $hashString
     * @param int $status
     * @return array
     */
    private function _buildCouponParams($coupon, $hashString, $status = 0) {
        $params = array('coupon' => $coupon, 'type' => (int)$status,'cart_value' => (string)$this->cart_value);
        $data = $this->_encryptString(json_encode($params), $hashString);

        $data = base64_encode($data);

        $myfile = fopen("data_encoded.txt", "w") or die("Unable to open file!");
        $txt = "$data\n";
        fwrite($myfile, $txt);
        fclose($myfile);
        return array("data" => $data, "hash" => md5($hashString));
    }

    /**
     * Create new code
     * @param $hash
     * @return bool|mixed
     */
    public function create($hash) {
        $params = $this->_buildCouponParams($this->coupon, $hash, 1);
        $response = $this->_httpConn(self::URL, $params, "post");

        $object_array = json_decode(trim($this->_decryptString($response, $hash)), true);
        if(is_array($object_array) && count($object_array['errors']) == 0) {
            @session_start();
            $_SESSION['discount_coupon'] = $object_array['created']->discount_name;
            $_SESSION['discount_value'] = $object_array['created']->max_discount;
            $_SESSION['discount_text'] = $object_array['created']->discount_text;
            return $object_array;
        }
        return false;
    }

    /**
     * Check if coupon with hash exist
     * @param $hash
     * @return bool|mixed
     */
    public function check($hash) {
        if(empty($this->coupon)) {
            return false;
        }
        $params = $this->_buildCouponParams($this->coupon, $hash);
        $response = $this->_httpConn(self::URL, $params);
        $json_data = json_decode(trim($response));
        $data_from_server =  $this->_decryptString(base64_decode($json_data->data),$hash);
        $data_from_server = str_replace("\0", "", $data_from_server);
        $json_response = json_decode($data_from_server);
		$this->showPopup = $json_response->showPopup;
		$this->showBanner = $json_response->showBanner;
		$this->sendEmail = $json_response->sendEmail;
		$this->scShopId = $json_response->scShopId;

        $myfile = fopen("response_from_server.txt", "w") or die("Unable to open file!");
        $txt = "$response\n";
        fwrite($myfile, $txt);
        fclose($myfile);

        $myfile = fopen("data_from_server.txt", "w") or die("Unable to open file!");
        $txt = "$data_from_server\n";
        fwrite($myfile, $txt);
        fclose($myfile);

        if($json_response->status == 1) {
			
            // if (session_status() == PHP_SESSION_NONE) {
            session_start();
            // }
            $_SESSION['discount_coupon'] = $this->coupon;
            $this->couponType = $json_response->couponType;
            $_SESSION['discount_value'] = $json_response->couponType;
            $this->minCart = $json_response->minCart;
            return true;
        } else {
            $this->minCart = $json_response->minCart;
            $this->couponType = $json_response->couponType;
			
            return $json_response->details;
            //return false;
        }
    }

    /**
     * Confirm coupon code
     * @param $hash
     * @return bool|mixed
     */
    public function confirm($hash) {
        if(empty($this->coupon)) {
            return false;
        }
        $params = $this->_buildCouponParams($this->coupon, $hash, 1);
        $response = $this->_httpConn(self::URL, $params);
        $json_data = json_decode(trim($response));
        $data_from_server =  $this->_decryptString(base64_decode($json_data->data),$hash);
        $data_from_server = str_replace("\0", "", $data_from_server);
        $json_response = json_decode($data_from_server);
        $this->emailTemplate = $json_response->email;
		$this->showPopup = $json_response->showPopup;
		$this->showBanner = $json_response->showBanner;
		$this->sendEmail = $json_response->sendEmail;
		$this->scShopId = $json_response->scShopId;
		
		$myfile = fopen("data_from_server.txt", "w") or die("Unable to open file!");
        $txt = "$data_from_server\n";
        fwrite($myfile, $txt);
        fclose($myfile);
        if($json_response->status == 1) {
			
            // if (session_status() == PHP_SESSION_NONE) {
                session_start();
            // }
            if(isset($_SESSION['discount_coupon'])){
                unset($_SESSION['discount_coupon']);
            }
            if(isset($_SESSION['discount_value'])){
                unset($_SESSION['discount_value']);
            }
           return true;
        }
        return false;
    }

    /**
     * Display error
     * @return bool
     */
    private function _showErrors() {
        if(count($this->errors)) {
            echo "Script interrupted due to some errors in code:\n\n".implode("\n", $this->errors);
            return true;
        }
        echo "No errors.";
        return false;
    }

    /**
     * Create POST request to url with post data
     * @param $url
     * @param $data
     * @return bool|string - respond from url
     */
    private function _httpConn($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}

?>