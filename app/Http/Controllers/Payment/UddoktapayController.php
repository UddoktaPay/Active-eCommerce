<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Models\CombinedOrder;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\SellerPackageController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CheckoutController;
use App\Lib\UddoktaPay;
use Session;
use Auth;
use Exception;

class UddoktapayController extends Controller
{

    public function pay(Request $request)
    {
        if (Auth::user()->phone == null) {
            flash(translate('Please add phone number to your profile'))->warning();
            return redirect()->route('profile');
        }

        if (Auth::user()->email == null) {
            $email = 'customer@exmaple.com';
        } else {
            $email = Auth::user()->email;
        }

        $amount = 0;
        if (Session::has('payment_type')) {
            if (Session::get('payment_type') == 'cart_payment') {
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $amount = round($combined_order->grand_total);
            } elseif (Session::get('payment_type') == 'wallet_payment') {
                $amount = round(Session::get('payment_data')['amount']);
            } elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = round($customer_package->amount);
            } elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = round($seller_package->amount);
            }
        }

        $requestData = [
            'full_name'     => Auth::user()->name ?? 'John Doe',
            'email'         => $email ?? 'john@doe.com',
            'amount'        => $amount,
            'metadata'      => [
                'payment_type' => Session::get('payment_type'),
                'combined_order_id' => Session::get('combined_order_id'),
                'payment_data' => Session::get('payment_data'),
            ],
            'redirect_url'  => route('uddoktapay.success'),
            'return_type'   => 'GET',
            'cancel_url'    => route('uddoktapay.cancel')
        ];

        try {
            $uddoktaPay = new UddoktaPay(config('uddoktapay.api_key'), config('uddoktapay.api_url'));
            $paymentUrl = $uddoktaPay->initPayment($requestData);
            return redirect($paymentUrl);
        } catch (Exception $e) {
            flash(translate('Something Went Wrong'))->error();
            return redirect()->route('cart');
        }
    }


    public function success(Request $request)
    {
        try {
            $uddoktaPay = new UddoktaPay(config('uddoktapay.api_key'), config('uddoktapay.api_url'));
            $response = $uddoktaPay->verifyPayment($request->invoice_id);
        } catch (Exception $e) {
            flash(translate('Something Went Wrong'))->error();
            return redirect()->route('cart');
        }

        if ($response['status'] !== 'COMPLETED') {
            flash(translate('Something Went Wrong'))->error();
            return redirect()->route('cart');
        }

        $payment_type = $response['metadata']['payment_type'];
        $combined_order_id = $response['metadata']['combined_order_id'];
        $payment_data = $response['metadata']['payment_data'];

        if ($payment_type == 'cart_payment') {
            return (new CheckoutController)->checkout_done($combined_order_id, json_encode($response));
        }

        if ($payment_type == 'wallet_payment') {
            return (new WalletController)->wallet_payment_done($payment_data, json_encode($response));
        }

        if ($payment_type == 'customer_package_payment') {
            return (new CustomerPackageController)->purchase_payment_done($payment_data, json_encode($response));
        }
        if ($payment_type == 'seller_package_payment') {
            return (new SellerPackageController)->purchase_payment_done($payment_data, json_encode($response));
        }
    }

    public function cancel(Request $request)
    {
        flash(translate('Payment failed'))->error();
        return redirect()->route('cart');
    }
}
