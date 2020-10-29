<?php

/**
 * Codisto Marketplace Sync Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @package   Codisto_Connect
 * @copyright 2016-2017 On Technology Pty. Ltd. (http://codisto.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://codisto.com/connect/
 */

namespace Codisto\Connect\Model\Source;

use Magento\InventoryApi\Api\StockRepositoryInterface;

class Stocks implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var StockRepositoryInterface
     */
    protected $stockRepository;
    
    public function __construct(
        StockRepositoryInterface $stockRepository
    ) {
        $this->stockRepository = $stockRepository;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $result = [];
        
        foreach ($this->stockRepository->getList()->getItems() as $stock) {
            $result[] = [
                'value' => $stock->getStockId(),
                'label' => $stock->getName()
            ];
        }
        return $result;
    }
}
