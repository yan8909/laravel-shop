<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Rest\ApiContext;
use PayPal\Api\PaymentExecution;
use PayPal\Auth\OAuthTokenCredential;
use App\Models\Order;

class PaymentController extends Controller
{
    public function __construct()
    {
        /** PayPal api context **/
        $paypal_conf = \Config::get('paypal');
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                $paypal_conf['client_id'],
                $paypal_conf['secret']
            )
        );
        $this->apiContext->setConfig($paypal_conf['settings']);
    }

    public function createPaypal(Order $order, Request $request)
    {
        // ### Payer
        // A resource representing a Payer that funds a payment
        // For paypal account payments, set payment method
        // to 'paypal'.
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");
        // ### Itemized information
        // (Optional) Lets you specify item wise
        // information
        
        // $items = [];
        // foreach($order->items() as $item)
        // {
        //     $item = new Item();
        //     $item->setName($item->product->title)
        //     ->setCurrency('USD')
        //     ->setQuantity(1)
        //     ->setSku($item->productSku->title) // Similar to `item_number` in Classic API
        //     ->setPrice($order->total_amount);
        //     $items[] = $item;
        // };

        $item1 = new Item();
        $item1->setName("Laravel Shop's order:" . $order->no)
            ->setCurrency('USD')
            ->setQuantity(1)
            // ->setSku("123123") // Similar to `item_number` in Classic API
            ->setPrice($order->total_amount);



        $itemList = new ItemList();
        $itemList->setItems(array($item1));
        // ### Additional payment details
        // Use this optional field to set additional
        // payment information such as tax, shipping
        // charges etc.
        $details = new Details();
        $details
            ->setSubtotal($order->total_amount);
        // ### Amount
        // Lets you specify a payment amount.
        // You can also specify additional details
        // such as shipping, tax.
        $amount = new Amount();
        $amount->setCurrency("USD")
            ->setTotal($order->total_amount)
            ->setDetails($details);
        // ### Transaction
        // A transaction defines the contract of a
        // payment - what is the payment for and who
        // is fulfilling it. 
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Payment description")
            ->setInvoiceNumber(uniqid());
        // ### Redirect urls
        // Set the urls that the buyer must be redirected to after 
        // payment approval/ cancellation.

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl(route('payment.execute-paypal', $order->id))
            ->setCancelUrl(route('root'));
        // ### Payment
        // A Payment Resource; create one using
        // the above types and intent set to 'sale'
        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));
        // For Sample Purposes Only.
        $request = clone $payment;
        // ### Create Payment
        // Create a payment by calling the 'create' method
        // passing it a valid apiContext.
        // (See bootstrap.php for more on `ApiContext`)
        // The return object contains the state and the
        // url to which the buyer must be redirected to
        // for payment approval
        try {
            $payment->create($this->apiContext);
        } catch (Exception $ex) {
            // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
            print("Created Payment Using PayPal. Please visit the URL to Approve." . $request);
            exit(1);
        }
        // ### Get redirect url
        // The API response provides the url that you must redirect
        // the buyer to. Retrieve the url from the $payment->getApprovalLink()
        // method
        $approvalUrl = $payment->getApprovalLink();
        // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
        return redirect($approvalUrl);
    }

    public function executePaypal(Order $order)
    {
        // ### Approval Status
        // Determine if the user approved the payment or not
        if (isset($_GET['success']) && $_GET['success'] == 'true') {
            // Get the payment Object by passing paymentId
            // payment id was previously stored in session in
            // CreatePaymentUsingPayPal.php
            $paymentId = $_GET['paymentId'];
            $payment = Payment::get($paymentId, $this->apiContext);
            // ### Payment Execute
            // PaymentExecution object includes information necessary
            // to execute a PayPal account payment.
            // The payer_id is added to the request query parameters
            // when the user is redirected from paypal back to your site
            $execution = new PaymentExecution();
            $execution->setPayerId($_GET['PayerID']);
            // ### Optional Changes to Amount
            // If you wish to update the amount that you wish to charge the customer,
            // based on the shipping address or any other reason, you could
            // do that by passing the transaction object with just `amount` field in it.
            // Here is the example on how we changed the shipping to $1 more than before.
            $transaction = new Transaction();
            $amount = new Amount();
            $details = new Details();
            $details
                ->setSubtotal($order->total_amount);
            $amount->setCurrency('USD');
            $amount->setTotal($order->total_amount);
            $amount->setDetails($details);
            $transaction->setAmount($amount);
            // Add the above transaction object inside our Execution object.
            $execution->addTransaction($transaction);
            try {
                // Execute the payment
                // (See bootstrap.php for more on `ApiContext`)
                $result = $payment->execute($execution, $this->apiContext);
                // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                print("Executed Payment" . $payment->getId() . "Result:" . $result);
                try {
                    $payment = Payment::get($paymentId, $this->apiContext);
                } catch (Exception $ex) {
                    // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                    print("Get Payment");
                    exit(1);
                }
            } catch (Exception $ex) {
                // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                print("Executed Payment");
                exit(1);
            }
            // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
            print("Get Payment" . $payment->getId());
            return $payment;
        } else {
            // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
            print("User Cancelled the Approval");
            exit;
        }
    }
}
