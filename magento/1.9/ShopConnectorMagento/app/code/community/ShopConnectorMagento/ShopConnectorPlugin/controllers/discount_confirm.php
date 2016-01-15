<?php
	define("HASH", Mage::getStoreConfig('shopconnectorplugin/shopconnectormagento_group/shopconnectormagento_input',Mage::app()->getStore())); 
	@session_start();
	include_once("Mail.php");
	include_once("check_coupon_client.php"); 

	function sendZendMail($name,$email,$subject,$content){
		$shopName = Mage::app()->getStore()->getName();
	    $mail = new Zend_Mail('UTF-8');
	    $mail->setBodyHtml($content);
	    $mail->setFrom('oszczedzaj@shopconnector.pl', $shopName);
	    $mail->addTo($email, $name);
	    $mail->setSubject($subject);
		try {
		    $mail->send();
		    Mage::getSingleton('core/session')->addSuccess('Wysłano maila z informacją o programie partnerskim.');
		}
		catch (Exception $e) {
		    Mage::getSingleton('core/session')->addError('Nie udało się wysłać maila z informacją o programie partnerskim.');
		}
	}

	function sendInfoMail($body, $lastOrderId){
		$user = Mage::getSingleton('customer/session')->getCustomer();
		$lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
		
		$order = Mage::getModel("sales/order")->load($lastOrderId);
		$billingAddress = $order->getBillingAddress();
		$shopName = Mage::app()->getStore()->getName();
		
		if(!empty($billingAddress)) {
			$email = $billingAddress->getEmail();
			$name = $billingAddress->getFirstname();
			$lastname = $billingAddress->getLastname();
			
			sendZendMail("$name $lastname",$email,"Następne zakupy w $shopName i innych sklepach mogą być tańsze!",$body);
		}
	}
	
	function sendMagentoEmail($template, $subject, $lastOrderId){
		//Getting the Store E-Mail Sender Name.
		$senderName = Mage::getStoreConfig('trans_email/ident_general/name');

		//Getting the Store General E-Mail.
		$senderEmail = Mage::getStoreConfig('trans_email/ident_general/email');


		//Appending the Custom Variables to Template.
		//$processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
		$processedTemplate = $template;

		$user = Mage::getSingleton('customer/session')->getCustomer();
		$lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
		
		$order = Mage::getModel("sales/order")->load($lastOrderId);
		$billingAddress = $order->getBillingAddress();
		$shopName = Mage::app()->getStore()->getName();
		
		if(empty($billingAddress)) {
			return false;
		}
		
		$email = $billingAddress->getEmail();
		$name = $billingAddress->getFirstname();
		$lastname = $billingAddress->getLastname();
			
			//sendZendMail("$name $lastname",$email,"Następne zakupy w $shopName i innych sklepach mogą być tańsze!",$body);
		$subject = str_replace('%s', $shopName, $subject);

		//Sending E-Mail to Customers.
		//$mail = Mage::getModel('core/email')
		$mail = new Zend_Mail('UTF-8');
		$mail->setBodyHtml($processedTemplate);
		$mail->setFrom($senderEmail, $senderName);
		$mail->addTo($email, $name. ' '. $lastname);
		$mail->setSubject($subject);
		//$mail->setToName($name. ' '. $lastname)
		//	 ->setToEmail($email)
		//	 ->setBody($processedTemplate)
		//	 ->setSubject($subject)
		//	 ->setFromEmail($senderEmail)
		//	 ->setFromName($senderName)
		//	 ->setType('html');
		try{
			//Confimation E-Mail Send
			$mail->send();
		}
		catch(Exception $error)
		{
			Mage::getSingleton('core/session')->addError($error->getMessage());
			return false;
		}
	}

	if(isset($_COOKIE['shopconnector_coupon'])) {
		$cookieDec = json_decode($_COOKIE['shopconnector_coupon']);
		$discountName = $cookieDec-> discount_coupon;
		$cart_value =  $cookieDec-> cart_value;
		$coupon = new CheckCouponClient($discountName);
		$coupon->setCartValue($cart_value); 
		$couponData = $coupon->confirm(HASH);
		$showPopup = $coupon->getShowPopup();
		$showBanner = $coupon->getShowBanner();
		$sendEmail = $coupon->getSendEmail();
		$scShopId = $coupon->getScShopId();
		setcookie("showPopup", $showPopup, time()+3600*24, "/");
		setcookie("showBanner", $showBanner, time()+3600*24, "/");
		setcookie("scShopId", $scShopId, time()+3600*24, "/");
		//gdy nie mozna bylo potwierdzic kuponu
		if (!$couponData){
			$body = $coupon->getEmailTemplate();
			$coupon = Mage::getModel('salesrule/coupon')->load($discountName, 'code');
			$ress = $coupon->getRuleId();
			//JEĹšLI KOD ISTNIEJE W SKLEPIE TO GO USUWAMY
			if (!empty($ress)) {
				$model = Mage::getModel('salesrule/rule')
		        ->getCollection()
		        ->addFieldToFilter('code', $discountName)
		        ->getFirstItem();
				$model->delete();
			}
			
			if($sendEmail == true){
				sendMagentoEmail($body, 'Następne zakupy w sklepie %s mogą być tańsze!', $lastOrderId);
			}
			
			//sendInfoMail($body, $lastOrderId);
			Mage::getSingleton('core/session')->addError('Podany kod został już wykorzystany.');
			setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24, "/");
			//setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24);
		}else{
			setcookie("shopconnector_info_cookie", "correct", time()+3600*24, "/");
			//setcookie("shopconnector_info_cookie", "correct", time()+3600*24);
		}
	    unset($_COOKIE['shopconnector_coupon']);
	    setcookie('shopconnector_coupon', null, -1, '/');
	    return true;
	}else{
		setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24, "/");
		//setcookie("shopconnector_info_cookie", "unknownUser", time()+3600*24);
		$coupon = new CheckCouponClient('empty');
		$coupon->setCartValue(0); 
		$couponData = $coupon->confirm(HASH);
		$showPopup = $coupon->getShowPopup();
		$showBanner = $coupon->getShowBanner();
		$sendEmail = $coupon->getSendEmail();
		$scShopId = $coupon->getScShopId();

		setcookie("showPopup", $showPopup, time()+3600*24, "/");
		setcookie("showBanner", $showBanner, time()+3600*24, "/");
		//setcookie("sendEmail", $sendEmail, time()+3600*24, "/");
		setcookie("scShopId", $scShopId, time()+3600*24, "/");
		
		
		$body = ($coupon->getEmailTemplate());
		//sendInfoMail($body);
		if($sendEmail == true){
			sendMagentoEmail($body, 'Następne zakupy w sklepie %s mogą być tańsze!', $lastOrderId);
		}
	}
?>