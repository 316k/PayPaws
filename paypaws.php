<?php
/**
 * Item sold via paypal.
 * @author Nicolas Hurtubise <nicolas.k.hurtubise@gmail.com>
 */
class PayPalItem {

    private $name;
    private $description;
    private $amount;
    private $quantity;

    /**
     * Creates a new PayPalItem
     * 
     * @param string $name Item name (truncated to 127 chars)
     * @param string $description Item description (truncated to 127 chars)
     * @param float $amount Item unit price
     * @param int $quantity Quantity of items bought (positive integer)
     */
    public function PayPalItem($name, $description, $amount, $quantity) {
        $this->name = substr($name, 0, 127);
        $this->description = $description;
        $this->amount = $amount;
        $this->quantity = $quantity;
    }

    /**
     * @return int Total price for the item (amount * quantity)
     */
    public function total() {
        return $this->amount * $this->quantity;
    }

    /**
     * Gives the item's parameters as array
     * 
     * @param integer $item_number
     * @return array parameters
     */
    public function compile($item_number) {
        return array('L_PAYMENTREQUEST_0_NAME' . $item_number => $this->name,
            'L_PAYMENTREQUEST_0_DESC' . $item_number => $this->description,
            'L_PAYMENTREQUEST_0_AMT' . $item_number => $this->amount,
            'L_PAYMENTREQUEST_0_QTY' . $item_number => $this->quantity,
        );
    }

}

/**
 * Paypal transaction class. I based my first sketch on the tutorial available on the page 
 * mentionned below. (It evolved a >lot< since then. It's now a lot more simple than it was).
 * @link http://coding.smashingmagazine.com/2011/09/05/getting-started-with-the-paypal-api/
 * @author Nicolas Hurtubise <nicolas.k.hurtubise@gmail.com>
 * @todo Deal with shipping, taxes
 */
class PayPal {

    /**
     * Currency used for the transactions
     * @var string Currency code
     * @link https://developer.paypal.com/webapps/developer/docs/classic/api/currency_codes/
     * Note that there is a maximum amount per transaction defined by currency (e.g. 10000.00 for USD, 12500.00 for CAD)
     */
    protected $currency = 'CAD';

    /**
     * API endpoint
     * Live - https://api-3t.paypal.com/nvp
     * Sandbox - https://api-3t.sandbox.paypal.com/nvp
     * @var string
     */
    protected $end_point = 'https://api-3t.paypal.com/nvp';

    /**
     * Paypal URL
     * Live - https://www.paypal.com/webscr?cmd=_express-checkout&token=
     * Sandbox - https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=
     * @var string
     */
    protected $url = 'https://www.paypal.com/webscr?cmd=_express-checkout&token=';

    /**
     * API Version
     * @var string
     */
    protected $version = '98.0';

    /**
     * Visual elements for the paypal confirmation page
     * 
     * @var array string 
     */
    protected $visual = array();

    /**
     * Indicates if InstantPaymentOnly will be passed during a
     * SetExpressCheckout operation
     * 
     * @var boolean
     */
    protected $instant_payment_only = false;

    /**
     * Credentials to use during requests.
     * 
     * @var array string
     */
    protected $credentials = array();

    /**
     * Constructor
     * 
     * @param array String $credentials Credentials to use during requests.
     * This array must contain three fields : 'USER', 'PWD' and 'SIGNATURE'.
     * See the link below for more details.
     * @param boolean $debug Set this to true to use Paypal's sandbox instead of live version
     * @link https://developer.paypal.com/webapps/developer/docs/classic/api/apiCredentials/
     * to see how to get your credentials
     */
    public function PayPal(array $credentials, $debug = false) {

        // For debug purposes, switch to sandbox
        if ($debug) {
            $this->end_point = 'https://api-3t.sandbox.paypal.com/nvp';
            $this->url = 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=';
        }

        $this->credentials = $credentials;
    }

    /**
     * Sets visual elements for the PayPal confirmation page. Note that this
     * must be setted <b>before</b> set_express_checkout() is called.
     * Note : It seems that if $logo is set, $banner is not used
     * 
     * @param string $brandname Your organisation name
     * @param string $banner URL to your banner (.jpg, .png or .gif - for the love of God, do not use .gifs)
     * @param string $logo URL to your logo
     * @return \PayPal $this
     */
    public function visual_elements($brandname = NULL, $banner = NULL, $logo = NULL) {

        if ($brandname) {
            $this->visual['BRANDNAME'] = $brandname;
        }

        if ($banner) {
            $this->visual['HDRIMG'] = $banner;
        }

        if ($logo) {
            $this->visual['LOGOIMG'] = $logo;
        }

        return $this;
    }

    /**
     * If instant_payment_only is defined, all delayed payments will be rejected 
     * by paypal. This ensure the payment won't be in a 'pending' state.
     * @param boolean $value 
     * @return \PayPal $this
     * @link https://developer.paypal.com/webapps/developer/docs/classic/express-checkout/integration-guide/ECImmediatePayment/
     */
    public function instant_payment_only($value = true) {
        $this->instant_payment_only = $value;
        return $this;
    }

