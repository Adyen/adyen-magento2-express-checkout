<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\Webapi\ParamOverrider;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request\ParamOverriderInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\Quote\Model\Webapi\ParamOverriderCartId as CoreParamOverriderCartId;

class AdyenCartId implements ParamOverriderInterface
{
    /**
     * @var ParamOverriderCartId
     */
    private $quoteCartIdParamOverrider;

    /**
     * @param ParamOverriderCartId $quoteCartIdParamOverrider
     */
    public function __construct(
        ParamOverriderCartId $quoteCartIdParamOverrider
    ) {
        $this->quoteCartIdParamOverrider = $quoteCartIdParamOverrider;
    }

    /**
     * @inerhitDoc
     */
    public function getOverriddenValue()
    {
        try {
            return $this->quoteCartIdParamOverrider->getOverriddenValue();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
