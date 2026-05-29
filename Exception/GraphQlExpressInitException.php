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

namespace Adyen\ExpressCheckout\Exception;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

/**
 * Exception for GraphQL to be thrown when express checkout fails
 *
 * @api
 */
class GraphQlExpressInitException extends ExpressInitException implements ClientAware, ProvidesExtensions
{
    public const EXCEPTION_CATEGORY = 'graphql-adyen-express';

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return static::EXCEPTION_CATEGORY;
    }

    public function getExtensions(): array
    {
        return [
            'category' => $this->getCategory(),
        ];
    }
}