    /**
     * Calculates the subtotal for a batch of PayPalItems
     * 
     * @param array/PayPalItem $items Array of PayPalItems or single PayPalItem
     * @return float Total price for the specified items
     */
    public function total($items) {

        if (!is_array($items)) {
            $items = array($items);
        }

        $transaction_amount = 0;

        foreach ($items as $index => $item) {
            $transaction_amount += $item->total();
        }

        return $transaction_amount;
    }

    /**
     * SetExpressCheckout : Part 1/3 of Express Checkout transactions.
     * Prepares a new PayPal Express Checkout transaction. After a successful
     * call to this method, you can redirect your client on PayPal's website via
     * the $paypal->redirect($token) method.
     * 
     * @param array/PayPalItem $items Array of PayPalItems or single PayPalItem
     * @param string $return_url
     * @param string $cancel_url
     * @param string $custom_data Custom information that will be passed to
     * PayPal. This Custom field will be included in the values returned by
     * get_express_checkout_details, with the key 'PAYMENTREQUEST_0_CUSTOM'
     * @return array string You'll need to use $returned_values['TOKEN']
     * @link https://developer.paypal.com/webapps/developer/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
     */
    public function set_express_checkout($items, $return_url, $cancel_url, $custom_data = NULL) {

        if (!is_array($items)) {
            $items = array($items);
        }

        $items_parameters = array();

        foreach ($items as $index => $item) {
            $items_parameters = array_merge($items_parameters,
                    $item->compile($index));
        }

        $payment_request = array(
            'PAYMENTREQUEST_0_AMT' => $this->total($items),
            'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
        );

        $parameters = array(
            'NOSHIPPING' => '1', // 0 - Shipping, 1 - No shipping
            'ALLOWNOTE' => '1',
            'RETURNURL' => $return_url,
            'CANCELURL' => $cancel_url,
        );

        if ($this->instant_payment_only) {
            $parameters['PAYMENTREQUEST_0_ALLOWEDPAYMENTMETHOD'] = 'InstantPaymentOnly';
        }

        if ($custom_data) {
            $parameters['PAYMENTREQUEST_0_CUSTOM'] = $custom_data;
        }

        return $this->request('SetExpressCheckout',
                        array_merge($payment_request, $parameters,
                                $this->visual, $items_parameters));
    }

    /**
     * Redirects user to PayPal's website to confirm (or cancel) the payment previously
     * set through set_express_checkout
     * 
     * @param string $token The token provided by PayPal on the set_express_checkout operation
     */
    public function redirect($token) {
        header('Location: ' . $this->url . urlencode($token));
    }

    /**
     * Part two of the famous J.R.R $Token trilogy.
     * This method gives details on an Express Checkout transaction prepared via set_express_checkout().
     * 
     * @param string $token The transaction token returned by paypal on set_express_checkout action
     * @return array informations about the express checkout transaction.
     * @link https://developer.paypal.com/webapps/developer/docs/classic/api/merchant/GetExpressCheckoutDetails_API_Operation_NVP/
     */
    public function get_express_checkout_details($token) {
        return $this->request('GetExpressCheckoutDetails',
                        array('TOKEN' => $token));
    }

    /**
     * Part three of the PayPal trilogy : the final confrontation. This method
     * completes the Express Checkout transactions
     * 
     * Note : it seems that the amount specified here is the amount that will be
     * charged to your custumer, without any influence from what you told him/her
     * in the set_express_checkout part. Review your code twice !
     * 
     * @param string $token The token returned by PayPal on the set_express_checkout operation ($_GET['token'])
     * @param string $payer_id payer_id specified by PayPal on the set_express_checkout operation ($_GET['PayerID'])
     * @param float $amount Total price to charge
     * @return array string Information provided by PayPal on the state of the Express checkout
     * @link https://developer.paypal.com/webapps/developer/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
     */
    public function do_express_checkout_payment($token, $payer_id, $amount) {
        $request_parameters = array('TOKEN' => $token,
            'PAYMENTACTION' => 'Sale',
            'PAYERID' => $payer_id,
            'PAYMENTREQUEST_0_AMT' => $amount,
            'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency);

        return $this->request('DoExpressCheckoutPayment', $request_parameters);
    }

    /**
     * Generic API request to paypal. This method is called through set_express_checkout,
     * get_express_checkout_details and do_express_checkout
     *
     * @param string $method string API method to request
     * @param array $parameters Additional request parameters
     * @return array / boolean Response array / boolean false on failure
     */
    private function request($method, $parameters = array()) {

        $request_parameters = array_merge(array('METHOD' => $method, 'VERSION' => $this->version),
                $this->credentials);

        // Building our NVP string
        $request = http_build_query($request_parameters + $parameters);

        $curl_handle = curl_init();
        curl_setopt_array($curl_handle,
                array(
            CURLOPT_URL => $this->end_point,
            CURLOPT_VERBOSE => 1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $request,
        ));

        $curl_response = curl_exec($curl_handle);

        // Checking for cURL errors
        if (curl_errno($curl_handle)) {
            die("Error whith cURL : " . curl_error($curl_handle));
        } else {
            curl_close($curl_handle);
            $response = array();

            // Parse the response
            parse_str($curl_response, $response);

            return $response;
        }
    }
}
