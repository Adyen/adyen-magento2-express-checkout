<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Plugin\Gateway\Request;

use Adyen\Payment\Gateway\Request\CheckoutDataBuilder as Subject;
use Adyen\ExpressCheckout\Model\IsExpressMethodResolverInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;

class CheckoutDataBuilder
{
    /**
     * @var IsExpressMethodResolverInterface
     */
    private $isExpressMethodResolver;

    /**
     * @param IsExpressMethodResolverInterface $isExpressMethodResolver
     */
    public function __construct(
        IsExpressMethodResolverInterface $isExpressMethodResolver
    ) {
        $this->isExpressMethodResolver = $isExpressMethodResolver;
    }

    /**
     * After build ensure payment state data is set in request body as paymentMethod
     *
     * @param Subject $subject
     * @param array $result
     * @param array $buildSubject
     * @return array
     */
    public function afterBuild(
        Subject $subject,
        array $result,
        array $buildSubject
    ): array {
        if (!isset($result['body']['paymentMethod'])) {
            /** @var PaymentDataObject $paymentDataObject */
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();
            $isAppleOrGooglePay = $this->isExpressMethodResolver->execute($payment);
            if ($isAppleOrGooglePay === true) {
                $paymentMethodStateData = $paymentAdditionalInfo['stateData']['paymentMethod'] ?? [];
                $result['body']['paymentMethod'] = $paymentMethodStateData;
            }
        }
        return $result;
    }
}
