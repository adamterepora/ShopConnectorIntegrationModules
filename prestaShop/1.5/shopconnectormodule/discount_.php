<?php
	if(!Module::isInstalled('shopconnectormodule') || !Module::isEnabled('shopconnectormodule')){
		return;
	}

	if (isset($_REQUEST['discount_name'])){
		$cookieset = false;
		$sc_cookie = json_decode(stripslashes(html_entity_decode($_COOKIE['shopconnector_coupon'])), true);
		if(!is_array($sc_cookie)) $sc_cookie = array();

		define("HASH", Configuration::get('SHOPCONNECTORMODULE_HASH')); //UNIKALNY HASH KAŻDEGO PARTNERA
		include_once("check_coupon_client.php");
		
                $cart = Context::getContext()->cart;

		$discountName = $_REQUEST['discount_name']; //POBRANIE KODU Z GET LUB POST
		$customer = new Customer($cart->id_customer); //DEFINICJA PODSTAWOWYCH DANYCH ZALOGOWANEGO UŻYTKOWNIKA - PRESTA CODE
		define("SC_CUSTOMER_FIRSTNAME", $customer->firstname);
		define("SC_CUSTOMER_LASTNAME", $customer->lastname);
		define("SC_CUSTOMER_EMAIL", $customer->email);

		//$cart = new Cart();
		$total_cart_price = $cart->getOrderTotal();

		
		$coupon = new CheckCouponClient($discountName); //SPRAWDZENIE ISTNIENIA KODU W SHOPCONNECTOR
		$coupon->setCartValue((string)$total_cart_price);
		$couponData = $coupon->check(HASH);
		$showPopup = $coupon->getShowPopup();
		$showBanner = $coupon->getShowBanner();
		$sendEmail = $coupon->getSendEmail();
		$scShopId = $coupon->getScShopId();
		$minimalCartValue = $coupon->getMinCart();
		
		//JEŚLI JEST KOD W SHOPCONNECTOR - TO SPRAWDZ CZY JEST JUZ W SYSTEMIE SKLEPU
		if(!empty($discountName)) { 
			$discountValue = (string)$coupon->getDiscountType();
			$discount = new CartRuleCore(intval(CartRuleCore::getIdByCode($discountName))); //PRESTA CODE
			
			//JEŚLI KOD NIE ISTNIEJE W SKLEPIE TO DODAJEMY GO, DZIĘKI TEMU MOŻE GO WYKORZYSTAĆ KLIENT
			if (is_object($discount) AND !$discount->id) {
				//PRESTA CODE
				if ($couponData === true){
					try{
						$object = new CartRuleCore();
						$object->id_customer = 0;
						$object->id_currency = 0;
						$object->id_discount_type = 1;
						$object->name = 'Discount from shopconnector: $discountName';
						$object->code = $discountName;
						$object->description = "Discount from partner site $discountName";
						$object->reduction_percent = $discountValue;
						$object->quantity = 1;
						$object->quantity_per_user = 1;
						$object->cumulable = false;
						$object->cumulable_reduction = false;
						$object->date_from = date("Y-m-d");
						$object->date_to = date("Y-m-d", time()+3600*24*365);
						$object->minimum_amount = floatval($minimalCartValue);
                                                $object->minimum_amount_tax = true;
						$object->active = 1;
						$object->cart_rule_restriction = 1;
						//$object->name = 'Discount from shopconnector: $discountName';
                                                $object->name = array(1 => "Discount from shopconnector: $discountName");
						$object->add(true, false);
					}catch(Exception $e) {
					  echo 'Message: ' .$e->getMessage();
					}
					$new_id =  $object->id;
					// good in older versions
//					$query='INSERT INTO `'._DB_PREFIX_.'cart_rule_lang` values('."$new_id".',1,"Discount from shopconnector")';
//					Db::getInstance()->execute($query);
					

				}
			}elseif ($couponData === true){
				$query='UPDATE `'._DB_PREFIX_."cart_rule` SET reduction_percent=$discountValue WHERE code='$discountName'";
				Db::getInstance()->execute($query);
			}
		}
		
		// pokaz niestandardowy blad
		if($couponData !== true){
			$this->errors[] = $couponData;
		}
		
		// setcookie("shopconnector_info_cookie_confirmed", "not_send", time()+3600*24, "/");

		//USTAWIAMY DANE KLIENTA DO COOKIE 
		if(!empty($discountName)) { 
			$sc_cookie['discount_coupon'] = $discountName;
			$sc_cookie['showPopup'] = $showPopup;
			$sc_cookie['showBanner'] = $showBanner;
			$sc_cookie['sendEmail'] = $sendEmail;
			$sc_cookie['scShopId'] = $scShopId;
			$cookieset = true;
		}
		if(defined("SC_CUSTOMER_FIRSTNAME") && trim(SC_CUSTOMER_FIRSTNAME) != "") { 
			$sc_cookie['firstname'] = SC_CUSTOMER_FIRSTNAME;
			$sc_cookie['lastname'] = SC_CUSTOMER_LASTNAME;
			$sc_cookie['email'] = SC_CUSTOMER_EMAIL; 
			$cookieset = true;
		}
		if(!empty($total_cart_price)) {
			$sc_cookie['cart_value'] = (string)$total_cart_price;
			$cookieset = true;
		}

		if(isset($cookieset) && $cookieset == true) setcookie("shopconnector_coupon", json_encode($sc_cookie), time()+3600*24, '/');
	}elseif(!isset($_COOKIE['shopconnector_coupon'])){

		$customer = new Customer($cart->id_customer); //DEFINICJA PODSTAWOWYCH DANYCH ZALOGOWANEGO UŻYTKOWNIKA - PRESTA CODE
		define("SC_CUSTOMER_FIRSTNAME", $customer->firstname);
		define("SC_CUSTOMER_LASTNAME", $customer->lastname);
		define("SC_CUSTOMER_EMAIL", $customer->email);

		if(defined("SC_CUSTOMER_EMAIL") && trim(SC_CUSTOMER_EMAIL) != "") { 
			$sc_cookie['firstname'] = SC_CUSTOMER_FIRSTNAME;
			$sc_cookie['lastname'] = SC_CUSTOMER_LASTNAME;
			$sc_cookie['email'] = SC_CUSTOMER_EMAIL; 
			$cookieset = true;
		}

		if($cookieset) setcookie("shop_user_info", json_encode($sc_cookie), time()+3600*24, '/');
	}

?>