<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Block\Cart;

use \Magento\Framework\View\Element\Template;
use Magento\Framework\App\Request\Http;

class OrderReview extends Template
{
     protected $request;

    public function __construct(
        Template\Context $context,
        Http $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->request = $request;
    }

    public function getOrderReviewDetails()
    {
        return $this->request->getParam('shopperDetails');
    }
}
