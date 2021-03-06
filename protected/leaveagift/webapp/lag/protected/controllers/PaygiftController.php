<?

require_once(dirname(__FILE__).'/../extensions/runactions/components/ERunActions.php');
set_include_path('/'.PATH_SEPARATOR.get_include_path());
require_once('CitrusPay.php');
require_once('Zend/Crypt/Hmac.php');


class PaygiftController extends AuthController{

public $layout="main";



public function actionPay()
{
   $session=new CHttpSession;
   $session->open();
   
	$userorder = UserOrder::model()->findbyPk(Yii::app()->session['id_userorder']);
	$id_product=$userorder->id_product;
	CitrusPay::setApiKey("09dc9523bb12f33fac4738a632f32e9b15ccde3e",'production');
	$model=Product::model()->findbyPk($id_product);
	
	
	$criteria=new CDbCriteria;
	$criteria->condition="id_user=:id_user";
	$criteria->params=array(":id_user"=>$session['logged-userid']);
	
	$userdetail= UserAddress::model()->find($criteria);
	
	//echo $userdetail->state;
	// echo $session['logged-userid'];
	// exit;
	
	$orderAmount=$model->price;
	//$orderAmount='1';
	$vanityUrl = "leaveagift";
	$currency = "INR";
	$merchantAccessKey = 'DUOX0J8URN9N9J63IUS5';
	$merchantTxnId = Yii::app()->session['id_userorder'];
	$addressState = $userdetail->state ;
	$addressCity = 	$userdetail->city;
	$addressStreet1= $userdetail->address1;
	$addressCountry = "INDIA";
	$addressZip = $userdetail->postcode;
	$firstName =$userdetail->firstname;
	$lastName=$userdetail->lastname;
	$phoneNumber=$userdetail->phone;
	$email = $userdetail->email;
	$returnUrl = "https://www.leaveagift.com/paygift/return/";
	$flag = "post";
	$data = "$vanityUrl$orderAmount$merchantTxnId$currency";
	$secSignature = Zend_Crypt_Hmac::compute(CitrusPay::getApiKey(), "sha1", $data);
	//$secSignature = generateHmacKey($data,CitrusPay::getApiKey());
	$action = CitrusPay::getCPBase()."$vanityUrl";  
	$time = time()*1000;
	$time = number_format($time,0,'.','');
	$command = Yii::app()->db->createCommand();
    	$command->insert('lag_payment', array('id_order'=>$merchantTxnId,'TxStatus'=>'IN PAYMENT'));

	$this->render('2',
           array(
		        'action'=>$action,
		        'merchantAccessKey'=>$merchantTxnId,
		        'orderAmount'=>$orderAmount,
		        'returnUrl'=>$returnUrl,
		        'time'=>$time,
		        'secSignature'=>$secSignature,
		        'currency'=>$currency,
		        'firstName'=>$firstName,
			'lastName'=>$lastName,
			'email'=>$email,
			'phoneNumber'=>$phoneNumber,
			'addressState'=>$addressState,
			'addressCity'=>$addressCity,
			'addressStreet1'=>$addressStreet1,
			'addressCountry'=>$addressCountry,
			'addressZip'=>$addressZip,
			'flag'=>$flag,
			'merchantTxnId'=>$merchantTxnId,
			//'YII_CSRF_TOKEN' => Yii::app()->request->csrfToken,
					)
			);



}

public function actionReturn()
{
	/*
	echo "<pre>";
  	print_r($_REQUEST);	
 	echo "</pre>";
	*/
	
	$id_order=$_REQUEST['TxId'];
	//$_SESSION['YII_CSRF_TOKEN']=$_REQUEST['customParams[0]'][value'];
	$date = new DateTime();
	$datenow=$date->getTimestamp();
	$txStatus=$_REQUEST['TxStatus'];
	   $userorder = UserOrder::model()->findbyPk($id_order);
	   $id_product=$userorder->id_product;
	   $modelproduct=Product::model()->findbyPk($id_product);
	   $command_payment = Yii::app()->db->createCommand();
	   $command_payment->update('lag_payment', array(
						'pgTxnNo'=>$_REQUEST['pgTxnNo'],
						'TxRefNo'=>$_REQUEST['TxRefNo'],
						'TxMsg'=>$_REQUEST['TxMsg'],
						'TxStatus'=>$_REQUEST['TxStatus'],
						'amount'=>$_REQUEST['amount'],
						'endtime'=>$datenow
						),
					'id_order=:id_order',
					 array(
						':id_order'=>$id_order
						)
			);
	$command_user = Yii::app()->db->createCommand();

	$command_user->update('ps_address', array(
							'firstname'=>$_REQUEST['firstName'],
							'lastname'=>$_REQUEST['lastName'],
							'phone'=>$_REQUEST['mobileNo'],
							'state'=>$_REQUEST['addressState'],
							'city'=>$_REQUEST['addressCity'],
							'address1'=>$_REQUEST['addressStreet1'],
							'email'=>$_REQUEST['email'],
							'postcode'=>$_REQUEST['addressZip'],


							),
				'id_user=:id_user',array(
							':id_user'=>Yii::app()->session['logged-userid']
								)
				);


if($_REQUEST['TxStatus']=='SUCCESS')
{


if(date('d',strtotime($userorder->delivery_date)) ==date('d',time()))
		{
			$this->postToFacebook(Yii::app()->session['id_userorder']);
			$this->sendMail(Yii::app()->session['id_userorder']);
		}

	$command_order = Yii::app()->db->createCommand();
	$command_order->update('lag_user_order', array(
							'order_status'=>$_REQUEST['TxStatus']
							),
				'id_user_gift=:id_user_gift',array(
							':id_user_gift'=>$id_order
								)
				);


//-- add voucher start---//


		$userorder = UserOrder::model()->findbyPk($id_order);
   		$id_product=$userorder->id_product;

                $criteria= new CDbCriteria;
                $criteria->condition="id_product=:id_product and status=:status";
                $criteria->limit="1";
                $criteria->params=array(':id_product'=>$id_product,':status'=>'1');		
 		$voucherCode = Voucher::model()->find($criteria);
/*
		if(count($voucherCode) == 0)
			{
				$this->redirect('auth/error');
			}
*/
		$voucherOrderCheck = VoucherOrder::model()->findbypk($id_order);
		if($voucherOrderCheck== null)
			{
				$VoucherOrder=new VoucherOrder;
				$VoucherOrder->id_voucher=$voucherCode->id;
				$VoucherOrder->id_order=$id_order;
				$VoucherOrder->validity=$voucherCode->validity;
				$VoucherOrder->save(false);
			}

		$command_voucher = Yii::app()->db->createCommand();
		$command_voucher->update('lag_vouchers', array(
							'status'=>'2',
							),
							'id=:id',array(
							':id'=>$voucherCode->id
								)
				);
		
		$voucherCodevalue=$voucherCode->voucher_code;
/*
		$quantity=$modelproduct->quantity;
		$quantity=$quantity-1;
		$command_product = Yii::app()->db->createCommand();
		$command_order->update('ps_product', array(
						'quantity'=>$quantity
						),
						'id_product=:id_product',array(
								':id_product'=>$id_product,
								)
			);
*/

//--add voucher ends--//

// to send email after payment

//--start email--//

	$url= Yii::app()->params['sendgridurl'];
	$user= Yii::app()->params['usersendgrid'];
	$pass= Yii::app()->params['passsendgrid'];
	$params = array(
	    'api_user'  => $user,
	    'api_key'   => $pass,
	    'to'        => $_REQUEST['email'],
	    'subject'   => "Thank you for Spreading Happiness",
	    'html'      => $this->renderPartial('mail',array(
							'firstname'=>$_REQUEST['firstName'],
							'lastname'=>$_REQUEST['lastName'],
							'phone'=>$_REQUEST['mobileNo'],
							'state'=>$_REQUEST['addressState'],
							'city'=>$_REQUEST['addressCity'],
							'address1'=>$_REQUEST['addressStreet1'],
							'email'=>$_REQUEST['email'],
							'postcode'=>$_REQUEST['addressZip'],
							'TxMsg'=>$_REQUEST['TxMsg'],
							'TxStatus'=>$_REQUEST['TxStatus'],
							'amount'=>$_REQUEST['amount'],
							'id_order'=>$id_order,),true),
	    //'html'      => $this->widget('ReceiverGiftCard',array('id_user_gift'=>4,'email_view'=>true)),
	    'text'      => '',
	    'from'      => 'donotreply@leaveagift.com',
	  );
 
// message body ends


 
$this->sendercurl($url,$params);



//--end email--//

   	$this->render("confirmation",array('userorder'=>$userorder));
}

else{

	$command_order = Yii::app()->db->createCommand();
	$command_order->update('lag_user_order', array(
							'order_status'=>$_REQUEST['TxStatus']
							),
				'id_user_gift=:id_user_gift',array(
							':id_user_gift'=>$id_order
								)
				);

	$this->render('3',
           array(
		'pgTxnNo'=>$_REQUEST['pgTxnNo'],
		'TxRefNo'=>$_REQUEST['TxRefNo'],
		'TxMsg'=>$_REQUEST['TxMsg'],
		'TxStatus'=>$_REQUEST['TxStatus'],
		'amount'=>$_REQUEST['amount'],
					)
			);
	$quantity=$modelproduct->quantity;
	$quantity=$quantity+1;
	$command_product = Yii::app()->db->createCommand();
	$command_order->update('ps_product', array(
					'quantity'=>$quantity
					),
					'id_product=:id_product',array(
							':id_product'=>$id_product,
							)
		);

	}



}

private function sendMail($id_user_gift) 
 {
    $commandPath = Yii::app()->getBasePath() . DIRECTORY_SEPARATOR . 'commands';
    $runner = new CConsoleCommandRunner();
    $runner->addCommands($commandPath);
    $commandPath = Yii::getFrameworkPath() . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'commands';
    $runner->addCommands($commandPath);
    $args = array('yiic', 'sendmail', '--type=instant', '--id='.$id_user_gift);
	ob_start();
    $runner->run($args);
    echo htmlentities(ob_get_clean(), null, Yii::app()->charset);
}

private function postToFacebook($id_user_gift) 
 {
    $commandPath = Yii::app()->getBasePath() . DIRECTORY_SEPARATOR . 'commands';
    $runner = new CConsoleCommandRunner();
    $runner->addCommands($commandPath);
    $commandPath = Yii::getFrameworkPath() . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'commands';
    $runner->addCommands($commandPath);
    $args = array('yiic', 'postfacebook', '--id='.$id_user_gift);
	ob_start();
    $runner->run($args);
    echo htmlentities(ob_get_clean(), null, Yii::app()->charset);
}

public function generateHmacKey($data, $apiKey=null){
	$hmackey = Zend_Crypt_Hmac::compute($apiKey, "sha1", $data);
	return $hmackey;  
}



private function sendercurl($url,$params)
{
$request =  $url.'api/mail.send.json';
// Generate curl request
$session = curl_init($request);
// Tell curl to use HTTP POST
curl_setopt ($session, CURLOPT_POST, true);
// Tell curl that this is the body of the POST
curl_setopt ($session, CURLOPT_POSTFIELDS, $params);
// Tell curl not to return headers, but do return the response
curl_setopt($session, CURLOPT_HEADER, false);
curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
 
// obtain response
$response = curl_exec($session);
curl_close($session);
//echo $response."- mail is send";
}

}



?>
