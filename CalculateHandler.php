<?php

namespace App\Handler\Order;

use App\Entity\Order\Code;
use App\Entity\User\User;
use App\Http\Controller\Order\CalculateRequest;
use App\Repository\CodeRepository;
use App\Service\Configuration\ConfigurationManager;
use App\Service\Order\CalculateService;

/**
 * Class CalculateHandler
 * @package App\Handler\Order
 */
class CalculateHandler {

    use OrderTrait;

    const ORDER_COMMISSION = 'ORDER_COMMISSION';

    /**
     * @var \App\Service\Configuration\ConfigurationManager
     */
    private $configurationManager;

    /**
     * CalculateHandler constructor.
     *
     * @param \App\Repository\CodeRepository $codeRepository
     * @param \App\Service\Configuration\ConfigurationManager $configurationManager
     * @param \App\Service\Order\CalculateService $calculateService
     */
    public function __construct(
        CodeRepository $codeRepository,
        ConfigurationManager $configurationManager,
        CalculateService $calculateService
    ) {
        $this->codeRepository = $codeRepository;
        $this->configurationManager = $configurationManager;
        $this->calculateService = $calculateService;
    }

    /**
     * @param \App\Http\Controller\Order\CalculateRequest $request
     * @param \App\Entity\User\User $consumer
     *
     * @return \App\Entity\Order\Calculate
     */
    public function __invoke(CalculateRequest $request, User $consumer) {
        $discountError = true;
        $discount = 0;
        $code = $this->getDiscountCode($request, $consumer);

        if ($code instanceof Code) {
            $discount = $code->getDiscount();
            $discountError = false;
        }

        if (empty($request->get('discountCode'))) {
            $discountError = false;
        }

        $calculate = $this->calculateService->calculateOrder($request, $discount);
        $calculate->setDiscountError($discountError);

        return $calculate;
    }
}