PayPaws
=============

The PayPal API helper that is so cute and easy it will make you forget that PayPal's API is inconsistent and terribly programed !

Yeah, I know, I'm not the first one to do this. This is another helper to communicate with PayPal's API in PHP. This class has been designed to be *really* simple, and only supports ExpressCheckouts (what 90% of the developers will actually need).

## Pros

0. Easy to use
1. Minimum of code required to make people you good money
2. Free as in free speech
3. **Comes with a tutorial and examples**

## Cons

This class does not handle shipping costs, taxes, and payments other than ExpressCheckouts. But who cares ? 90% of the developers are looking for simple ExpressCheckouts, as they don't need all the fancy complicated stuff PayPal has to offer.

## Technical details

The protocol used to communicate with PayPal's API is NVP (name value pairs), as their SOAP API seems so... cryptic.

## Tutorial

Usually, the tutorial is what's missing from all those other Paypal helpers. If you don't think my explainations are clear enough, please, tell me about it : nicolas.k.hurtubise \[hat\] gmail \[dot\] com.
Communication with PayPal's API is done in 8 simple steps *(don't panic, half of those aren't real steps)*.

### 0. Create a new Paypal object, specifing your credentials

    $paypal = new PayPal(array('USER' => USER, 'PWD' => PWD, 'SIGNATURE' => SIGNATURE));
    
Note that if you want to do test it first (in PayPal's Sandbox, using fake money), you can use the second constructor.

    $paypal = new PayPal(array('USER' => USER, 'PWD' => PWD, 'SIGNATURE' => SIGNATURE), TRUE);

See http://developer.paypal.com/ to find out how to create and use sandbox accounts.

### 1. Set the Express Checkout.

    $paypal_items = array(
        new PayPalItem("Cheesecake", "A super cool homemade cheesecake", 10.50, 2),
        new PayPalItem("Chocolate Cake", "A super cool homemade chocolate cake", 15.00, 1),
    );
    
    // Stock the total in a database somewhere
    $total = $paypal->total($paypal_items);
    $bdd->query("...");
    
    $response = $paypal->set_express_checkout($paypal_items, 'http://example.com/return_url', 'http://example.com/cancel_url');


#### 1.5 Bonus stuff

You can chain functions to set optionnal elements.

For example,

    $paypal->instant_payment_only();

Forces the buyer to pay instantly (delayed payments are therefore automaticly refused).

Similarly,

    $paypal->visual_elements('My company', 'http://example.com/images/my_logo.jpg');

Customises PayPal's interface.

### 2. Use PayPal's response to redirect your client

    if (is_array($response) && in_array($response['ACK'], array('Success', 'SuccessWithWarning'))) { //Request successful
        $token = $response['TOKEN'];
        $paypal->redirect($token);
    } else {
        echo "Sir ! There's been a problem with the PayPals !";
    }

### 3. Your client is on PayPal and is doing great

You just sit there and wait for your client to come back home.

### 4. Your client is back on your website 

First, you can tell if your client canceled the purchase or not by looking at the URL he/she followed (remember the cancel/return URLs specified as parameters of set_express_checkout ?).
If your client didn't cancel the purchase, then it's time to Do the Express Checkout !

#### 4.1 Wait !!! Take some time to get more details on your customer first !

    // At this point, you won't be on the same page, and therefore, you'll need to create a new PayPal object
    $paypal = new PayPal($credentials);
    
    // PayPal will send you the same token you received from set_express_checkout() as a GET argument to the URL you provided
    $token = urldecode($_GET['token']);
    
    $response = $paypal->get_express_checkout_details($token);

You will find in $response (an array of name-value pairs returned by PayPal) all sorts of informations you can use to have a better idea of who your customer is. You should find his/her name, email, the note he/she left you, and more.

### 5. Do the Express Checkout

    // Assuming you still have those $token and $paypal from the step 4.1

    // Get the payer id sent by PayPal as a GET argument
    $payer_id = urldecode($_GET['PayerID']);
    
    // Get the total price from the database
    $total = ...;

    // $total is the price I specified earlyer at the set_express_checkout() through the items' unit prices and quantities
    $paypal->do_express_checkout_payment($token, $payer_id, $total);

### 6. ????????

...

### 7. Money

Voilà ! You just sold your first item via PayPal ! Congratulations !
