<?php

$websiteId = Mage::app()->getStore()->getWebsiteId();

if(!extension_loaded('mcrypt')){
	Mage::getSingleton('core/session')->addNotice('Do poprawnego działania wtyczki ShopConnector wymagane jest zainstalowanie rozszerzenia PHP mcrypt');
}

define("HASH", Mage::getStoreConfig('shopconnectorplugin/shopconnectormagento_group/shopconnectormagento_input',Mage::app()->getStore())); //UNIKALNY HASH KAŻDEGO PARTNERA

$cookieset = false;

$sc_cookie = json_decode(stripslashes(html_entity_decode($_COOKIE['shopconnector_coupon'])), true);

if(!is_array($sc_cookie)) $sc_cookie = array();



include_once("check_coupon_client.php");



$total_cart_price = Mage::getSingleton('checkout/cart')->getQuote()->getGrandTotal();

$discountName = (string) $this->getRequest()->getParam('coupon_code'); //POBRANIE KODU Z GET LUB POST

if ($this->getRequest()->getParam('remove') == 1){

	$deleteEvent=true;

}else{

	$deleteEvent=false;

}

$user = Mage::getSingleton('customer/session')->getCustomer();

if(!empty($user)) {

define("SC_CUSTOMER_FIRSTNAME", $user->getFirstname());

define("SC_CUSTOMER_LASTNAME", $user->getLastname());

define("SC_CUSTOMER_EMAIL", $user->getEmail());



}

//pobranie wartości z koszyka

$total_cart_price = Mage::getSingleton('checkout/cart')->getQuote()->getGrandTotal();

//SPRAWDZENIE ISTNIENIA KODU W SHOPCONNECTOR

$coupon = new CheckCouponClient($discountName); 

//wysyłanie wartości koszyka aby sprawdzić czy jest wyższa niż minimalna wartość zakupów

$coupon->setCartValue($total_cart_price);

//sprawdzenie kuponu w shopconnector

$couponCorrect= $coupon->check(HASH);

if($couponCorrect !== true){
	Mage::getSingleton('core/session')->addNotice($couponCorrect);
}

$myfile = fopen("cokolwiek.txt", "w") or die("Unable to open file!");
            $txt = "i tu tez dziala\n";
            fwrite($myfile, $txt);
            fclose($myfile);

//JEŚLI JEST KOD W SHOPCONNECTOR - TO SPRAWDZ CZY JEST JUZ W SYSTEMIE SKLEPU

if(!empty($discountName) && $couponCorrect === true) { 



	//usuwam ze sklepu

	if ($deleteEvent){

	    $coupon = Mage::getModel('salesrule/coupon')->load($discountName, 'code');

		$ress = $coupon->getRuleId();

		

		//JEŚLI KOD ISTNIEJE W SKLEPIE TO GO USUWAMY

		if (!empty($ress)) {

			// $coupon->delete();

			/*$model = Mage::getModel('salesrule/rule')

	        ->getCollection()

	        ->addFieldToFilter('code', $discountName)

	        ->getFirstItem();

			$model->delete();*/

		}

	//dodaje do sklepu

	}else{

		$discountValue = (string)$coupon->getDiscountType();

		$coupon = Mage::getModel('salesrule/coupon')->load($discountName, 'code');

		$ress = $coupon->getRuleId();

		

		//JEŚLI KOD NIE ISTNIEJE W SKLEPIE TO DODAJEMY GO, DZIĘKI TEMU MOŻE GO WYKORZYSTAĆ KLIENT

		if (empty($ress)) {

			$data = array(

		  "product_ids"=> "",

		  "name"=> "Discount from Shopconnector.pl",

		  "description"=> "",

		  "is_active"=> "1",

		  "website_ids"=>  array(0=> $websiteId),

		  "customer_group_ids"=>

			  array(

				0=> "0",

				1=> "1",

				2=> "2",

				3=> "3"

			  ),

		  "coupon_type"=> "2",

		  "coupon_code"=> $discountName,

		  "uses_per_coupon"=> "1",

		  "uses_per_customer"=> "1",

		  "from_date"=> date("d/m/Y"),

		  "to_date"=> date("d/m/Y", time()+3600*24*365),

		  "sort_order"=> "",

		  "is_rss"=> "1",

		  "rule"=>

			  array(

				"conditions"=>

					array(

					  1=>

					  array(

						"type"=> "salesrule/rule_condition_combine",

						"aggregator"=> "all",

						"value"=> "1",

						"new_child"=> ""

					  )

					),

				"actions"=>

					array(

					  1=>

					  array(

						"type"=> "salesrule/rule_condition_product_combine",

						"aggregator"=> "all",

						"value"=> "1",

						"new_child"=> ""

					  )

					)

			  ),

		  "simple_action"=> "by_percent",

		  "discount_amount"=> $discountValue,

		  "discount_qty"=> "0",

		  "discount_step"=> "",

		  "apply_to_shipping"=> "0",

		  "simple_free_shipping"=> "0",

		  "stop_rules_processing"=> "0",

		  "store_labels"=>

			  array(

				0=> "",

				1=> ""

			  )

			);

			$model = Mage::getModel('salesrule/rule');



			if (isset($data['simple_action']) && $data['simple_action'] == 'by_percent'

			&& isset($data['discount_amount'])) {

				$data['discount_amount'] = min(100,$data['discount_amount']);

			}

			if (isset($data['rule']['conditions'])) {

				$data['conditions'] = $data['rule']['conditions'];

			}

			if (isset($data['rule']['actions'])) {

				$data['actions'] = $data['rule']['actions'];

			}

			unset($data['rule']);

			$model->loadPost($data);



			$useAutoGeneration = (int)!empty($data['use_auto_generation']);

			$model->setUseAutoGeneration($useAutoGeneration);

			$model->save();

		}else{

			$resource = Mage::getSingleton('core/resource');

            $readConnection = $resource->getConnection('core_read');

            $writeConnection = $resource->getConnection( 'core_write' );

            $tableName = $resource->getTableName('salesrule/coupon');

			$websiteTable = $resource->getTableName('salesrule/website');
			
            // echo $tableName;
			
            $query = "SELECT rule_id FROM $tableName WHERE code = '$discountName'";

            $result = $readConnection->query($query);

            $l = '';

			while ( $row = $result->fetch() ) {

				$l = $row['rule_id'];

			}
			
			// delete all relations with website ids
			$q = "DELETE FROM $websiteTable where rule_id = $l";
			$r = $readConnection->query($q);
			
			$q2 = "INSERT INTO $websiteTable (rule_id, website_id) VALUES ($l, $websiteId)";
			$r2 = $readConnection->query($q2);
			
			// $discountValue=12;

			$tableName2 = $resource->getTableName('salesrule');

			$query2 = "UPDATE $tableName2 set discount_amount = '$discountValue' where rule_id = '$l'";

			$writeConnection->query($query2);

		}

	}

}



//USTAWIAMY DANE KLIENTA DO COOKIE 

if(!empty($discountName)) { 

	if ($deleteEvent)

		$sc_cookie['discount_coupon'] = '';

	else

		$sc_cookie['discount_coupon'] = $discountName;

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



if($cookieset) setcookie("shopconnector_coupon", json_encode($sc_cookie), time()+3600*24, "/");



?>