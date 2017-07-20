<?php

/**
 * Codisto LINQ Sync Extension
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

namespace Codisto\Connect\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

use Magento\Framework\Indexer\StateInterface;

class InstallData implements InstallDataInterface
{
    private $indexerFactory;

    public function __construct(\Magento\Indexer\Model\IndexerFactory $indexerFactory)
    {
        $this->indexerFactory = $indexerFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup; //unused param
        $context; // unused param

        $indexer = $this->indexerFactory->create();
        $indexer->load('codisto_index_product');
        $indexer->getState()->setStatus(StateInterface::STATUS_VALID);
        $indexer->setScheduled(true);

        $indexer = $this->indexerFactory->create();
        $indexer->load('codisto_index_category');
        $indexer->getState()->setStatus(StateInterface::STATUS_VALID);
        $indexer->setScheduled(true);

        $indexer = $this->indexerFactory->create();
        $indexer->load('codisto_index_order');
        $indexer->getState()->setStatus(StateInterface::STATUS_VALID);
        $indexer->setScheduled(true);
    }
}
