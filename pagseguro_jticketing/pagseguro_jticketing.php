<?php

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
if(JVERSION >='1.6.0')
	require_once(JPATH_SITE.'/plugins/payment/pagseguro_jticketing/pagseguro_jticketing/helper.php');
else
	require_once(JPATH_SITE.'/plugins/payment/pagseguro_jticketing/helper.php');


class  plgPaymentPagseguro_jticketing extends JPlugin
{

	function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		//Set the language in the class
		$config =& JFactory::getConfig();

				/*
		1	Waiting for payment : the buyer initiated the transaction, but so far the PagSeguro not received any payment information.
		2	In Review : The buyer chose to pay with a credit card and PagSeguro is analyzing the risk of the transaction.
		3	Pay : the transaction was paid by the buyer and PagSeguro already received a confirmation from the financial institution responsible for processing.
		4	Available : the transaction was paid and reached the end of their period of release and returned without having been without any dispute opened.
		5	In dispute : the buyer, the deadline for release of the transaction, opened a dispute.
		6	Returned : The value of the transaction was returned to the buyer.
		7	Canceled : the transaction was canceled without having been finalized.
		*/
		//Define Payment Status codes in Pagseguro  And Respective Alias in Framework
		$this->responseStatus= array(
 	'1'=>'P',
 	'2'=>'UR',
 	'3'=>'C',
  '4'=>'RV',
  '5'=>'DP',
  '6'=>'RT',
  '7'=>'D',
		);
	}

	/* Internal use functions */
	function buildLayoutPath($layout) {
		$app = JFactory::getApplication();
		$core_file 	= dirname(__FILE__).DS.$this->_name.DS.'tmpl'.DS.'default.php';
		$override		= JPATH_BASE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'plugins'.DS.$this->_type.DS.$this->_name.DS.$layout.'.php';
		if(JFile::exists($override))
		{
			return $override;
		}
		else
		{
	  	return  $core_file;
	}
	}

	//Builds the layout to be shown, along with hidden fields.
	function buildLayout($vars, $layout = 'default' )
	{
		// Load the layout & push variables
		ob_start();
        $layout = $this->buildLayoutPath($layout);
        include($layout);
        $html = ob_get_contents();
        ob_end_clean();
		return $html;
	}

	// Used to Build List of Payment Gateway in the respective Components
	function onTP_GetInfo($config)
	{

	if(!in_array($this->_name,$config))
	return;
		$obj 		= new stdClass;
		$obj->name 	=$this->params->get( 'plugin_name' );
		$obj->id	= $this->_name;
		return $obj;
	}

	//Constructs the Payment form in case of On Site Payment gateways like Auth.net & constructs the Submit button in case of offsite ones like Pagseguro
	function onTP_GetHTML($vars)
	{

		if(!strstr($vars->client,'jticketing'))
		return;
		require_once JPATH_SITE.'/plugins/payment/pagseguro_jticketing/lib/PagSeguroLibrary.php';

		$vars->sellar_email = $this->params->get('sellar_email');
		$vars->token = $this->params->get('token');
		//$vars->order_id=$vars->client.'__'.$vars->order_id;
		$vars->action_url = plgPaymentPagseguro_jticketingHelper::buildPagseguroUrl($vars,1);


		//Take this receiver email address from plugin if component not provided it


		$html = $this->buildLayout($vars);

		return $html;
	}



	function onTP_Processpayment($data)
	{

		require_once JPATH_SITE.'/plugins/payment/pagseguro_jticketing/lib/PagSeguroLibrary.php';

		$vars->sellar_email = $this->params->get('sellar_email');
		$vars->token = $this->params->get('token');
		$verified_Data = plgPaymentPagseguro_jticketingHelper::validateIPN($data,$vars);

		//$order_idstr=explode('__',$verified_Data['order_id']);
		//$verified_Data['order_id']=$order_idstr['1'];


		$pstatus=$verified_Data['payment_statuscode'];
		$status=$this->translateResponse($pstatus);
		if(!$status)
		$status='P';


		$result = array(
						'order_id'=>$verified_Data['order_id'],
						'transaction_id'=>$verified_Data['transaction_id'],
						'buyer_email'=>$verified_Data['buyer_email'],
						'status'=>$status,
						'txn_type'=>$verified_Data['payment_method'],
						'total_paid_amt'=>$verified_Data['total_paid_amt'],
						'raw_data'=>$verified_Data['raw_data'],
						'error'=>$error,
						);

		return $result;
	}

	function translateResponse($payment_status){
			foreach($this->responseStatus as $key=>$value)
			{
				if($key==$payment_status)
				return $value;
			}
	}
	function onTP_Storelog($data)
	{
			//$log = plgPaymentPagseguro_jticketingHelper::Storelog($this->_name,$data);

	}
}
