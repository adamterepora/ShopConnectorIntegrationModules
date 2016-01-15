<?php

if(!defined('_PS_VERSION_'))
    exit;

class ShopConnectorModule extends Module {
    public function __construct()
    {
        $this->name = 'shopconnectormodule';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.5';
        $this->author = 'ShopConnector';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_); 
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Moduł ShopConnector dla PrestaShop');
        $this->description = $this->l('Integracja z platformą rabatową shopconnector.pl (walidacja kodów rabatowych, wyświetlanie personalizowanych pop-upów). Zdobędziesz nowych klientów z innych sklepów internetowych i nagrodzisz własną bazę klientów. Dowiesz się co Twoi klienci robią poza Twoim sklepem.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('SHOPCONNECTORMODULE_HASH'))      
          $this->warning = $this->l('No name provided');
    }
    
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name))
        {
            $my_module_name = strval(Tools::getValue('SHOPCONNECTORMODULE_HASH'));
            if (!$my_module_name
              || empty($my_module_name)
              || !Validate::isGenericName($my_module_name))
                $output .= $this->displayError($this->l('Niepoprawna konfiguracja sklepu lub brak hasha.'));
            else
            {
                Configuration::updateValue('SHOPCONNECTORMODULE_HASH', $my_module_name);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output.$this->displayForm();
    }
    
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Ustawienia Integracyjne'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Hash sklepu w Shopconnector'),
                    'name' => 'SHOPCONNECTORMODULE_HASH',
                    'size' => 20,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();
		
		if(!extension_loaded('mcrypt')){
			echo '<div style="padding: 20px; text-align: center; background-color: yellow; font-weight: bold;">Do poprawnego działania wtyczki ShopConnector wymagane jest zainstalowanie rozszerzenia PHP mcrypt.</div>';
		}

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['SHOPCONNECTORMODULE_HASH'] = Configuration::get('SHOPCONNECTORMODULE_HASH');

        return $helper->generateForm($fields_form);
    }
    
    public function install()
    {
        return parent::install() &&
            $this->registerHook('footer') && 
            $this->registerHook('displayOrderConfirmation');
    }
    
    public function uninstall()
    {
        if (
            !parent::uninstall() ||
            !Configuration::deleteByName('SHOPCONNECTORMODULE_HASH')
        )
            return false;
        return true;
    }
    
    public function hookDisplayOrderConfirmation(){
        $hash = Configuration::get('SHOPCONNECTORMODULE_HASH');
        $shopName = Configuration::get('PS_SHOP_NAME');
        include_once("check_coupon_client.php"); 
		include_once(_PS_ROOT_DIR_."/modules/shopconnectormodule/Mail.php");
        
        if(isset($_COOKIE['shopconnector_coupon_presta'])) {
		
		$cookieDecoded = $_COOKIE['shopconnector_coupon_presta'];
		$cookieDecoded = str_replace('&quot;','"',(string)$cookieDecoded);
		$cookieDecoded = str_replace('\"','"',(string)$cookieDecoded);
		$sc_cookie = json_decode($cookieDecoded);
		$discountName = $sc_cookie->discount_coupon;
		$cart_value = $sc_cookie->cart_value;
		$coupon = new CheckCouponClient($discountName);
		$couponData = $coupon->setCartValue($cart_value); 
		$couponData = $coupon->confirm($hash);
		$couponEmail= $coupon->getEmailTemplate();
		$sendEmail = $coupon->getSendEmail();

		if($couponData) {
			setcookie("shopconnector_info_cookie", "correct", time()+3600*24, "/");
			setcookie("shopconnector_info_cookie", "correct", time()+3600*24);
		}else{
			if($sendEmail == true){
				try {
					$from2 = array($shopName => "oszczedzaj@shopconnector.pl");
					$to2 = array($sc_cookie->firstname." ".$sc_cookie->lastname => $sc_cookie->email);
					$body2 = $couponEmail;
					$subject2 = "Następne zakupy w $shopName i innych sklepach mogą być tańsze!";
			
					$mail = new Mail2($body, $subject2, $from2, $to2);
					$mail->send();
					MailCore::Send(1, $subject2, $from2, '', $sc_cookie->email, $sc_cookie->firstname .' '.$sc_cookie->lastname, "oszczedzaj@shopconnector.pl", 'Shopconnector.pl');
				} catch (\Exception $e){
					$myfile = fopen("err.txt", "w") or die("Unable to open file!");
					$txt = $e->getMessage();
					fwrite($myfile, $txt);
					fclose($myfile);
				}
			}
			setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24, "/");
			setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24);
		}
		unset($_COOKIE['shopconnector_coupon_presta']);
		setcookie('shopconnector_coupon_presta', null, -1, '/');
		setcookie('shopconnector_coupon_presta', null, -1);
		if(isset($_COOKIE['shop_user_info'])){
			unset($_COOKIE['shop_user_info']);
			setcookie('shop_user_info', null, -1, '/');
			setcookie('shop_user_info', null, -1);
		}
		// setcookie("shopconnector_info_cookie_confirmed", "send", time()+3600*24, "/");
	} elseif(isset($_COOKIE['shopconnector_coupon'])) {
		$cookieDecoded = $_COOKIE['shopconnector_coupon'];
		$cookieDecoded = str_replace('&quot;','"',(string)$cookieDecoded);
		$cookieDecoded = str_replace('\"','"',(string)$cookieDecoded);
		$sc_cookie = json_decode($cookieDecoded);
		$discountName = $sc_cookie->discount_coupon;
		$showPopup = $sc_cookie->showPopup;
		$showBanner = $sc_cookie->showBanner;
		
		$scShopId = $sc_cookie->scShopId;
		$cart_value = $sc_cookie->cart_value;
		$coupon = new CheckCouponClient($discountName);
		$couponData = $coupon->setCartValue($cart_value); 
		$couponData = $coupon->confirm($hash);
		$couponEmail= $coupon->getEmailTemplate();
		$sendEmail = $coupon->getSendEmail();

		setcookie("scShopId", $scShopId, time()+3600*24, "/");
		setcookie("scShopId", $sscShopId, time()+3600*24);

		if($couponData) {
			setcookie("shopconnector_info_cookie", "correct", time()+3600*24, "/");
			setcookie("shopconnector_info_cookie", "correct", time()+3600*24);
			setcookie("showPopup", $showPopup, time()+3600*24, "/");
			setcookie("showPopup", $showPopup, time()+3600*24);
		}else{
			if($sendEmail){
				try {
					$from2 = array($shopName => "oszczedzaj@shopconnector.pl");
					$to2 = array($sc_cookie->firstname." ".$sc_cookie->lastname => $sc_cookie->email);
					$body2 = $couponEmail;
					$subject2 = "Następne zakupy w $shopName i innych sklepach mogą być tańsze!";
					$mail = new Mail2($body2, $subject2, $from2, $to2);
					$mail->send();
					MailCore::Send(1, $subject2, $from2, '', $sc_cookie->email, $sc_cookie->firstname .' '.$sc_cookie->lastname, "oszczedzaj@shopconnector.pl", 'Shopconnector.pl');
				} catch (\Exception $e){
					$myfile = fopen("err.txt", "w") or die("Unable to open file!");
					$txt = $e->getMessage();
					fwrite($myfile, $txt);
					fclose($myfile);
				}
			}
			setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24, "/");
			setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24);
			setcookie("showPopup", $showPopup, time()+3600*24, "/");
			setcookie("showPopup", $showPopup, time()+3600*24);
		}
		unset($_COOKIE['shopconnector_coupon']);
		setcookie('shopconnector_coupon', null, -1, '/');
		setcookie('shopconnector_coupon', null, -1);
		if(isset($_COOKIE['shop_user_info'])){
			unset($_COOKIE['shop_user_info']);
			setcookie('shop_user_info', null, -1, '/');
			setcookie('shop_user_info', null, -1);
		}
		// setcookie("shopconnector_info_cookie_confirmed", "send", time()+3600*24, "/");
	}elseif(isset($_COOKIE['shop_user_info'])) {
	
		$cookieDecoded = $_COOKIE['shop_user_info'];
		$cookieDecoded = str_replace('&quot;','"',(string)$cookieDecoded);
		$cookieDecoded = str_replace('\"','"',(string)$cookieDecoded);
		$sc_cookie = json_decode($cookieDecoded);
		$coupon = new CheckCouponClient('empty');
		$couponData = $coupon->setCartValue("0"); 
		$couponData = $coupon->confirm($hash);
		$couponEmail= $coupon->getEmailTemplate();
		$sendEmail = $coupon->getSendEmail();
                
		if($sendEmail){
			try {
				$from2 = array($shopName => "oszczedzaj@shopconnector.pl");
				$to2 = array($sc_cookie->firstname." ".$sc_cookie->lastname => $sc_cookie->email);
				$body2 = $couponEmail;
				$subject2 = "Następne zakupy w $shopName i innych sklepach mogą być tańsze!";
				$mail = new Mail2($body2, $subject2, $from2, $to2);
				$mail->send();
				MailCore::Send(1, $subject2, $from2, '', $sc_cookie->email, $sc_cookie->firstname .' '.$sc_cookie->lastname, "oszczedzaj@shopconnector.pl", 'Shopconnector.pl');
			} catch (\Exception $e){
				$myfile = fopen("err.txt", "w") or die("Unable to open file!");
				$txt = $e->getMessage();
				fwrite($myfile, $txt);
				fclose($myfile);
			}
		}
                
		unset($_COOKIE['shop_user_info']);
		setcookie('shop_user_info', null, -1, '/');
		setcookie('shop_user_info', null, -1);

		setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24, "/");
		setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24);
	}else{
            
                $coupon = new CheckCouponClient('empty');
		$couponData = $coupon->setCartValue("0"); 
		$couponData = $coupon->confirm($hash);
		$couponEmail= $coupon->getEmailTemplate();
		$sendEmail = $coupon->getSendEmail();
                
                $cart = Context::getContext()->cart;
		$customer = new Customer($cart->id_customer); //DEFINICJA PODSTAWOWYCH DANYCH ZALOGOWANEGO UŻYTKOWNIKA - PRESTA CODE
                
		if($sendEmail){
			try{
				$from2 = array($shopName => "oszczedzaj@shopconnector.pl");
				$to2 = array($customer->firstname." ".$customer->lastname => $customer->email);
				$subject2 = "Następne zakupy w $shopName i innych sklepach mogą być tańsze!";
				$mail = new Mail2($couponEmail, $subject2, $from2, $to2);
				$mail->send();
				Mail::Send(1, $subject2, $from2, '', $sc_cookie->email, $sc_cookie->firstname .' '.$sc_cookie->lastname, "oszczedzaj@shopconnector.pl", 'Shopconnector.pl');
			} catch (\Exception $e){
				$myfile = fopen("err.txt", "w") or die("Unable to open file!");
				$txt = $e->getMessage();
				fwrite($myfile, $txt);
				fclose($myfile);
			}
		}
                
                setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24, "/");
		setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24);
	}
        
        $this->context->smarty->assign(
            array(
                'mess' => '',
            )
        );
        return $this->display(__FILE__, 'displayorderconfirmation.tpl');
    }
    
    public function hookDisplayFooter()
    {
        $js = '<script type="text/javascript" src="https://shopconnector.pl/js/banner_shopconnector.js"></script>
<script type="text/javascript" src="//shopconnector.pl/js/shopconnector.js"></script>
';
        $this->context->smarty->assign(
            array(
                'extra' => $js,
            )
        );
        return $this->display(__FILE__, 'footer.tpl');
    }
}