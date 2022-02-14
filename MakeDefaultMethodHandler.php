<?php

namespace App\Handler\Payment;


use App\Http\Exception\AppException;
use Braintree\CreditCard as BraintreeCreditCard;
use App\Entity\Payment\CreditCard;
use Braintree\PayPalAccount as BraintreePayPalAccount;
use App\Entity\Payment\PayPalAccount;
use App\Http\Controller\Payment\MakeDefaultMethodRequest;
use App\Service\Payment\Braintree;

/**
 * Class MakeDefaultMethodHandler
 * @package App\Handler\Payment
 */
class MakeDefaultMethodHandler {

    /**
     * @var \App\Service\Payment\Braintree
     */
    private $braintree;

    /**
     * MakeDefaultMethodHandler constructor.
     * @param \App\Service\Payment\Braintree $braintree
     */
    public function __construct(Braintree $braintree) {
        $this->braintree = $braintree;
    }

    /**
     * @param \App\Http\Controller\Payment\MakeDefaultMethodRequest $request
     * @return \App\Entity\Payment\CreditCard|\App\Entity\Payment\PayPalAccount|null
     */
    public function __invoke(MakeDefaultMethodRequest $request) {
        $result = $this->braintree->makeDefaultPaymentMethod($request->get('token'));

        if ($result) {
            if ($result instanceof BraintreeCreditCard) {
                return (new CreditCard())->build($result);
            } elseif ($result instanceof BraintreePayPalAccount) {
                return (new PayPalAccount())->build($result);
            } else {
                throw new AppException('Invalid payment method!');
            }
        }
        throw new AppException('An error occurred while requesting data from the payment system!');
    }

}